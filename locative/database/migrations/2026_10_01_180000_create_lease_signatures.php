<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bail PDF et signature électronique simple : document de bail généré,
 * signature de chaque partie (horodatage, IP, navigateur, empreinte SHA-256
 * du PDF signé) et bail signé final avec certificat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('lease_document_id')->nullable()->after('status')->constrained('documents')->nullOnDelete();
            $table->foreignId('signed_document_id')->nullable()->after('lease_document_id')->constrained('documents')->nullOnDelete();
            $table->timestamp('signed_at')->nullable()->after('signed_document_id');
        });

        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->enum('party', ['landlord', 'tenant']);
            $table->string('document_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->unique(['contract_id', 'party']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_signatures');
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lease_document_id');
            $table->dropConstrainedForeignId('signed_document_id');
            $table->dropColumn('signed_at');
        });
    }
};
