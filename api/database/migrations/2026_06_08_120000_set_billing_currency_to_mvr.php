<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('license_plans')->where('currency', 'USD')->update(['currency' => 'MVR']);
        DB::table('license_requests')->where('currency', 'USD')->update(['currency' => 'MVR']);
    }

    public function down(): void
    {
        DB::table('license_plans')->where('currency', 'MVR')->update(['currency' => 'USD']);
        DB::table('license_requests')->where('currency', 'MVR')->update(['currency' => 'USD']);
    }
};
