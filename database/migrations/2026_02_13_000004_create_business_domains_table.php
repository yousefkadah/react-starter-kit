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
        Schema::create('business_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique()->comment('Whitelisted business domain (e.g., stripe.com)');
            $table->timestamps();

            // Index for email domain lookups
            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_domains');
    }
};
