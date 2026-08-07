<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeployRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_endpoint_is_404_without_a_token(): void
    {
        config(['app.deploy_token' => 'secret-token']);
        $this->get('/_deploy/migrate')->assertNotFound();
        $this->get('/_deploy/migrate?token=wrong')->assertNotFound();
    }

    public function test_migrate_endpoint_is_404_when_no_token_configured(): void
    {
        config(['app.deploy_token' => null]);
        $this->get('/_deploy/migrate?token=anything')->assertNotFound();
    }

    public function test_migrate_endpoint_runs_with_the_correct_token(): void
    {
        config(['app.deploy_token' => 'secret-token']);
        $this->get('/_deploy/migrate?token=secret-token')->assertOk();
    }
}
