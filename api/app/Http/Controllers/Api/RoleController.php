<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\PermissionMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->when(! $request->user()->isPlatformAdmin(), fn ($query) => $query->where('tenant_id', $request->user()->tenant_id))
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $this->validateRole($request);
        $actor = $request->user();

        $tenantId = $actor->isPlatformAdmin()
            ? ($validated['tenant_id'] ?? null)
            : $actor->tenant_id;

        $scope = $actor->isPlatformAdmin()
            ? ($validated['scope'] ?? ($tenantId === null ? 'platform' : 'tenant'))
            : 'tenant';

        $this->authorizePermissionMatrix($actor, $validated['permissions']);
        $this->ensureUniqueSlug($validated['slug'], $tenantId);

        $role = Role::query()->create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'scope' => $scope,
            'permissions' => $validated['permissions'],
        ]);

        $this->audit($request, 'role.created', $role);

        return response()->json(['data' => $role], 201);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return response()->json(['data' => $role->loadCount('users')]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $validated = $this->validateRole($request, $role);
        $actor = $request->user();

        $this->authorizePermissionMatrix($actor, $validated['permissions']);
        $this->ensureUniqueSlug($validated['slug'], $role->tenant_id, $role->id);

        $role->forceFill([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'permissions' => $validated['permissions'],
        ])->save();

        $this->audit($request, 'role.updated', $role);

        return response()->json(['data' => $role->refresh()]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        abort_if($role->users()->exists(), 409, 'Roles assigned to users cannot be deleted.');

        $this->audit($request, 'role.deleted', $role);
        $role->delete();

        return response()->json(['status' => 'deleted']);
    }

    /**
     * @return array{name: string, slug: string, tenant_id?: int|null, scope?: string, permissions: array<string, mixed>}
     */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9-]+$/'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'scope' => ['nullable', Rule::in(['tenant', 'platform'])],
            'permissions' => ['required', 'array'],
            'permissions.modules' => ['nullable', 'array'],
            'permissions.modules.*' => [Rule::in(config('mtrack.tenancy.permission_levels'))],
            'permissions.fleet_groups' => ['nullable', 'array'],
            'permissions.fleet_groups.*' => ['array'],
            'permissions.fleet_groups.*.*' => [Rule::in(config('mtrack.tenancy.permission_levels'))],
            'permissions.trackers' => ['nullable', 'array'],
            'permissions.trackers.*' => ['array'],
            'permissions.trackers.*.*' => [Rule::in(config('mtrack.tenancy.permission_levels'))],
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        if ($role !== null) {
            unset($validated['tenant_id'], $validated['scope']);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function authorizePermissionMatrix(User $actor, array $permissions): void
    {
        if ($actor->isPlatformAdmin()) {
            return;
        }

        $allowedModules = collect(config('mtrack.modules.customer'));
        $requested = collect($permissions['modules'] ?? [])
            ->map(fn (string $level, string $module): array => [$module, $level]);

        foreach (['fleet_groups', 'trackers'] as $scope) {
            foreach (($permissions[$scope] ?? []) as $scopeRules) {
                foreach ($scopeRules as $module => $level) {
                    $requested->push([$module, $level]);
                }
            }
        }

        foreach ($requested as [$module, $level]) {
            if (! $allowedModules->contains($module)) {
                throw ValidationException::withMessages([
                    'permissions' => "Tenant roles can only grant customer module permissions. Invalid module: {$module}.",
                ]);
            }

            if (! $actor->hasPermission($module, PermissionMatrix::normalize($level))) {
                throw ValidationException::withMessages([
                    'permissions' => "You cannot grant {$level} access to {$module}.",
                ]);
            }
        }
    }

    private function ensureUniqueSlug(string $slug, ?int $tenantId, ?int $ignoreRoleId = null): void
    {
        $exists = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreRoleId, fn ($query) => $query->whereKeyNot($ignoreRoleId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'slug' => 'A role with this slug already exists in this tenant.',
            ]);
        }
    }

    private function audit(Request $request, string $action, Role $role): void
    {
        AuditLog::query()->create([
            'tenant_id' => $role->tenant_id,
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $role::class,
            'subject_id' => $role->id,
            'metadata' => Arr::only($role->fresh()?->toArray() ?? $role->toArray(), ['name', 'slug', 'scope']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
