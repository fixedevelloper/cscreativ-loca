<?php

namespace App\Console\Commands;

use App\Jobs\SendRentReminder;
use App\Models\Rent;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendRentReminders extends Command
{
    protected $signature = 'app:send-rent-reminders';
    protected $description = "Détecte les loyers impayés et envoie les relances aux paliers J+3/J+7/J+15.";

    private const STAGE_DAYS = ['j3' => 3, 'j7' => 7, 'j15' => 15];

    public function handle(): int
    {
        $sent = 0;

        Rent::whereIn('status', ['pending', 'late'])
            ->whereNotNull('due_date')
            ->chunkById(200, function ($rents) use (&$sent) {
                foreach ($rents as $rent) {
                    $daysLate = Carbon::parse($rent->due_date)->diffInDays(now(), false);

                    foreach (self::STAGE_DAYS as $stage => $threshold) {
                        if ($daysLate >= $threshold && !$rent->reminders()->where('stage', $stage)->exists()) {
                            SendRentReminder::dispatch($rent->id, $stage);
                            $sent++;
                        }
                    }
                }
            });

        $this->info("{$sent} relance(s) mise(s) en file d'attente.");

        return Command::SUCCESS;
    }
}
