<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rents', function (Blueprint $table) {
            // Pénalité de retard figée au moment où elle est appliquée (ne
            // change plus rétroactivement si la config évolue ensuite).
            $table->decimal('late_penalty', 12, 2)->nullable()->after('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            // Qui a enregistré l'encaissement (bailleur/agent/admin) — le
            // modèle Payment déclarait déjà user_id en $fillable mais la
            // colonne n'existait pas en base.
            $table->foreignId('user_id')->nullable()->after('rent_id')
                ->constrained('users')->onDelete('set null');
        });

        // Trace d'audit des relances envoyées : évite les doublons (un seul
        // envoi par palier J+3/J+7/J+15 et par loyer) et sert de preuve en
        // cas de litige.
        Schema::create('rent_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_id')->constrained('rents')->onDelete('cascade');
            $table->enum('stage', ['j3', 'j7', 'j15']);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['rent_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_reminders');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('rents', function (Blueprint $table) {
            $table->dropColumn('late_penalty');
        });
    }
};
