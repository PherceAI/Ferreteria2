<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class GmailOAuthAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_gmail_connect_requires_manager_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']));

        $this->actingAs($user)
            ->get(route('purchasing.gmail.connect'))
            ->assertForbidden();
    }

    public function test_gmail_connect_uses_session_state(): void
    {
        config([
            'gmail_inbox.client_id' => 'client-id',
            'gmail_inbox.redirect_uri' => 'https://app.test/purchasing/gmail/callback',
            'gmail_inbox.auth_endpoint' => 'https://accounts.example.test/oauth',
        ]);

        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']));

        $response = $this->actingAs($user)
            ->get(route('purchasing.gmail.connect'));

        $response->assertRedirect();
        $response->assertSessionHas('gmail_oauth_state');

        $location = $response->headers->get('Location') ?? '';
        $this->assertStringStartsWith('https://accounts.example.test/oauth?', $location);
        $this->assertStringContainsString('client_id=client-id', $location);
        $this->assertStringContainsString('state=', $location);
    }

    public function test_gmail_callback_rejects_missing_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']));

        $this->actingAs($user)
            ->get(route('purchasing.gmail.callback', ['code' => 'oauth-code']))
            ->assertRedirect(route('purchasing.index'))
            ->assertSessionHas('error');
    }
}
