<?php

namespace Pterodactyl\Services\Ecosystem;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Models\EventBusEvent;
use Pterodactyl\Models\WebhookSubscription;

class EventBusService
{
    public function emit(
        string $eventKey,
        array $payload = [],
        ?string $source = null,
        ?int $serverId = null,
        ?int $actorUserId = null
    ): EventBusEvent {
        $event = EventBusEvent::query()->create([
            'event_key' => $eventKey,
            'source' => $source,
            'server_id' => $serverId,
            'actor_user_id' => $actorUserId,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        $subscriptions = WebhookSubscription::query()
            ->where('enabled', true)
            ->get();
        foreach ($subscriptions as $subscription) {
            if (!$this->matchesEventPattern($eventKey, (string) $subscription->event_pattern)) {
                continue;
            }

            $this->deliverToWebhook($subscription, $event);
        }

        return $event;
    }

    private function deliverToWebhook(WebhookSubscription $subscription, EventBusEvent $event): void
    {
        $body = [
            'event' => $event->event_key,
            'source' => $event->source,
            'server_id' => $event->server_id,
            'actor_user_id' => $event->actor_user_id,
            'payload' => $event->payload,
            'created_at' => optional($event->created_at)->toAtomString(),
        ];
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        $signature = $subscription->secret ? hash_hmac('sha256', (string) $json, (string) $subscription->secret) : '';

        try {
            $response = Http::timeout(3)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-HexTyl-Event' => $event->event_key,
                    'X-HexTyl-Signature' => $signature,
                ])
                ->post($subscription->url, $body);

            $subscription->forceFill([
                'delivery_success_count' => (int) $subscription->delivery_success_count + 1,
                'last_delivery_at' => now(),
                'last_delivery_status' => (string) $response->status(),
                'last_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $subscription->forceFill([
                'delivery_failed_count' => (int) $subscription->delivery_failed_count + 1,
                'last_delivery_at' => now(),
                'last_delivery_status' => 'error',
                'last_error' => Str::limit($exception->getMessage(), 1000),
            ])->save();
        }
    }

    private function matchesEventPattern(string $eventKey, string $pattern): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '' || $pattern === '*') {
            return true;
        }

        // Support wildcard suffix: security.*
        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim(substr($pattern, 0, -1), '.');

            return str_starts_with($eventKey, $prefix);
        }

        return $eventKey === $pattern;
    }
}
