<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lien explicite quittance -> loyer (avant : seulement via le titre
 * "Quittance {mois}" + contract_id). Permet d'afficher la quittance sur la
 * ligne du loyer et rend sa génération idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('rent_id')->nullable()->after('contract_id')
                ->constrained('rents')->nullOnDelete();
        });

        // Rattrapage des quittances existantes, retrouvées par leur titre.
        DB::table('documents')->where('type', 'quittance')->whereNull('rent_id')->orderBy('id')
            ->each(function ($document) {
                $month = trim(str_replace('Quittance', '', $document->title));
                $rentId = DB::table('rents')
                    ->where('contract_id', $document->contract_id)
                    ->where('month', $month)
                    ->value('id');
                if ($rentId) {
                    DB::table('documents')->where('id', $document->id)->update(['rent_id' => $rentId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rent_id');
        });
    }
};
