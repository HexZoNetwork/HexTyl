<?php

namespace Pterodactyl\Http\Requests\Api\Remote;

use Illuminate\Support\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\Security\SecurityEventService;

class ActivityEventRequest extends FormRequest
{
    private const HEADER_TIMESTAMP = 'X-HexWings-Timestamp';
    private const HEADER_NONCE = 'X-HexWings-Nonce';
    private const HEADER_SIGNATURE = 'X-HexWings-Signature';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'array', 'min:1', 'max:200'],
            'data.*' => ['array'],
            'data.*.user' => ['sometimes', 'nullable', 'uuid'],
            'data.*.server' => ['required', 'uuid'],
            'data.*.event' => ['required', 'string', 'regex:/^server:[a-z0-9._-]{1,120}$/'],
            'data.*.metadata' => ['present', 'nullable', 'array', 'max:40'],
            'data.*.ip' => ['sometimes', 'nullable', 'ip'],
            'data.*.timestamp' => ['required', 'string', 'regex:/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d{1,9})?(?:Z|[+-]\\d{2}:\\d{2})$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasValidActivitySignature()) {
                return;
            }

            $validator->errors()->add('signature', 'Invalid remote activity signature.');
        });
    }

    /**
     * Returns all the unique server UUIDs that were received in this request.
     */
    public function servers(): array
    {
        return Collection::make($this->input('data'))->pluck('server')->unique()->toArray();
    }

    /**
     * Returns all the unique user UUIDs that were submitted in this request.
     */
    public function users(): array
    {
        return Collection::make($this->input('data'))
            ->filter(function ($value) {
                return !empty($value['user']);
            })
            ->pluck('user')
            ->unique()
            ->toArray();
    }

    private function hasValidActivitySignature(): bool
    {
        /** @var Node|null $node */
        $node = $this->attributes->get('node');
        if (!$node instanceof Node) {
            return false;
        }

        $timestamp = trim((string) $this->header(self::HEADER_TIMESTAMP, ''));
        $nonce = trim((string) $this->header(self::HEADER_NONCE, ''));
        $signature = trim((string) $this->header(self::HEADER_SIGNATURE, ''));

        $required = (bool) config('remote_security.activity_signature.required', false);
        if ($timestamp === '' || $nonce === '' || $signature === '') {
            if (!$required) {
                return true;
            }

            $this->logSignatureFailure('missing_headers', $node, ['required' => true]);
            return false;
        }

        if (!preg_match('/^\d{10}$/', $timestamp)) {
            $this->logSignatureFailure('invalid_timestamp', $node, ['timestamp' => $timestamp]);
            return false;
        }

        if (!preg_match('/^[a-f0-9]{16,128}$/i', $nonce)) {
            $this->logSignatureFailure('invalid_nonce', $node, ['nonce' => $nonce]);
            return false;
        }

        $maxSkew = max(30, (int) config('remote_security.activity_signature.max_clock_skew_seconds', 180));
        if (abs(time() - (int) $timestamp) > $maxSkew) {
            $this->logSignatureFailure('timestamp_skew', $node, [
                'timestamp' => $timestamp,
                'max_clock_skew_seconds' => $maxSkew,
            ]);
            return false;
        }

        $normalized = preg_replace('/^sha256=/i', '', $signature);
        if (!is_string($normalized) || !preg_match('/^[a-f0-9]{64}$/i', $normalized)) {
            $this->logSignatureFailure('invalid_signature_format', $node, ['signature' => $signature]);
            return false;
        }

        $body = (string) $this->getContent();
        $expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $node->getDecryptedKey());
        if (!hash_equals(strtolower($expected), strtolower($normalized))) {
            $this->logSignatureFailure('signature_mismatch', $node, ['nonce' => $nonce]);
            return false;
        }

        $replayWindow = max(60, (int) config('remote_security.activity_signature.replay_window_seconds', 300));
        $replayKey = sprintf('security:daemon:activity:replay:%s:%s', $node->daemon_token_id, strtolower($nonce));
        if (!Cache::add($replayKey, 1, now()->addSeconds($replayWindow))) {
            $this->logSignatureFailure('replay_detected', $node, [
                'nonce' => $nonce,
                'replay_window_seconds' => $replayWindow,
            ]);
            return false;
        }

        return true;
    }

    private function logSignatureFailure(string $reason, Node $node, array $meta = []): void
    {
        app(SecurityEventService::class)->log('security:daemon.activity_signature.invalid', [
            'ip' => $this->ip(),
            'risk_level' => 'high',
            'meta' => array_merge([
                'path' => '/api/remote/activity',
                'reason' => $reason,
                'token_id' => (string) $node->daemon_token_id,
            ], $meta),
        ]);
    }
}
