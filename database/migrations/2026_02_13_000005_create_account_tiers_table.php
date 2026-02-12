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
        Schema::create('account_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Tier key (Email_Verified, Verified_And_Configured, Production, Live)');
            $table->string('name')->comment('Display name for tier (Email Verified, etc.)');
            $table->text('description')->nullable()->comment('Tier description and capabilities');
            $table->integer('order')->default(0)->comment('Sort order for tier progression');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_tiers');
    }
};
