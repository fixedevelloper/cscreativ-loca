<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La base_migrations initiale avait déjà tourné (batch 1) quand le soft
 * delete + les contraintes onDelete('restrict') ont été ajoutés dans son
 * fichier source : éditer un fichier de migration déjà exécuté ne change
 * rien au schéma réel, d'où cette migration incrémentale qui applique
 * effectivement ces changements à la base existante.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['properties', 'tenants', 'contracts', 'rents', 'payments', 'documents'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                if (!Schema::hasColumn($t->getTable(), 'deleted_at')) {
                    $t->softDeletes();
                }
            });
        }

        // 🔒 users -> properties/tenants : restrict, pas cascade (un bailleur
        // ayant des biens ne doit pas pouvoir être supprimé physiquement).
        Schema::table('properties', function (Blueprint $t) {
            $t->dropForeign(['user_id']);
            $t->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::table('tenants', function (Blueprint $t) {
            $t->dropForeign(['user_id']);
            $t->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $t->dropForeign(['property_id']);
            $t->foreign('property_id')->references('id')->on('properties')->onDelete('restrict');
        });

        // 🔒 Un bail signé et les paiements/loyers associés sont des pièces
        // légales/comptables : jamais de suppression physique en cascade.
        Schema::table('contracts', function (Blueprint $t) {
            $t->dropForeign(['property_id']);
            $t->foreign('property_id')->references('id')->on('properties')->onDelete('restrict');
            $t->dropForeign(['tenant_id']);
            $t->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
        });

        Schema::table('rents', function (Blueprint $t) {
            $t->dropForeign(['contract_id']);
            $t->foreign('contract_id')->references('id')->on('contracts')->onDelete('restrict');
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->dropForeign(['rent_id']);
            $t->foreign('rent_id')->references('id')->on('rents')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->dropForeign(['user_id']);
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->dropSoftDeletes();
        });

        Schema::table('tenants', function (Blueprint $t) {
            $t->dropForeign(['user_id']);
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->dropForeign(['property_id']);
            $t->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $t->dropSoftDeletes();
        });

        Schema::table('contracts', function (Blueprint $t) {
            $t->dropForeign(['property_id']);
            $t->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
            $t->dropForeign(['tenant_id']);
            $t->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $t->dropSoftDeletes();
        });

        Schema::table('rents', function (Blueprint $t) {
            $t->dropForeign(['contract_id']);
            $t->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $t->dropSoftDeletes();
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->dropForeign(['rent_id']);
            $t->foreign('rent_id')->references('id')->on('rents')->onDelete('cascade');
            $t->dropSoftDeletes();
        });

        Schema::table('documents', function (Blueprint $t) {
            $t->dropSoftDeletes();
        });
    }
};
