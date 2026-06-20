<?php

namespace App\Console\Commands;

use App\Models\PropertyTenant;
use Illuminate\Console\Command;

class ProcessScheduledMoveOuts extends Command
{
    protected $signature = 'turtle:process-move-outs';
    protected $description = 'Process scheduled tenant move-outs';

    public function handle(): int
    {
        $count = PropertyTenant::whereDate('moved_out_at', now()->toDateString())
            ->update(['moved_out_at' => now()]);

        $this->info("Processed {$count} tenant move-out(s).");

        return Command::SUCCESS;
    }
}
