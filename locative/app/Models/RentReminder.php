<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentReminder extends Model
{
    protected $fillable = ['rent_id', 'stage', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function rent()
    {
        return $this->belongsTo(Rent::class);
    }
}
