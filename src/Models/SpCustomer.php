<?php

namespace Zdp\BI\Models;

class SpCustomer extends Model
{
    protected $table = 'sp_customer';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'date',
        'province',
        'city',
        'district',

        'type',
        'user_id',
        'user_name',
        'user_shop',

        'sp_id',
        'sp_name',
        'sp_shop',
        'wechat_account',
    ];
}
