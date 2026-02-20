<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show queue health metrics (pending and failed jobs)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $latestFailed = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->latest('failed_at')->first()
            : null;

        $this->info('Queue Health');
        $this->line('Pending jobs: '.$pending);
        $this->line('Failed jobs: '.$failed);

        if ($latestFailed) {
            $this->line('Last failed job ID: '.$latestFailed->id);
            $this->line('Last failed at: '.$latestFailed->failed_at);
        } else {
            $this->line('Last failed at: none');
        }

        if (! Schema::hasTable('jobs')) {
            $this->line('Note: jobs table not found (queue connection may be non-database).');
        }

        return Command::SUCCESS;
    }
}
