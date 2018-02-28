<?php

namespace Zdp\BI\Models;

/**
 * 商品咨询记录
 *
 * @package Zdp\BI\Models
 */
class CallLog extends Model
{
    protected $table = 'call_log';

    public $timestamps = false;

    protected $fillable = [
        'buyer_id',        // 买家ID
        'buyer_type',      // 买家类型
        'buyer_name',      // 买家名字  dp_shangHuInfo.xingming
        'buyer_shop',       // 买家店铺
        'buyer_province',  // 买家省份
        'buyer_city',      // 买家城市
        'buyer_district',  // 买家家区域

        'telnumber',        // 拨打的电话号码

        'seller_id',        // 卖家ID
        'seller_type',      // 卖家类型
        'seller_name',      // 卖家名字  dp_shopInfo.dianPuName
        'seller_province',  // 卖家省份
        'seller_city',      // 卖家城市
        'seller_district',  // 卖家区域
        'seller_market',    // 卖家市场

        'goods_id',        // 商品ID
        'goods_name',      // 商品名字
        'goods_title',     // 商品标题
        'goods_sort',      // 商品类名
        'goods_type_node', // 商品分类串
        'goods_brand',     // 商品品牌
        'goods_price',     // 商品单价

        'call_times',   // 咨询次数

        'call_date', // 咨询日期
    ];

}
