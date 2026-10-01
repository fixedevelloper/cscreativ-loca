<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            // 🔒 restrict, pas cascade : un bailleur ayant des biens ne peut pas être
            // supprimé physiquement (utiliser le soft delete de User à la place).
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('name');
            $table->string('address');
            $table->string('city');
            $table->string('surface');
            $table->integer('number_piece')->default(1);
            $table->decimal('monthly_charge',12, 2)->nullable();
            $table->enum('type',['room','studio','apartment','office','other']);
            $table->enum('status',['occupied','free','maintenance'])->default('free');
            $table->decimal('monthly_rent', 12, 2);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->enum('type',['society','personal'])->default('personal');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('property_id')->constrained('properties')->onDelete('restrict');
            $table->string('Profession')->nullable();
            $table->string('nationality')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_birth')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->enum('document_type',['cni','passport'])->nullable();
            $table->string('document_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            // 🔒 Un bail signé est une pièce légale : jamais de suppression
            // physique en cascade, uniquement un soft delete explicite.
            $table->foreignId('property_id')->constrained('properties')->onDelete('restrict');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('restrict');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('number_person')->default(1);
            $table->decimal('rent_amount', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->text('terms')->nullable();
            $table->enum('periodic', ['monthly', 'annual', 'semester'])->default('monthly');
            $table->enum('status', ['active', 'terminated', 'expired'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('restrict');
            $table->string('month');
            $table->string('due_date')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['paid', 'pending', 'late'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // 🔒 Un paiement encaissé est une pièce comptable : conservation
            // obligatoire, jamais de suppression physique en cascade.
            $table->foreignId('rent_id')->constrained('rents')->onDelete('restrict');
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'bank', 'mobile'])->default('cash');
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('agent_owner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('plan_name'); // Free, Pro, Agency
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('property_limit')->default(1);
            $table->integer('tenant_limit')->default(1);
            $table->integer('duration_days')->default(30);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active','pending', 'expired'])->default('pending');
            $table->timestamps();
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->nullable()->constrained()->onDelete('cascade');
            $table->json('shared_with')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('cascade'); // 🔹 utilisateur qui a uploadé
            $table->enum('uploader_role', ['owner', 'tenant', 'agent', 'admin'])->nullable(); // 🔹 rôle de l’uploader
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['contract', 'quittance', 'avis', 'autre'])->default('autre');
            $table->string('file_path');
            $table->boolean('is_shared')->default(false);
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('plan_name');
            $table->decimal('amount', 10, 2);
            $table->string('reference')->unique();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamps();
        });
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->decimal('total',10, 2)->default(1);
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // agent/admin ayant créé l’inventaire
            $table->enum('type', ['entry', 'exit']); // état des lieux d’entrée ou de sortie
            $table->text('general_condition')->nullable(); // description générale
            $table->date('inventory_date')->default(now());
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price',10, 2)->default(1);
            $table->enum('condition',['new','good','bad condition','medium condition','broken','missing','used'])->nullable(); // ex : Bon, Cassé, Manquant, Usé
            $table->text('remarks')->nullable();
            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
