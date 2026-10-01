<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement du loyer en ligne par le locataire (MoMo) : référence de la
 * transaction (X-Reference-Id) et numéro débité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference')->nullable()->unique()->after('status');
            $table->string('payer_phone', 20)->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'payer_phone']);
        });
    }
};
