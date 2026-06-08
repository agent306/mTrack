<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('fleet_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('visibility')->default('private')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('tracker_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('business_label')->nullable();
            $table->string('contract_key')->nullable()->index();
            $table->unsignedInteger('contract_version')->nullable();
            $table->string('status')->default('offline')->index();
            $table->foreignId('last_event_id')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'contract_key', 'contract_version']);
        });

        Schema::create('fleet_group_tracker_device', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fleet_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_device_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['fleet_group_id', 'tracker_device_id']);
            $table->index(['tenant_id', 'tracker_device_id']);
        });

        Schema::create('raw_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tracker_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('parser_contract_key')->nullable()->index();
            $table->unsignedInteger('parser_contract_version')->nullable();
            $table->timestamp('received_at')->index();
            $table->json('headers')->nullable();
            $table->longText('body_content');
            $table->string('body_content_type')->nullable();
            $table->string('processing_status')->default('received')->index();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'processing_status']);
            $table->index(['tenant_id', 'received_at']);
        });

        Schema::create('normalized_location_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_payload_id')->nullable()->constrained()->nullOnDelete();
            $table->string('parser_contract_key')->index();
            $table->unsignedInteger('parser_contract_version');
            $table->timestamp('event_timestamp')->index();
            $table->timestamp('received_timestamp')->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->decimal('altitude', 9, 2)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->json('status_metadata')->nullable();
            $table->json('normalized_metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'event_timestamp']);
            $table->index(['tracker_device_id', 'event_timestamp']);
        });

        Schema::table('tracker_devices', function (Blueprint $table) {
            $table->foreign('last_event_id')
                ->references('id')
                ->on('normalized_location_events')
                ->nullOnDelete();
        });

        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fleet_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('shape_type');
            $table->json('shape_geometry');
            $table->boolean('entrance_alert_enabled')->default(true);
            $table->boolean('exit_alert_enabled')->default(true);
            $table->decimal('speed_limit', 8, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'shape_type']);
        });

        Schema::create('geofence_tracker_device', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('geofence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_device_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['geofence_id', 'tracker_device_id']);
            $table->index(['tenant_id', 'tracker_device_id']);
        });

        Schema::create('alert_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('geofence_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('normalized_location_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'occurred_at']);
        });

        Schema::create('license_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('base_features')->nullable();
            $table->unsignedInteger('included_device_count')->default(0);
            $table->decimal('price_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('MVR');
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('license_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('active_device_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('license_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_type');
            $table->unsignedInteger('requested_device_count')->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('MVR');
            $table->string('status')->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('payment_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_slips');
        Schema::dropIfExists('license_requests');
        Schema::dropIfExists('license_allocations');
        Schema::dropIfExists('license_plans');
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('geofence_tracker_device');
        Schema::dropIfExists('geofences');
        Schema::table('tracker_devices', function (Blueprint $table) {
            $table->dropForeign(['last_event_id']);
        });
        Schema::dropIfExists('normalized_location_events');
        Schema::dropIfExists('raw_payloads');
        Schema::dropIfExists('fleet_group_tracker_device');
        Schema::dropIfExists('tracker_devices');
        Schema::dropIfExists('fleet_groups');
        Schema::dropIfExists('tenant_settings');
    }
};
