<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - property_limit / tenant_limit nullable : null = illimité (plan Agency,
 *   voir config/plans.php). En NOT NULL, souscrire à Agency levait une 500.
 * - status 'cancelled' : écrit par SubscriptionController::cancel mais absent
 *   de l'enum, d'où une 500 à chaque annulation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->integer('property_limit')->nullable()->default(null)->change();
            $table->integer('tenant_limit')->nullable()->default(null)->change();
            $table->enum('status', ['active', 'pending', 'expired', 'cancelled'])->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('subscriptions')->where('status', 'cancelled')->update(['status' => 'expired']);
        DB::table('subscriptions')->whereNull('property_limit')->update(['property_limit' => 0]);
        DB::table('subscriptions')->whereNull('tenant_limit')->update(['tenant_limit' => 0]);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->integer('property_limit')->default(1)->nullable(false)->change();
            $table->integer('tenant_limit')->default(1)->nullable(false)->change();
            $table->enum('status', ['active', 'pending', 'expired'])->default('pending')->change();
        });
    }
};
