<?php

namespace Zdp\BI\Models;

/**
 * 商品购买记录 (不含运费)
 *
 * @package Zdp\BI\Models
 */
class PurchaseLog extends Model
{
    protected $table = 'purchase_log';

    public $timestamps = false;

    protected $fillable = [
        'id', // 记录 ID - 对应 DpCartInfo - id

        'buyer_id',        // 买家ID
        'buyer_type',      // 买家类型
        'buyer_province',  // 买家省份
        'buyer_city',      // 买家城市
        'buyer_district',  // 卖家区域
        'buyer_market',    // 买家市场

        'seller_id',        // 卖家ID
        'seller_type',      // 卖家类型
        'seller_province',  // 卖家省份
        'seller_city',      // 卖家城市
        'seller_district',  // 卖家区域
        'seller_market',    // 卖家市场

        'goods_id',        // 商品ID
        'goods_type_node', // 商品类别
        'goods_brand',     // 商品品牌
        'goods_price',     // 商品单价

        'num',   // 购买数量
        'price', // 总支付金额

        'pay_method',         // 支付方式
        'pay_online_channel', // 线上支付渠道

        'delivery_method', // 物流方式

        'status', // 订单状态

        'created_at', // 购买时间
        'updated_at', // 最后更新时间
    ];

}