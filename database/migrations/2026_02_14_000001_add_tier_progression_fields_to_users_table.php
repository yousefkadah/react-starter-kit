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
        Schema::table('users', function (Blueprint $table) {
            // Production tier requests
            $table->timestamp('production_requested_at')->nullable()->after('approved_by');
            $table->timestamp('production_approved_at')->nullable()->after('production_requested_at');
            $table->foreignId('production_approved_by')
                ->nullable()
                ->references('id')
                ->on('users')
                ->after('production_approved_at');
            $table->timestamp('production_rejected_at')->nullable()->after('production_approved_by');
            $table->string('production_rejected_reason')->nullable()->after('production_rejected_at');

            // Pre-launch checklist (JSON field)
            $table->json('pre_launch_checklist')->nullable()->after('production_rejected_reason');

            // Live tier approval
            $table->timestamp('live_approved_at')->nullable()->after('pre_launch_checklist');

            $table->index('production_requested_at');
            $table->index('production_approved_at');
            $table->index('production_rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['production_approved_by']);
            $table->dropIndex(['production_requested_at']);
            $table->dropIndex(['production_approved_at']);
            $table->dropIndex(['production_rejected_at']);
            $table->dropColumn([
                'production_requested_at',
                'production_approved_at',
                'production_approved_by',
                'production_rejected_at',
                'production_rejected_reason',
                'pre_launch_checklist',
                'live_approved_at',
            ]);
        });
    }
};
