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
        Schema::create('bulk_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_template_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 100);
            $table->text('field_value');
            $table->json('filters')->nullable();
            $table->string('status', 20)->default('pending');
            $table->integer('total_count')->default(0);
            $table->integer('processed_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['pass_template_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_updates');
    }
};
