<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only confirmed bookings must be unique per slot. A unique index on
     * (slot_id, status) also blocks a second cancelled booking for the same
     * slot, so a nullable active_slot_id (set only while confirmed) carries
     * the uniqueness instead.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['slot_id', 'status']);
            $table->string('active_slot_id')->nullable()->unique();
        });

        DB::table('bookings')
            ->where('status', 'confirmed')
            ->update(['active_slot_id' => DB::raw('slot_id')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['active_slot_id']);
            $table->dropColumn('active_slot_id');
            $table->unique(['slot_id', 'status']);
        });
    }
};
