<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Encaissement refusé pour une raison métier (loyer déjà soldé, montant
 * supérieur au reste dû…). Porte une clé de traduction : le contrôleur la
 * rend dans la langue de la requête.
 */
class RentPaymentException extends RuntimeException
{
    public function __construct(public readonly string $key, public readonly array $params = [])
    {
        parent::__construct($key);
    }

    public function translated(): string
    {
        return __($this->key, $this->params);
    }
}
