<?php

namespace Zdp\BI\Models;

class SpGoods extends Model
{
    protected $table = 'sp_goods';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'date',
        'province',
        'city',
        'district',

        'order_id',
        'order_status',
        'goods_id',
        'goods_type_node',
        'goods_brand',
        'goods_sort',
        'goods_name',
        'goods_price',
        'method',
        'goods_num',

        'user_id',
        'type',
        'user_name',
        'user_shop',

        'shop_id',
        'shop_type',
        'shop_name',
        'shop_province',
        'shop_city',
        'shop_district',
        'shop_market',

        'sp_id',
        'sp_name',
        'sp_shop',
        'wechat_account',
    ];
}
