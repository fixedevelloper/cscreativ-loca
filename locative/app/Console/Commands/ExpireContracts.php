<?php

namespace App\Console\Commands;

use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireContracts extends Command
{
    protected $signature = 'app:expire-contracts';
    protected $description = "Passe en 'expired' les contrats actifs dont la date de fin est dépassée et libère les biens concernés.";

    public function handle(): int
    {
        $count = 0;

        Contract::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now())
            ->with('property')
            ->chunkById(200, function ($contracts) use (&$count) {
                foreach ($contracts as $contract) {
                    DB::transaction(function () use ($contract) {
                        $contract->update(['status' => 'expired']);

                        $property = $contract->property;
                        if ($property && !$property->contracts()->where('status', 'active')->exists()) {
                            $property->update(['status' => 'free']);
                        }
                    });
                    $count++;
                }
            });

        $this->info("{$count} contrat(s) expiré(s).");

        return Command::SUCCESS;
    }
}
