<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(?User $actor, string $action, ?Model $subject = null, ?int $tenantId = null, array $metadata = [], ?Request $request = null): AuditLog
    {
        return AuditLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantId ?? $this->tenantId($actor, $subject),
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
        ]);
    }

    private function tenantId(?User $actor, ?Model $subject): ?int
    {
        $subjectTenantId = $subject?->getAttribute('tenant_id');

        if ($subjectTenantId !== null) {
            return (int) $subjectTenantId;
        }

        return $actor?->tenant_id;
    }
}
