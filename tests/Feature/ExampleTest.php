<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The app is behind the auth gate: guests hitting "/" are sent to login.
     */
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
