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
        Schema::create('pass_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bulk_update_id')->nullable()->constrained('bulk_updates')->nullOnDelete();
            $table->string('source', 20);
            $table->json('fields_changed');
            $table->string('apple_delivery_status', 20)->nullable();
            $table->string('google_delivery_status', 20)->nullable();
            $table->integer('apple_devices_notified')->default(0);
            $table->boolean('google_updated')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['pass_id', 'created_at']);
            $table->index('bulk_update_id');
            $table->index('created_at');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pass_updates');
    }
};
