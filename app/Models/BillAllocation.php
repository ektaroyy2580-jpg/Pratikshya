<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillAllocation extends Model
{
    protected $fillable = ['weight'];

    protected function casts(): array
    {
        return ['weight' => 'integer'];
    }

    public function item()
    {
        return $this->belongsTo(GroupBillItem::class, 'group_bill_item_id');
    }

    public function participant()
    {
        return $this->belongsTo(BillParticipant::class, 'bill_participant_id');
    }
}
