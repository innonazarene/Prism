<?php

declare(strict_types=1);

namespace Innonazarene\PrismInit\Tests;

use Illuminate\Contracts\Console\Kernel;

class PrismServiceProviderTest extends TestCase
{
    /** @test */
    public function it_registers_the_prism_init_command(): void
    {
        $this->assertArrayHasKey('prism:init', $this->app->make(Kernel::class)->all());
    }

    /** @test */
    public function it_loads_config_defaults(): void
    {
        $this->assertSame('v1', config('prism-init.route_prefix'));
        $this->assertTrue(config('prism-init.grouped'));
        $this->assertTrue(config('prism-init.timestamps'));
        $this->assertTrue(config('prism-init.soft_deletes'));
        $this->assertTrue(config('prism-init.generate_services'));
        $this->assertTrue(config('prism-init.generate_resources'));
        $this->assertTrue(config('prism-init.generate_policies'));
        $this->assertContains('migrations', config('prism-init.exclude_tables'));
    }

    /** @test */
    public function it_publishes_config(): void
    {
        $this->artisan('vendor:publish', [
            '--provider' => 'Innonazarene\\PrismInit\\PrismServiceProvider',
            '--tag'      => 'prism-init-config',
        ])->assertSuccessful();
    }

    /** @test */
    public function it_publishes_stubs(): void
    {
        $this->artisan('vendor:publish', [
            '--provider' => 'Innonazarene\\PrismInit\\PrismServiceProvider',
            '--tag'      => 'prism-init-stubs',
        ])->assertSuccessful();
    }
}
