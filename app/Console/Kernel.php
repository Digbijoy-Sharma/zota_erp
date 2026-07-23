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
        $env = config('app.env');
        $email = config('mail.username');

        if ($env === 'live') {
            //Scheduling backup, specify the time when the backup will get cleaned & time when it will run.
            
            $schedule->command('backup:clean')->daily()->at('01:00');
            $schedule->command('backup:run')->daily()->at('01:30');


            //Schedule to create recurring invoices
            $schedule->command('pos:generateSubscriptionInvoices')->dailyAt('23:30');
            $schedule->command('pos:updateRewardPoints')->dailyAt('23:45');

            $schedule->command('pos:autoSendPaymentReminder')->dailyAt('8:00');

            $schedule->command('pos:generateRecurringExpense')->dailyAt('02:00');

            // Auto-retag every product per store every 90 days
            // (1st day of each quarter — Jan/Apr/Jul/Oct — at 03:00),
            // recomputing BOTH the movement tag AND min/max from sales.
            // The super admin's initial manual tag/min-max is the
            // bootstrap and is retained until a product has 90 days of
            // sales history, after which each 90-day run takes over.
            $schedule->command('pos:updateMovementTags')->cron('0 3 1 1,4,7,10 *');

            // Auto purchase orders: runs DAILY, but each store only raises
            // a combined PO when it is "due" per its own auto-PO frequency
            // (1-30 days) and has open auto-requisitions. withoutOverlapping
            // guards against a long run colliding with the next day's tick.
            $schedule->command('pos:autoRaisePurchaseOrders')->dailyAt('03:30')->withoutOverlapping();
        }

        if ($env === 'demo') {
            //IMPORTANT NOTE: This command will delete all business details and create dummy business, run only in demo server.
            $schedule->command('pos:dummyBusiness')
                    ->cron('0 */3 * * *')
                    //->everyThirtyMinutes()
                    ->emailOutputTo($email);
        }
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
