<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_subscribe_to_push_notifications(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('push.subscriptions.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-endpoint',
            'key' => 'fake-p256dh-key',
            'token' => 'fake-auth-token',
            'contentEncoding' => 'aesgcm',
        ]);

        $response->assertCreated();
        $response->assertJson(['subscribed' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => User::class,
            'subscribable_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-endpoint',
        ]);
    }

    public function test_user_can_unsubscribe_from_push_notifications(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/fake-endpoint-to-delete';

        $user->updatePushSubscription($endpoint);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => User::class,
            'subscribable_id' => $user->id,
            'endpoint' => $endpoint,
        ]);

        $response = $this->actingAs($user)->deleteJson(route('push.subscriptions.destroy'), [
            'endpoint' => $endpoint,
        ]);

        $response->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => $endpoint,
        ]);
    }
}
