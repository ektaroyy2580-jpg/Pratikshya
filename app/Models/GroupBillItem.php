<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupBillItem extends Model
{
    protected $fillable = ['name', 'unit_price', 'quantity', 'is_shared', 'position'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'quantity' => 'integer', 'is_shared' => 'boolean', 'position' => 'integer'];
    }

    public function bill()
    {
        return $this->belongsTo(GroupBill::class, 'group_bill_id');
    }

    public function allocations()
    {
        return $this->hasMany(BillAllocation::class);
    }
}
