<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModuleServiceProvider;

return [
    AppServiceProvider::class,
    ModuleServiceProvider::class,
    // Registered explicitly (not via package discovery) so it survives a
    // pull-only deploy where bootstrap/cache/packages.php can be stale.
    Barryvdh\DomPDF\ServiceProvider::class,
];
