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
            // Account Setup Fields
            $table->enum('region', ['EU', 'US'])->default('US')->after('email');
            $table->string('tier')->default('Email_Verified')->after('region');
            $table->string('industry')->nullable()->after('tier');

            // Approval Workflow
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->after('industry');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->foreignId('approved_by')->nullable()->references('id')->on('users')->after('approved_at');

            // Indexes for filtering
            $table->index('region');
            $table->index('approval_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['region']);
            $table->dropIndex(['approval_status']);
            $table->dropColumn([
                'region',
                'tier',
                'industry',
                'approval_status',
                'approved_at',
                'approved_by',
            ]);
        });
    }
};
