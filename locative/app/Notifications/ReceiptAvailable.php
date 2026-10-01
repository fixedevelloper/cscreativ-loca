<?php

namespace App\Notifications;

use App\Models\Rent;

/** Au locataire : la quittance de son loyer soldé est disponible. */
class ReceiptAvailable extends InAppNotification
{
    public function __construct(private readonly Rent $rent)
    {
    }

    protected function type(): string
    {
        return 'receipt_available';
    }

    protected function params(): array
    {
        return ['month' => $this->rent->month, 'property' => $this->rent->contract?->property?->name];
    }

    protected function link(): ?string
    {
        return '/documents';
    }
}
