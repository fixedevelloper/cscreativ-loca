<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'inventory_id', 'item_name', 'quantity', 'condition', 'remarks'
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }
}
