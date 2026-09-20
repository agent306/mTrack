<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\{AlertEvent, AuditLog, DeviceConnection, Geofence, NormalizedLocationEvent, TrackerDevice};
use App\Tracking\DeviceGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    private const MODULES = ['dashboard', 'live', 'my_fleets', 'geofence', 'events', 'analysis', 'routes', 'playback'];

    private function access(Request $request, string $module, string $level = 'view'): void
    {
        abort_unless($request->user()?->tenant_id && $request->user()->hasPermission($module, $level), 403);
    }

    private function trackers(Request $request)
    {
        return TrackerDevice::where('tenant_id', $request->user()->tenant_id)->with('lastEvent')->orderBy('display_name')->get();
    }

    public function show(Request $request, string $module)
    {
        $this->access($request, $module);
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'tracker' => ['nullable', 'integer'], 'fence' => ['nullable', 'integer'],
            'direction' => ['nullable', 'in:geofence_entry,geofence_exit'],
        ]);
        $from = $filters['from'] ?? now('UTC')->subDays(7)->toDateString();
        $to = $filters['to'] ?? now('UTC')->toDateString();
        $trackers = $this->trackers($request);
        $events = $this->events($request, $from, $to);
        $counts = (clone $events)->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $eventPage = $events->with(['trackerDevice', 'geofence'])->orderByDesc('occurred_at')->paginate(30)->withQueryString();
        $history = [];
        if (in_array($module, ['playback', 'routes']) && $request->integer('tracker') && $trackers->contains('id', $request->integer('tracker'))) {
            $history = NormalizedLocationEvent::where('tenant_id', $request->user()->tenant_id)
                ->where('tracker_device_id', $request->integer('tracker'))
                ->whereBetween('event_timestamp', [$from.' 00:00:00', $to.' 23:59:59'])
                ->orderBy('event_timestamp')->limit(2000)->get()->map(fn ($e) => [
                    'id' => $e->id, 'label' => $e->event_timestamp->toIso8601String(),
                    'latitude' => (float) $e->latitude, 'longitude' => (float) $e->longitude, 'speed' => (float) $e->speed * 3.6,
                ]);
        }
        return Inertia::render('Customer/Workspace', [
            'activeModule' => $module,
            'allowedModules' => array_values(array_filter([...self::MODULES, 'settings', 'billing', 'audit_log'], fn ($m) => $request->user()->hasPermission($m))),
            'canManageDevices' => $request->user()->hasPermission('my_fleets', 'edit'),
            'canManageGeofences' => $request->user()->hasPermission('geofence', 'edit'),
            'trackers' => $trackers->map(fn ($t) => [
                'id' => $t->id, 'display_name' => $t->display_name, 'imei' => $t->metadata['device_identity'] ?? null,
                'status' => $t->last_seen_at?->gt(now()->subMinutes(10)) ? $t->status : 'offline',
                'last_seen_at' => $t->last_seen_at?->toIso8601String(),
                'latitude' => $t->lastEvent ? (float) $t->lastEvent->latitude : null,
                'longitude' => $t->lastEvent ? (float) $t->lastEvent->longitude : null,
                'speed' => $t->lastEvent ? (float) $t->lastEvent->speed * 3.6 : null,
            ]),
            'geofences' => $request->user()->hasPermission('geofence') ? Geofence::where('tenant_id', $request->user()->tenant_id)->orderBy('name')->get()->map(fn ($f) => [
                'id' => $f->id, 'name' => $f->name, 'shape_type' => $f->shape_type, 'shape_geometry' => $this->geometry($f),
                'entrance_alert_enabled' => $f->entrance_alert_enabled, 'exit_alert_enabled' => $f->exit_alert_enabled,
            ]) : [],
            'events' => $eventPage->through(fn ($e) => [
                'id' => $e->id, 'device' => $e->trackerDevice?->display_name ?? 'Removed device',
                'place' => $e->geofence?->name ?? 'Removed place', 'type' => $e->type,
                'occurred_at' => $e->occurred_at->toIso8601String(),
            ]),
            'counts' => $counts, 'filters' => [...$filters, 'from' => $from, 'to' => $to],
            'history' => $history, 'preferences' => $request->user()->dashboard_preferences ?? ['widgets' => ['summary', 'map', 'devices', 'activity']],
            'connection' => ['host' => config('tracker.host'), 'port' => config('tracker.port')],
            'discovery' => $request->session()->get('device_discovery'),
            'refreshedAt' => now()->toIso8601String(),
        ]);
    }

    private function events(Request $request, string $from, string $to)
    {
        return AlertEvent::where('tenant_id', $request->user()->tenant_id)
            ->whereIn('type', ['geofence_entry', 'geofence_exit'])
            ->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($request->integer('tracker'), fn ($q, $id) => $q->where('tracker_device_id', $id))
            ->when($request->integer('fence'), fn ($q, $id) => $q->where('geofence_id', $id))
            ->when($request->input('direction'), fn ($q, $type) => $q->where('type', $type));
    }

    private function geometry(Geofence $fence): array
    {
        $g = $fence->shape_geometry;
        if ($fence->shape_type === 'circle') {
            $c = $g['center'];
            return ['center' => [$c['lat'] ?? $c[0], $c['lng'] ?? $c[1]], 'radius' => $g['radius'] ?? $g['radius_m'] ?? 300];
        }
        return ['points' => array_map(fn ($p) => [$p['lat'] ?? $p[0], $p['lng'] ?? $p[1]], $g['points'] ?? [])];
    }

    private function verifyDevice(Request $request): DeviceConnection
    {
        $this->access($request, 'my_fleets', 'edit');
        $data = $request->validate(['imei' => ['required', 'regex:/^\d{15}$/'], 'proof' => ['required', 'string', 'min:18', 'max:64']]);
        $device = DeviceConnection::where('imei', $data['imei'])->lockForUpdate()->first();
        $proof = hash('sha256', trim($data['proof']));
        $verified = $device && ! $device->tracker_device_id && (
            ($device->iccid_hash && hash_equals($device->iccid_hash, $proof)) ||
            ($device->claim_code_hash && hash_equals($device->claim_code_hash, $proof))
        );
        if (! $verified) {
            throw \Illuminate\Validation\ValidationException::withMessages(['proof' => 'Unable to verify an available device. Check its connection, IMEI and SIM ICCID, or ask support for a private claim code.']);
        }
        return $device;
    }

    public function discover(Request $request)
    {
        $device = DB::transaction(fn () => $this->verifyDevice($request));
        return back()->with('device_discovery', ['imei' => $device->imei, 'last_seen_at' => $device->last_seen_at->toIso8601String()]);
    }

    public function claim(Request $request, DeviceGateway $gateway)
    {
        $request->validate(['name' => ['required', 'string', 'max:120']]);
        DB::transaction(function () use ($request, $gateway) {
            $device = $this->verifyDevice($request);
            $tracker = TrackerDevice::create([
                'tenant_id' => $request->user()->tenant_id, 'display_name' => $request->string('name')->toString(),
                'contract_key' => 'vl512-gnss', 'contract_version' => 1, 'status' => 'no_data',
                'metadata' => ['device_identity' => $device->imei, 'claimed_by' => $request->user()->id],
            ]);
            $device->update(['tracker_device_id' => $tracker->id, 'claim_code_hash' => null]);
            $gateway->processPending($device);
            $this->audit($request, 'device.claimed', $tracker);
        }, 3);
        $request->session()->forget('device_discovery');
        return redirect('/customer/my-fleets')->with('status', 'Device claimed. New locations will appear here.');
    }

    public function preferences(Request $request)
    {
        $this->access($request, 'dashboard');
        $data = $request->validate([
            'widgets' => ['present', 'array', 'max:4'],
            'widgets.*' => ['distinct', Rule::in(['summary', 'map', 'devices', 'activity'])],
        ]);
        $request->user()->forceFill(['dashboard_preferences' => $data])->save();
        return back()->with('status', 'Your dashboard layout was saved.');
    }

    public function saveFence(Request $request, ?Geofence $geofence = null)
    {
        $this->access($request, 'geofence', 'edit');
        abort_if($geofence && $geofence->tenant_id !== $request->user()->tenant_id, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('geofences')->where('tenant_id', $request->user()->tenant_id)->ignore($geofence?->id)],
            'shape_type' => ['required', 'in:circle,polygon'], 'shape_geometry' => ['required', 'array'],
            'shape_geometry.center' => ['required_if:shape_type,circle', 'array', 'size:2'],
            'shape_geometry.center.0' => ['required_if:shape_type,circle', 'numeric', 'between:-90,90'],
            'shape_geometry.center.1' => ['required_if:shape_type,circle', 'numeric', 'between:-180,180'],
            'shape_geometry.radius' => ['required_if:shape_type,circle', 'numeric', 'between:10,100000'],
            'shape_geometry.points' => ['required_if:shape_type,polygon', 'array', 'min:3', 'max:100'],
            'shape_geometry.points.*' => ['array', 'size:2'],
            'shape_geometry.points.*.0' => ['required', 'numeric', 'between:-90,90'],
            'shape_geometry.points.*.1' => ['required', 'numeric', 'between:-180,180'],
            'entrance_alert_enabled' => ['required', 'boolean'], 'exit_alert_enabled' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($request, $geofence, $data) {
            $fence = $geofence ?? new Geofence(['tenant_id' => $request->user()->tenant_id]);
            $geometryChanged = $fence->shape_type !== $data['shape_type'] || $fence->shape_geometry !== $data['shape_geometry'];
            $fence->fill($data)->save();
            if ($geometryChanged) {
                DB::table('geofence_states')->where('geofence_id', $fence->id)->delete();
            }
            $this->audit($request, $geofence ? 'geofence.updated' : 'geofence.created', $fence);
        });
        return back()->with('status', 'Place saved. Entry and exit tracking starts with the next location.');
    }

    public function deleteFence(Request $request, Geofence $geofence)
    {
        $this->access($request, 'geofence', 'edit');
        abort_unless($geofence->tenant_id === $request->user()->tenant_id, 403);
        $this->audit($request, 'geofence.deleted', $geofence);
        $geofence->delete();
        return back()->with('status', 'Place deleted. Historical events remain in your reports.');
    }

    public function export(Request $request)
    {
        $this->access($request, 'analysis');
        $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'], 'direction' => ['nullable', 'in:geofence_entry,geofence_exit']]);
        $query = $this->events($request, $request->input('from'), $request->input('to'))->with(['trackerDevice', 'geofence']);
        $this->audit($request, 'geofence_report.exported', $request->user());
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time (UTC)', 'Device', 'Place', 'Direction'], ',', '"', '');
            foreach ($query->orderBy('id')->lazyById(500) as $event) {
                $row = [$event->occurred_at->utc()->toIso8601String(), $event->trackerDevice?->display_name ?? 'Removed device', $event->geofence?->name ?? 'Removed place', $event->type];
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\t\r\n]/', $v) ? "'".$v : $v, $row), ',', '"', '');
            }
            fclose($out);
        }, 'mtrack-place-events.csv', ['Content-Type' => 'text/csv']);
    }

    private function audit(Request $request, string $action, $subject): void
    {
        AuditLog::create(['tenant_id' => $request->user()->tenant_id, 'actor_id' => $request->user()->id,
            'action' => $action, 'subject_type' => $subject::class, 'subject_id' => $subject->id]);
    }
}
