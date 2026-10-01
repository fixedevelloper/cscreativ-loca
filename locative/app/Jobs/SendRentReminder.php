<?php

namespace App\Jobs;

use App\Models\Rent;
use App\Models\RentReminder;
use App\Notifications\RentOverdueNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Envoie une relance de loyer impayé pour un palier donné (j3/j7/j15) et
 * l'enregistre pour empêcher tout doublon (contrainte unique rent_id+stage).
 */
class SendRentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $rentId,
        private readonly string $stage,
    ) {
    }

    public function handle(): void
    {
        $rent = Rent::with('contract.tenant.user', 'contract.property')->find($this->rentId);

        if (!$rent || $rent->status === 'paid') {
            return;
        }

        // Fige la pénalité de retard calculée à cet instant, une seule fois.
        if ($rent->late_penalty === null) {
            $penalty = $rent->calculatePenalty();
            if ($penalty > 0) {
                $rent->update(['late_penalty' => $penalty, 'status' => 'late']);
            }
        }

        try {
            RentReminder::create([
                'rent_id' => $rent->id,
                'stage' => $this->stage,
                'sent_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Contrainte unique (rent_id, stage) déjà satisfaite : relance déjà envoyée.
            return;
        }

        $tenantUser = $rent->contract->tenant->user ?? null;
        if ($tenantUser) {
            $tenantUser->notify(new RentOverdueNotification($rent, $this->stage));
        }
    }
}
