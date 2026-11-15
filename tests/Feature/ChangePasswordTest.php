<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = 'current-password';

    private const NEW = 'a-much-better-password';

    private function user(): User
    {
        return User::factory()->create([
            'password' => Hash::make(self::CURRENT),
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/account/password')->assertRedirect('/login');

        $this->put('/account/password', [
            'current_password' => self::CURRENT,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertRedirect('/login');
    }

    public function test_the_page_is_shown_to_a_signed_in_user(): void
    {
        $this->actingAs($this->user())
            ->get('/account/password')
            ->assertOk()
            ->assertSee('Change password');
    }

    public function test_a_wrong_current_password_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/account/password')
            ->put('/account/password', [
                'current_password' => 'not-my-password',
                'password' => self::NEW,
                'password_confirmation' => self::NEW,
            ])
            ->assertRedirect('/account/password')
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/account/password')
            ->put('/account/password', [
                'current_password' => self::CURRENT,
                'password' => self::NEW,
                'password_confirmation' => 'something-else-entirely',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_a_too_short_password_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/account/password')
            ->put('/account/password', [
                'current_password' => self::CURRENT,
                'password' => 'short1',
                'password_confirmation' => 'short1',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_reusing_the_current_password_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/account/password')
            ->put('/account/password', [
                'current_password' => self::CURRENT,
                'password' => self::CURRENT,
                'password_confirmation' => self::CURRENT,
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }

    public function test_a_good_change_saves_a_new_hash_and_keeps_the_user_signed_in(): void
    {
        $user = $this->user();
        $oldRememberToken = $user->remember_token;

        $this->actingAs($user)
            ->from('/account/password')
            ->put('/account/password', [
                'current_password' => self::CURRENT,
                'password' => self::NEW,
                'password_confirmation' => self::NEW,
            ])
            ->assertRedirect('/account/password')
            ->assertSessionHas('status');

        $fresh = $user->fresh();

        $this->assertTrue(Hash::check(self::NEW, $fresh->password));
        $this->assertFalse(Hash::check(self::CURRENT, $fresh->password));
        $this->assertNotSame($oldRememberToken, $fresh->remember_token);
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_the_new_password_signs_in_and_the_old_one_does_not(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => self::CURRENT,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ]);

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', [
            'email' => $user->email,
            'password' => self::CURRENT,
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', [
            'email' => $user->email,
            'password' => self::NEW,
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_the_old_password_is_still_live_in_the_auth_layer_before_a_change(): void
    {
        $user = $this->user();

        $this->assertTrue(Auth::validate([
            'email' => $user->email,
            'password' => self::CURRENT,
        ]));
    }

    public function test_attempts_are_throttled(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->put('/account/password', [
                'current_password' => 'wrong-'.$i,
                'password' => self::NEW,
                'password_confirmation' => self::NEW,
            ])->assertStatus(302);
        }

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'wrong-again',
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertStatus(429);

        $this->assertTrue(Hash::check(self::CURRENT, $user->fresh()->password));
    }
}
