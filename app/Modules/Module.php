<?php

namespace App\Modules;

/**
 * A discovered tool module. Wraps its manifest (module.php) and exposes
 * locale-aware accessors used by the hub dashboard and navigation.
 */
class Module
{
    public function __construct(
        public readonly string $key,
        public readonly string $path,
        public readonly array $manifest,
    ) {
    }

    public function name(): string
    {
        return $this->localized('name', $this->key);
    }

    public function description(): string
    {
        return $this->localized('description', '');
    }

    public function icon(): string
    {
        return $this->manifest['icon'] ?? '';
    }

    /** Color key for the dashboard card: teal|violet|amber|sky|pink. */
    public function color(): string
    {
        return $this->manifest['color'] ?? 'violet';
    }

    /** Named route the dashboard card links to. */
    public function route(): ?string
    {
        return $this->manifest['route'] ?? null;
    }

    public function url(): ?string
    {
        return $this->route() ? route($this->route()) : null;
    }

    public function order(): int
    {
        return $this->manifest['order'] ?? 100;
    }

    /**
     * Optional dashboard quick actions. Each entry:
     * ['label' => ['ro'=>..,'en'=>..], 'route' => 'flower.runs.start', 'params' => [...]].
     *
     * @return array<int,array<string,mixed>>
     */
    public function shortcuts(): array
    {
        return $this->manifest['shortcuts'] ?? [];
    }

    protected function localized(string $field, string $default): string
    {
        $value = $this->manifest[$field] ?? $default;

        if (is_array($value)) {
            return $value[app()->getLocale()]
                ?? $value[config('app.fallback_locale')]
                ?? (reset($value) ?: $default);
        }

        return (string) $value;
    }
}
