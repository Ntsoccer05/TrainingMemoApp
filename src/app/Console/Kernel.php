<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 2年以上経った記録を削除(本番ではDBインスタンスが4時に停止しているため4時半に実行)
        $schedule->command('command:deleteRecords')->dailyAt('4:30');
        // 記録なし、セット数０の記録を削除(本番ではDBインスタンスが4時に停止しているため4時半に実行)
        $schedule->command('command:deleteNothingRecords')->dailyAt('4:30');
        // テスト用
        // $schedule->command('command:deleteNothingRecords')->everyMinute();
        // $schedule->command('command:deleteRecords')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
