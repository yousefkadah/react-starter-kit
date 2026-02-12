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
        Schema::create('pass_distribution_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')
                ->constrained('passes')
                ->cascadeOnDelete();
            $table->string('slug', 36)->unique();
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('accessed_count')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('pass_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pass_distribution_links');
    }
};
