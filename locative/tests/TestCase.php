<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Le client de test de Symfony envoie "Accept-Language: en-us" par
        // défaut (comme un navigateur) : on fixe la langue par défaut du
        // produit pour des assertions déterministes. Voir SetLocale.
        $this->withHeader('Accept-Language', 'fr');
    }
    //
}
