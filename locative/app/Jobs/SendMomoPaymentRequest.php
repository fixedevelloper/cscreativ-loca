<?php

namespace App\Jobs;

use App\Service\MomoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Appelle l'API MoMo (requestToPay) en tâche de fond : c'est un appel HTTP
 * externe potentiellement lent, il ne doit pas bloquer la réponse HTTP au
 * client (voir SubscriptionController::initiatePayment).
 * Le client vérifie ensuite le statut via SubscriptionController::confirm.
 */
class SendMomoPaymentRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /** Attente (secondes) avant chaque nouvelle tentative. */
    public $backoff = [10, 60];

    public function __construct(
        private readonly string $reference,
        private readonly string $phone,
        private readonly float $amount,
        private readonly ?string $message = null,
    ) {
    }

    public function handle(MomoService $momo): void
    {
        $momo->requestToPay($this->reference, $this->phone, $this->amount, $this->message);
    }
}
