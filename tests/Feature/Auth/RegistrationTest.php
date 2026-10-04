<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_route_is_removed(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_admin_registration_screen_can_be_rendered(): void
    {
        $admin = User::factory()->create(['email' => 'admin@email.com', 'role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/register');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_render_registration_screen(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com', 'role' => 'admin']);

        $response = $this->actingAs($user)->get('/admin/register');

        $response->assertStatus(403);
    }

    public function test_admin_can_register_new_users(): void
    {
        $admin = User::factory()->create(['email' => 'admin@email.com', 'role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/register', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'role' => 'user',
        ]);
        $response->assertRedirect(route('register', absolute: false));
    }
}
