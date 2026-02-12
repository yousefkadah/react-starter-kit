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
        Schema::create('google_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('issuer_id')->comment('Google Wallet issuer ID extracted from service account');
            $table->text('private_key')->encrypted()->comment('Encrypted Google service account private key');
            $table->string('project_id')->comment('Google Cloud project ID');
            
            // Rotation tracking
            $table->timestamp('last_rotated_at')->nullable()->comment('Last credential rotation timestamp');
            
            $table->softDeletes();
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('issuer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_credentials');
    }
};
