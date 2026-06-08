<?php

namespace App\Support\Authorization;

class PermissionMatrix
{
    public const HIDE = 'hide';

    public const VIEW = 'view';

    public const EDIT = 'edit';

    /**
     * @var array<string, int>
     */
    private const WEIGHTS = [
        self::HIDE => 0,
        self::VIEW => 1,
        self::EDIT => 2,
    ];

    /**
     * @param  array<string, mixed>|null  $permissions
     */
    public static function levelFor(?array $permissions, string $module, ?int $fleetGroupId = null, ?int $trackerId = null): string
    {
        $permissions ??= [];

        return self::maxLevel([
            self::moduleLevel($permissions, $module),
            self::scopedLevel($permissions, 'fleet_groups', $fleetGroupId, $module),
            self::scopedLevel($permissions, 'trackers', $trackerId, $module),
        ]);
    }

    public static function allows(string $actual, string $required): bool
    {
        return self::weight($actual) >= self::weight($required);
    }

    public static function normalize(string $level): string
    {
        return array_key_exists($level, self::WEIGHTS) ? $level : self::HIDE;
    }

    public static function maxLevel(array $levels): string
    {
        return collect($levels)
            ->map(fn (?string $level) => self::normalize($level ?? self::HIDE))
            ->sortByDesc(fn (string $level) => self::weight($level))
            ->first() ?? self::HIDE;
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private static function moduleLevel(array $permissions, string $module): string
    {
        $modules = $permissions['modules'] ?? [];

        if (! is_array($modules)) {
            return self::HIDE;
        }

        return self::normalize((string) ($modules[$module] ?? $modules['*'] ?? self::HIDE));
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private static function scopedLevel(array $permissions, string $scope, ?int $scopeId, string $module): string
    {
        if ($scopeId === null) {
            return self::HIDE;
        }

        $scopes = $permissions[$scope] ?? [];

        if (! is_array($scopes)) {
            return self::HIDE;
        }

        $rules = $scopes[(string) $scopeId] ?? [];

        if (is_string($rules)) {
            return self::normalize($rules);
        }

        if (! is_array($rules)) {
            return self::HIDE;
        }

        return self::normalize((string) ($rules[$module] ?? $rules['*'] ?? self::HIDE));
    }

    private static function weight(string $level): int
    {
        return self::WEIGHTS[self::normalize($level)];
    }
}
