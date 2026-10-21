<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slot_id');
            $table->foreign('slot_id')->references('id')->on('slots')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->enum('status', ['confirmed', 'cancelled']);
            $table->timestamps();
            $table->unique(['slot_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
