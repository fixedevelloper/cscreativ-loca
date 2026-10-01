<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceComment extends Model
{
    protected $fillable = ['maintenance_request_id', 'user_id', 'body'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function request()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }
}
