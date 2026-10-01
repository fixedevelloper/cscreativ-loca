<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPayment;
use App\Models\Payment;
use App\Service\MomoReconciler;
use Illuminate\Console\Command;

class GetStatusMomo extends Command
{
    protected $signature = 'app:get-status-momo';
    protected $description = 'Vérifie et met à jour le statut des paiements MoMo en attente (filet de sécurité en complément du webhook).';

    /** Au-delà, un paiement toujours en attente est considéré abandonné. */
    private const PENDING_MAX_HOURS = 48;

    public function __construct(private readonly MomoReconciler $reconciler)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Un paiement jamais validé reste "pending" pour toujours : au-delà du
        // délai, on le clôt au lieu d'interroger MoMo toutes les 5 minutes
        // indéfiniment (abonnements ou loyers abandonnés).
        $cutoff = now()->subHours(self::PENDING_MAX_HOURS);
        $abandoned = SubscriptionPayment::where('status', 'pending')->where('amount', '>', 0)
            ->where('created_at', '<', $cutoff)->update(['status' => 'failed'])
            + Payment::where('status', 'pending')->whereNotNull('reference')
            ->where('created_at', '<', $cutoff)->update(['status' => 'failed']);
        if ($abandoned) {
            $this->info("{$abandoned} paiement(s) abandonné(s) clôturé(s).");
        }

        $payments = SubscriptionPayment::where('status', 'pending')->where('amount', '>', 0)->get()
            ->concat(Payment::where('status', 'pending')->whereNotNull('reference')->get());

        if ($payments->isEmpty()) {
            $this->info('Aucun paiement en attente trouvé.');
            return Command::SUCCESS;
        }

        foreach ($payments as $payment) {
            try {
                $status = $this->reconciler->reconcile($payment);
                $this->line(class_basename($payment) . " {$payment->reference} : {$status}");
            } catch (\Exception $e) {
                $this->error("Erreur pour {$payment->reference} : " . $e->getMessage());
            }
        }

        $this->info('✅ Vérification des paiements terminée.');
        return Command::SUCCESS;
    }
}
