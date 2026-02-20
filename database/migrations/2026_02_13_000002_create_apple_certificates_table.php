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
        Schema::create('apple_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('path')->comment('The certificate path as a string');
            $table->text('password')->nullable()->encrypted()->comment('Encrypted certificate password (optional for non-encrypted certs)');
            $table->timestamp('valid_from')->comment('Certificate validity start date');
            $table->timestamp('expiry_date')->comment('Certificate expiration date');

            // Expiry notification tracking
            $table->boolean('expiry_notified_30_days')->default(false);
            $table->boolean('expiry_notified_7_days')->default(false);
            $table->boolean('expiry_notified_0_days')->default(false);

            $table->string('fingerprint')->nullable()->comment('Certificate fingerprint for identification');

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apple_certificates');
    }
};
