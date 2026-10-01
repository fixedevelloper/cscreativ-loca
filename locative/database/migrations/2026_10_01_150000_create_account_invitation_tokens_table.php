<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tokens du broker "invitations" (config/auth.php), même structure que password_reset_tokens. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_invitation_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_invitation_tokens');
    }
};
