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
        Schema::table('passes', function (Blueprint $table) {
            $table->string('usage_type')->default('single_use')->after('status');
            $table->text('custom_redemption_message')->nullable()->after('usage_type');
            $table->timestamp('redeemed_at')->nullable()->after('custom_redemption_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->dropColumn(['usage_type', 'custom_redemption_message', 'redeemed_at']);
        });
    }
};
