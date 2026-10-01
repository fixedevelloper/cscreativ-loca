<?php

namespace App\Notifications;

use App\Models\Payment;

/** Au bailleur : un paiement de loyer a été reçu (manuel ou MoMo). */
class RentPaymentReceived extends InAppNotification
{
    public function __construct(private readonly Payment $payment)
    {
    }

    protected function type(): string
    {
        return $this->payment->rent->status === 'paid' ? 'rent_paid' : 'rent_partially_paid';
    }

    protected function params(): array
    {
        $rent = $this->payment->rent;

        return [
            'tenant' => $rent->contract?->tenant?->user?->name,
            'property' => $rent->contract?->property?->name,
            'month' => $rent->month,
            'amount' => (float) $this->payment->amount,
            'method' => $this->payment->method,
        ];
    }

    protected function link(): ?string
    {
        return '/rents';
    }
}
