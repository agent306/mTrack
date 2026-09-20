<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_connections', function (Blueprint $table) {
            $table->id();
            $table->string('imei', 15)->unique();
            $table->foreignId('tracker_device_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('iccid_hash', 64)->nullable();
            $table->string('claim_code_hash', 64)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });
        Schema::create('device_packets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_connection_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->text('packet_hex');
            $table->unsignedSmallInteger('protocol');
            $table->json('location')->nullable();
            $table->timestamp('received_at')->index();
            $table->timestamp('processed_at')->nullable();
        });
        Schema::create('geofence_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('geofence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_device_id')->constrained()->cascadeOnDelete();
            $table->boolean('inside');
            $table->timestamp('observed_at');
            $table->unique(['geofence_id', 'tracker_device_id']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->json('dashboard_preferences')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('dashboard_preferences'));
        Schema::dropIfExists('geofence_states');
        Schema::dropIfExists('device_packets');
        Schema::dropIfExists('device_connections');
    }
};
