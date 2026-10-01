<?php

namespace App\Notifications;

use App\Models\Contract;
use App\Models\User;

/** Bail : prêt à signer, signé par une partie, ou signé par tous. */
class LeaseUpdate extends InAppNotification
{
    /** @param string $event ready | signed_by | fully_signed */
    public function __construct(
        private readonly Contract $contract,
        private readonly string $event,
        private readonly User $actor,
    ) {
    }

    protected function type(): string
    {
        return "lease_{$this->event}";
    }

    protected function params(): array
    {
        return ['property' => $this->contract->property?->name, 'actor' => $this->actor->name];
    }

    protected function link(): ?string
    {
        return '/my_contracts';
    }
}
