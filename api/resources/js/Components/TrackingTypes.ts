import type { Geometry } from './PlaceMap.vue';
export type Tracker = { id: number; display_name: string; imei: string | null; status: string; last_seen_at: string | null; latitude: number | null; longitude: number | null; speed: number | null; tenant_id: number; customer?: string };
export type Fence = { id: number; name: string; shape_type: string; shape_geometry: Geometry; entrance_alert_enabled: boolean; exit_alert_enabled: boolean; tenant_id: number; customer?: string; tracker_ids: number[] };
export type Crossing = { id: number; device: string; place: string; type: string; occurred_at: string };
export type Pagination<T> = { data: T[]; total: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null };
export function displayTime(value: string | null) { return value ? new Date(value).toLocaleString(undefined, { timeZone: 'UTC', dateStyle: 'medium', timeStyle: 'short' }) + ' UTC' : 'Not reported'; }
