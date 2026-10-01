<?php

namespace App\Jobs;

use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Génère l'échéance de loyer du mois courant pour un contrat actif, si elle
 * n'existe pas déjà (idempotent — sûr à rejouer plusieurs fois/mois).
 */
class GenerateRentForContract implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $contractId)
    {
    }

    /** Nombre de mois couverts par une échéance, selon contracts.periodic. */
    private const PERIOD_MONTHS = ['monthly' => 1, 'semester' => 6, 'annual' => 12];

    public function handle(): void
    {
        $contract = Contract::find($this->contractId);

        if (!$contract || $contract->status !== 'active') {
            return;
        }

        $month = now()->format('Y-m');
        $dueDay = min((int) config('rent.due_day'), now()->daysInMonth);
        $dueDate = Carbon::create(now()->year, now()->month, $dueDay);
        $start = Carbon::parse($contract->start_date);

        // Prorata temporis simplifié : si le contrat démarre après le jour
        // d'échéance du mois en cours, on ne génère rien avant le mois suivant.
        if ($start->gt($dueDate)) {
            return;
        }

        // Bail terminé avant l'échéance : plus de loyer (le statut est mis à
        // jour par app:expire-contracts, mais on ne dépend pas de son passage).
        if ($contract->end_date && Carbon::parse($contract->end_date)->lt($dueDate)) {
            return;
        }

        // Échéance semestrielle/annuelle : uniquement les mois anniversaires
        // du début de bail. rent_amount est le montant de la période.
        $periodMonths = self::PERIOD_MONTHS[$contract->periodic ?? 'monthly'] ?? 1;
        $monthsSinceStart = ($dueDate->year - $start->year) * 12 + ($dueDate->month - $start->month);
        if ($monthsSinceStart % $periodMonths !== 0) {
            return;
        }

        $contract->rents()->firstOrCreate(
            ['month' => $month],
            [
                'due_date' => $dueDate->toDateString(),
                'amount' => $contract->rent_amount,
                'status' => 'pending',
            ]
        );
    }
}
