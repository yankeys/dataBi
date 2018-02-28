<?php

namespace Zdp\BI\Models;

class LoanUserLog extends Model
{
    protected $table = 'loan_user_log';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'date',
        'province',
        'city',
        'district',

        'shop_id',
        'shop_name',
        'status',
    ];
}