<?php

namespace Zdp\BI\Models;

class LoanSyncOrder extends Model
{
    protected $table = 'loan_sync_order';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'date',
        'province',
        'city',
        'district',

        'ious_id',
        'amount',

        'shop_id',
        'shop_name',
    ];
}