<?php

namespace Pterodactyl\Exceptions;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Application;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\Exception\TransportException;
use Pterodactyl\Services\Security\SecurityEventService;
use Pterodactyl\Exceptions\Repository\RecordNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Handler extends ExceptionHandler
{
    /**
     * The validation parser in Laravel formats custom rules using the class name
     * resulting in some weird rule names. This string will be parsed out and
     * replaced with 'p_' in the response code.
     */
    private const PTERODACTYL_RULE_STRING = 'pterodactyl\_rules\_';

    /**
     * A list of the exception types that should not be reported.
     */
    protected $dontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        RecordNotFoundException::class,
        TokenMismatchException::class,
        ValidationException::class,
    ];

    /**
     * Maps exceptions to a specific response code. This handles special exception
     * types that don't have a defined response code.
     */
    protected static array $exceptionResponseCodes = [
        AuthenticationException::class => 401,
        AuthorizationException::class => 403,
        ValidationException::class => 422,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     */
    protected $dontFlash = [
        'token',
        'secret',
        'password',
        'password_confirmation',
    ];

    /**
     * Registers the exception handling callbacks for the application. This
     * will capture specific exception types that we do not want to include
     * the detailed stack traces for since they could reveal credentials to
     * whoever can read the logs.
     *
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public function register(): void
    {
        if (config('app.exceptions.report_all', false)) {
            $this->dontReport = [];
        }

        $this->reportable(function (\PDOException $ex) {
            $ex = $this->generateCleanedExceptionStack($ex);
        });

        $this->reportable(function (TransportException $ex) {
            $ex = $this->generateCleanedExceptionStack($ex);
        });
    }

    private function generateCleanedExceptionStack(\Throwable $exception): string
    {
        $cleanedStack = '';
        foreach ($exception->getTrace() as $index => $item) {
            $cleanedStack .= sprintf(
                "#%d %s(%d): %s%s%s\n",
                $index,
                Arr::get($item, 'file'),
                Arr::get($item, 'line'),
                Arr::get($item, 'class'),
                Arr::get($item, 'type'),
                Arr::get($item, 'function')
            );
        }

        $message = sprintf(
            '%s: %s in %s:%d',
            class_basename($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        return $message . "\nStack trace:\n" . trim($cleanedStack);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Throwable
     */
    public function render($request, \Throwable $e): Response
    {
        $connections = $this->container->make(Connection::class);

        // If we are currently wrapped up inside a transaction, we will roll all the way
        // back to the beginning. This needs to happen, otherwise session data does not
        // get properly persisted.
        //
        // This is kind of a hack, and ideally things like this should be handled as
        // much as possible at the code level, but there are a lot of spots that do a
        // ton of actions and were written before this bug discovery was made.
        //
        // @see https://github.com/pterodactyl/panel/pull/1468
        if ($connections->transactionLevel()) {
            $connections->rollBack(0);
        }

        $guardResponse = $this->handleServerErrorAbuseGuard($request, $e);
        if ($guardResponse instanceof Response) {
            return $guardResponse;
        }

        return parent::render($request, $e);
    }

    private function handleServerErrorAbuseGuard($request, \Throwable $e): ?Response
    {
        $enabled = (bool) config('ddos.server_error_guard.enabled', true);
        if (!$enabled) {
            return null;
        }

        // Do not treat explicit HTTP exceptions (4xx/5xx from business logic) as exploit-like server errors.
        if ($e instanceof HttpExceptionInterface) {
            return null;
        }

        $path = '/' . ltrim((string) $request->path(), '/');
        // Never apply server error abuse guard to Wings <-> Panel internal channel.
        if (str_starts_with($path, '/api/remote/')) {
            return null;
        }

        $ip = (string) ($request->ip() ?? '');
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }

        $threshold = max(3, (int) config('ddos.server_error_guard.threshold_per_minute', 8));
        $blockMinutes = max(1, (int) config('ddos.server_error_guard.block_minutes', 15));
        $window = now()->format('YmdHi');
        $counterKey = "security:server_error_guard:{$ip}:{$window}";

        Cache::add($counterKey, 0, 120);
        $count = (int) Cache::increment($counterKey);
        Cache::put($counterKey, $count, 120);

        if ($count < $threshold) {
            return null;
        }

        Cache::put("ddos:ban:{$ip}", true, now()->addMinutes($blockMinutes));
        Cache::put("ddos:temp_block:{$ip}", true, now()->addMinutes($blockMinutes));

        try {
            app(SecurityEventService::class)->log('security:server_error_guard.triggered', [
                'actor_user_id' => optional($request->user())->id,
                'ip' => $ip,
                'risk_level' => 'high',
                'meta' => [
                    'path' => '/' . ltrim((string) $request->path(), '/'),
                    'method' => strtoupper((string) $request->method()),
                    'error_class' => class_basename($e),
                    'count_per_minute' => $count,
                    'threshold_per_minute' => $threshold,
                    'block_minutes' => $blockMinutes,
                ],
            ]);
        } catch (\Throwable) {
            // Never break exception rendering because of event logging failure.
        }

        if ($request->expectsJson() || str_starts_with((string) $request->path(), 'api/')) {
            return response()->json([
                'errors' => [[
                    'code' => 'TooManyRequestsHttpException',
                    'status' => '429',
                    'detail' => 'Request temporarily blocked by server error guard.',
                ]],
            ], 429);
        }

        return response('Request temporarily blocked by server error guard.', 429);
    }

    /**
     * Transform a validation exception into a consistent format to be returned for
     * calls to the API.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function invalidJson($request, ValidationException $exception): JsonResponse
    {
        $codes = Collection::make($exception->validator->failed())->mapWithKeys(function ($reasons, $field) {
            $cleaned = [];
            foreach ($reasons as $reason => $attrs) {
                $cleaned[] = Str::snake($reason);
            }

            return [str_replace('.', '_', $field) => $cleaned];
        })->toArray();

        $errors = Collection::make($exception->errors())->map(function ($errors, $field) use ($codes, $exception) {
            $response = [];
            foreach ($errors as $key => $error) {
                $meta = [
                    'source_field' => $field,
                    'rule' => str_replace(self::PTERODACTYL_RULE_STRING, 'p_', Arr::get(
                        $codes,
                        str_replace('.', '_', $field) . '.' . $key
                    )),
                ];

                $converted = $this->convertExceptionToArray($exception)['errors'][0];
                $converted['detail'] = $error;
                $converted['meta'] = array_merge($converted['meta'] ?? [], $meta);

                $response[] = $converted;
            }

            return $response;
        })->flatMap(function ($errors) {
            return $errors;
        })->toArray();

        return response()->json(['errors' => $errors], $exception->status);
    }

    /**
     * Return the exception as a JSONAPI representation for use on API requests.
     */
    protected function convertExceptionToArray(\Throwable $e, array $override = []): array
    {
        $match = self::$exceptionResponseCodes[get_class($e)] ?? null;

        $error = [
            'code' => class_basename($e),
            'status' => method_exists($e, 'getStatusCode')
                ? strval($e->getStatusCode())
                : strval($match ?? '500'),
            'detail' => $e instanceof HttpExceptionInterface || !is_null($match)
                ? $e->getMessage()
                : 'An unexpected error was encountered while processing this request, please try again.',
        ];

        if ($e instanceof ModelNotFoundException || $e->getPrevious() instanceof ModelNotFoundException) {
            // Show a nicer error message compared to the standard "No query results for model"
            // response that is normally returned. If we are in debug mode this will get overwritten
            // with a more specific error message to help narrow down things.
            $error['detail'] = 'The requested resource could not be found on the server.';
        }

        if (config('app.debug')) {
            $error = array_merge($error, [
                'detail' => $e->getMessage(),
                'source' => [
                    'line' => $e->getLine(),
                    'file' => str_replace(Application::getInstance()->basePath(), '', $e->getFile()),
                ],
                'meta' => [
                    'trace' => Collection::make($e->getTrace())
                        ->map(fn ($trace) => Arr::except($trace, ['args']))
                        ->all(),
                    'previous' => Collection::make($this->extractPrevious($e))
                        ->map(fn ($exception) => $e->getTrace())
                        ->map(fn ($trace) => Arr::except($trace, ['args']))
                        ->all(),
                ],
            ]);
        }

        return ['errors' => [array_merge($error, $override)]];
    }

    /**
     * Return an array of exceptions that should not be reported.
     */
    public static function isReportable(\Exception $exception): bool
    {
        return (new self(Container::getInstance()))->shouldReport($exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param \Illuminate\Http\Request $request
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return new JsonResponse($this->convertExceptionToArray($exception), JsonResponse::HTTP_UNAUTHORIZED);
        }

        return redirect()->guest('/auth/login');
    }

    /**
     * Extracts all the previous exceptions that lead to the one passed into this
     * function being thrown.
     *
     * @return \Throwable[]
     */
    protected function extractPrevious(\Throwable $e): array
    {
        $previous = [];
        while ($value = $e->getPrevious()) {
            if (!$value instanceof \Throwable) { // @phpstan-ignore instanceof.alwaysTrue
                break;
            }
            $previous[] = $value;
            $e = $value;
        }

        return $previous;
    }

    /**
     * Helper method to allow reaching into the handler to convert an exception
     * into the expected array response type.
     */
    public static function toArray(\Throwable $e): array
    {
        return (new self(app()))->convertExceptionToArray($e);
    }
}
