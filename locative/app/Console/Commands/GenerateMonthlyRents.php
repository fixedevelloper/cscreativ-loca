<?php

namespace App\Console\Commands;

use App\Jobs\GenerateRentForContract;
use App\Models\Contract;
use Illuminate\Console\Command;

class GenerateMonthlyRents extends Command
{
    protected $signature = 'app:generate-rent';
    protected $description = "Génère l'échéance de loyer du mois pour chaque contrat actif (une par contrat, en queue).";

    public function handle(): int
    {
        $count = 0;

        Contract::where('status', 'active')
            ->select('id')
            ->chunkById(200, function ($contracts) use (&$count) {
                foreach ($contracts as $contract) {
                    GenerateRentForContract::dispatch($contract->id);
                    $count++;
                }
            });

        $this->info("{$count} contrat(s) traité(s).");

        return Command::SUCCESS;
    }
}
