<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Demandes d'intervention (tickets) et leur fil de commentaires. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('restrict');
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('title');
            $table->text('description');
            $table->enum('category', ['plumbing', 'electricity', 'appliance', 'structure', 'security', 'other'])->default('other');
            $table->enum('priority', ['low', 'normal', 'urgent'])->default('normal');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['property_id', 'status']);
        });

        Schema::create('maintenance_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_comments');
        Schema::dropIfExists('maintenance_requests');
    }
};
