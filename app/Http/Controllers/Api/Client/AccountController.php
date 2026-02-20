<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Users\UserUpdateService;
use Pterodactyl\Transformers\Api\Client\AccountTransformer;
use Pterodactyl\Http\Requests\Api\Client\Account\UpdateEmailRequest;
use Pterodactyl\Http\Requests\Api\Client\Account\UpdatePasswordRequest;

class AccountController extends ClientApiController
{
    /**
     * AccountController constructor.
     */
    public function __construct(private AuthManager $manager, private UserUpdateService $updateService)
    {
        parent::__construct();
    }

    public function index(Request $request): array
    {
        return $this->fractal->item($request->user())
            ->transformWith($this->getTransformer(AccountTransformer::class))
            ->toArray();
    }

    /**
     * Update the authenticated user's email address.
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $original = $request->user()->email;
        $this->updateService->handle($request->user(), $request->validated());

        if ($original !== $request->input('email')) {
            Activity::event('user:account.email-changed')
                ->property(['old' => $original, 'new' => $request->input('email')])
                ->log();
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Update the authenticated user's password. All existing sessions will be logged
     * out immediately.
     *
     * @throws \Throwable
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = Activity::event('user:account.password-changed')->transaction(function () use ($request) {
            return $this->updateService->handle($request->user(), $request->validated());
        });

        $guard = $this->manager->guard();
        // If you do not update the user in the session you'll end up working with a
        // cached copy of the user that does not include the updated password. Do this
        // to correctly store the new user details in the guard and allow the logout
        // other devices functionality to work.
        $guard->setUser($user);

        // This method doesn't exist in the stateless Sanctum world.
        if (method_exists($guard, 'logoutOtherDevices')) { // @phpstan-ignore function.alreadyNarrowedType
            $guard->logoutOtherDevices($request->input('password'));
        }

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Upload or replace the authenticated user's avatar image.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'avatar' => 'required|file|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $user = $request->user();
        $file = $data['avatar'];
        $extension = strtolower((string) ($file->guessExtension() ?: $file->extension() ?: 'png'));
        $filename = sprintf('%s-%s.%s', $user->uuid, now()->timestamp, $extension);
        $path = $file->storeAs('avatars', $filename, 'public');

        $previous = trim((string) ($user->avatar_path ?? ''));
        if ($previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $this->updateService->handle($user, [
            'avatar_path' => $path,
            'gravatar' => false,
        ]);

        Activity::event('user:account.avatar-updated')
            ->property(['avatar_path' => $path])
            ->log();

        return new JsonResponse([
            'object' => 'avatar',
            'attributes' => [
                'url' => $user->fresh()->avatar_url,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Remove custom avatar and revert to default avatar source.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        $previous = trim((string) ($user->avatar_path ?? ''));
        if ($previous !== '') {
            Storage::disk('public')->delete($previous);
        }

        $this->updateService->handle($user, [
            'avatar_path' => null,
            'gravatar' => true,
        ]);

        Activity::event('user:account.avatar-removed')->log();

        return new JsonResponse([
            'object' => 'avatar',
            'attributes' => [
                'url' => $user->fresh()->avatar_url,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Update profile preferences such as dashboard template.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dashboard_template' => 'required|string|in:midnight,ocean,ember',
        ]);

        $this->updateService->handle($request->user(), $data);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
