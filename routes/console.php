<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\ProcessScheduledMoveOuts;

Schedule::command('turtle:process-move-outs')->daily();
