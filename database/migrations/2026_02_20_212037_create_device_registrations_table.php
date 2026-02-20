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
        Schema::create('device_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('device_library_identifier', 64);
            $table->string('push_token', 128);
            $table->string('pass_type_identifier', 128);
            $table->string('serial_number', 64);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['device_library_identifier', 'pass_type_identifier', 'serial_number'], 'device_registrations_device_pass_unique');
            $table->index('serial_number');
            $table->index(['user_id', 'is_active']);
            $table->index('push_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_registrations');
    }
};
