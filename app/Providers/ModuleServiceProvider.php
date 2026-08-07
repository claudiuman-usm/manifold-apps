<?php

namespace App\Providers;

use App\Modules\Module;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-discovers every tool under app/Modules/*. For each module it wires up
 * routes (prefixed + named by module key, behind web+auth), migrations, views
 * ("{key}::view"), and translations ("{key}::file.line"). Registering a new
 * tool requires only dropping in its folder + module.php manifest.
 */
class ModuleServiceProvider extends ServiceProvider
{
    protected string $modulesPath;

    public function register(): void
    {
        $this->modulesPath = app_path('Modules');

        $this->app->singleton(ModuleRegistry::class, function () {
            $registry = new ModuleRegistry();

            foreach ($this->moduleDirectories() as $dir) {
                $manifestFile = $dir.'/module.php';

                if (! is_file($manifestFile)) {
                    continue;
                }

                $manifest = require $manifestFile;
                $key = $manifest['key'] ?? strtolower(basename($dir));

                $registry->add(new Module($key, $dir, $manifest));
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            $this->bootModule($module);
        }
    }

    protected function bootModule(Module $module): void
    {
        $path = $module->path;

        if (is_dir($path.'/resources/views')) {
            $this->loadViewsFrom($path.'/resources/views', $module->key);
        }

        if (is_dir($path.'/resources/lang')) {
            $this->loadTranslationsFrom($path.'/resources/lang', $module->key);
        }

        if (is_dir($path.'/Database/Migrations')) {
            $this->loadMigrationsFrom($path.'/Database/Migrations');
        }

        $routesFile = $path.'/routes.php';

        if (is_file($routesFile)) {
            Route::middleware(['web', 'auth'])
                ->prefix($module->key)
                ->name($module->key.'.')
                ->group($routesFile);
        }
    }

    /** @return array<int,string> */
    protected function moduleDirectories(): array
    {
        if (! is_dir($this->modulesPath)) {
            return [];
        }

        return array_filter(glob($this->modulesPath.'/*') ?: [], 'is_dir');
    }
}
