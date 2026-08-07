<?php

namespace App\Modules;

use Illuminate\Support\Collection;

/**
 * Holds every module discovered at boot. The dashboard reads this to render
 * one card per tool — adding a module never touches this class.
 */
class ModuleRegistry
{
    /** @var array<string,Module> */
    protected array $modules = [];

    public function add(Module $module): void
    {
        $this->modules[$module->key] = $module;
    }

    public function get(string $key): ?Module
    {
        return $this->modules[$key] ?? null;
    }

    /** @return Collection<int,Module> */
    public function all(): Collection
    {
        return collect($this->modules)
            ->sortBy(fn (Module $m) => $m->order())
            ->values();
    }
}
