<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractSignature extends Model
{
    protected $fillable = ['contract_id', 'user_id', 'party', 'document_hash', 'ip_address', 'user_agent', 'signed_at'];

    protected $casts = ['signed_at' => 'datetime'];

    public function signer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
