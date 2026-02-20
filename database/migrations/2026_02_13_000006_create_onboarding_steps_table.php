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
        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('step_key')->comment('Step identifier (email_verified, apple_setup, google_setup, user_profile, first_pass)');
            $table->timestamp('completed_at')->nullable()->comment('Timestamp when step was completed');
            $table->timestamps();

            // Compound index for querying incomplete steps by user
            $table->index(['user_id', 'step_key']);
            $table->index(['completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_steps');
    }
};
