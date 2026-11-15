<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Tests\TestCase;

class ViteProductionTest extends TestCase
{
    public function test_production_ignores_the_public_vite_hot_file(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        $this->app->instance(Vite::class, new Vite);
        ViteFacade::clearResolvedInstance();
        (new AppServiceProvider($this->app))->boot();

        $this->assertSame(
            $this->app->storagePath('framework/vite-hot-file-disabled'),
            $this->app->make(Vite::class)->hotFile(),
        );
    }
}
