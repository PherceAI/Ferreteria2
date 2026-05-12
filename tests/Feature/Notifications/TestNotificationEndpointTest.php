<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Notifications\GenericWebPushNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TestNotificationEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_endpoint_dispatches_three_webpush_with_correct_severity(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']));
        $user->updatePushSubscription(
            endpoint: 'https://fcm.googleapis.com/fcm/send/fake',
            key: 'fake-p256dh',
            token: 'fake-auth',
            contentEncoding: 'aesgcm',
        );

        Notification::fake();

        $response = $this->actingAs($user)->postJson(route('push.test'));

        $response->assertOk();
        $response->assertJson(['sent' => true, 'notifications' => 3, 'recipients' => 1]);

        Notification::assertSentToTimes($user, GenericWebPushNotification::class, 3);

        // Invariant crítico: el payload debe tener severity dentro de data
        // para que el Service Worker lo lea correctamente (bug recurrente).
        $severities = [];
        Notification::assertSentTo($user, GenericWebPushNotification::class, function ($notification) use (&$severities, $user) {
            $message = $notification->toWebPush($user, $notification);
            $payload = $message->toArray();

            $this->assertArrayHasKey('data', $payload);
            $this->assertArrayHasKey('severity', $payload['data'], 'severity debe vivir en payload.data.severity');

            $severities[] = $payload['data']['severity'];

            return true;
        });

        $this->assertEqualsCanonicalizing(['warning', 'critical', 'info'], $severities);
    }

    public function test_demo_endpoint_returns_422_when_no_subscribers(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']));

        $response = $this->actingAs($user)->postJson(route('push.test'));

        $response->assertStatus(422);
        $response->assertJson(['sent' => false]);
    }

    public function test_demo_endpoint_requires_internal_role(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']));

        $this->actingAs($user)
            ->postJson(route('push.test'))
            ->assertForbidden();
    }
}
