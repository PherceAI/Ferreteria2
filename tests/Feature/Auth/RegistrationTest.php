<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled_by_default(): void
    {
        $this->assertFalse(Features::enabled(Features::registration()));
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }
}
