<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Authorization\PermissionMatrix;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'auth_provider',
        'google_id',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canSignIn(): bool
    {
        return $this->isActive()
            && ($this->tenant_id === null || $this->tenant?->status === 'active');
    }

    public function isPlatformAdmin(): bool
    {
        if ($this->tenant_id !== null) {
            return false;
        }

        return $this->roles
            ->contains(fn (Role $role): bool => $role->scope === 'platform' || $role->slug === 'platform-admin');
    }

    public function permissionLevel(string $module, ?int $fleetGroupId = null, ?int $trackerId = null): string
    {
        if ($this->isPlatformAdmin()) {
            return PermissionMatrix::EDIT;
        }

        $levels = $this->roles
            ->filter(fn (Role $role): bool => $role->tenant_id === $this->tenant_id)
            ->map(fn (Role $role): string => PermissionMatrix::levelFor($role->permissions, $module, $fleetGroupId, $trackerId))
            ->all();

        return PermissionMatrix::maxLevel($levels);
    }

    public function hasPermission(string $module, string $required = PermissionMatrix::VIEW, ?int $fleetGroupId = null, ?int $trackerId = null): bool
    {
        return PermissionMatrix::allows(
            $this->permissionLevel($module, $fleetGroupId, $trackerId),
            $required,
        );
    }
}
