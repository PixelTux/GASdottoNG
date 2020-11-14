<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        // dummy
    ];

    protected function schedule(Schedule $schedule)
    {
    	$schedule->command('check:fees')->daily();
        $schedule->command('check:orders')->daily();
    }

    protected function commands()
    {
        require base_path('routes/console.php');
        $this->load(__DIR__ . '/Commands');
    }
}
