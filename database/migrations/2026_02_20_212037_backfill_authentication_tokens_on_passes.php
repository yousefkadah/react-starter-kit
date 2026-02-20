<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('passes')
            ->whereNull('authentication_token')
            ->orderBy('id')
            ->chunkById(100, function ($passes): void {
                foreach ($passes as $pass) {
                    do {
                        $token = bin2hex(random_bytes(32));
                        $exists = DB::table('passes')
                            ->where('authentication_token', $token)
                            ->exists();
                    } while ($exists);

                    DB::table('passes')
                        ->where('id', $pass->id)
                        ->update(['authentication_token' => $token]);
                }
            });

        if (in_array(DB::getDriverName(), ['pgsql', 'mysql'], true)) {
            DB::statement('ALTER TABLE passes ALTER COLUMN authentication_token SET NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'mysql'], true)) {
            DB::statement('ALTER TABLE passes ALTER COLUMN authentication_token DROP NOT NULL');
        }

        DB::table('passes')->update(['authentication_token' => null]);
    }
};
