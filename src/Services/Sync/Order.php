<?php

namespace Zdp\BI\Services\Sync;

use App\Models\DpBrands;
use App\Models\DpCartInfo;
use App\Models\DpGoodsType;
use App\Models\DpMarketInfo;
use App\Models\DpOpderForm;
use App\Models\DpShopInfo;
use Carbon\Carbon;
use Zdp\BI\Models\PurchaseLog;

/**
 * 订单数据同步服务
 *
 * @package Zdp\BI\Services
 */
class Order
{
    const SOURCE_NUM_LIMIT  = 100;
    const DEFAULT_START_DAY = '2017-01-01';

    protected $processHandle;

    /**
     * 支付成功的状态
     *
     * @var array
     */
    protected $existStatus = [
        DpCartInfo::CONFIRM_ORDER_GOODS            => '已确认收款',
        DpCartInfo::DELIVERY_ORDER_GOODS           => '已发货',
        DpCartInfo::TAKE_ORDER_GOODS               => '已确认收货',
        DpCartInfo::WITHDRAW_BEING_PROCESSED_ORDER => '提现中',
        DpCartInfo::WITHDRAW_ACCOMPLISH_ORDER      => '已提现',
        DpCartInfo::HAVE_EVALUATION                => '已评价',
    ];

    /**
     * 字段名转换
     *
     * @var array
     */
    protected $fields = [
        'dp_cart_info.id as id',

        'buyer_info.shid as buyer_id',
        'buyer.trenchnum as buyer_type',
        'buyer.province as buyer_province',
        'buyer.city as buyer_city',
        'buyer.county as buyer_district',
        'buyer.pianquid as buyer_market',

        'seller.shopid as seller_id',
        'seller.trenchnum as seller_type',
        'seller.province as seller_province',
        'seller.city as seller_city',
        'seller.county as seller_district',
        'seller.pianquid as seller_market',

        'goods.id as goods_id',
        'goods.sortid as goods_type_node',
        'goods.brand_id as goods_brand',
        'dp_cart_info.good_new_price as goods_price',

        'dp_cart_info.buy_num as num',
        'dp_cart_info.count_price as price',

        'order.method as pay_method',
        'order.payment_method as pay_online_channel',

        'order.delivery as delivery_method',

        'dp_cart_info.good_act as status',

        'order.addtime as created_at',
        'dp_cart_info.update_at as updated_at',
    ];

    /**
     * 关联表及其别名
     *
     * @var array
     */
    protected $joins = [
        [
            'dp_opder_form as order',
            'dp_cart_info.coid',
            '=',
            'order.order_code',
        ],
        [
            'dp_shanghuinfo as buyer_info',
            'dp_cart_info.uid',
            '=',
            'buyer_info.shid',
        ],
        [
            'dp_shopinfo as buyer',
            'buyer_info.shopid',
            '=',
            'buyer.shopid',
        ],
        [
            'dp_shopinfo as seller',
            'order.shopid',
            '=',
            'seller.shopid',
        ],
        [
            'dp_goods_info as goods',
            'dp_cart_info.goodid',
            '=',
            'goods.id',
        ],
        [
            'dp_goods_basic_attributes as attributes',
            'dp_cart_info.bid',
            '=',
            'attributes.basicid',
        ],
    ];

    /**
     * 订单数据同步
     *
     * @param Carbon|null   $time
     * @param callable|null $callback
     */
    public function sync(Carbon $time = null, callable $callback = null)
    {
        $this->processHandle = $callback;

        $this->source($time, function ($item) {
            $this->dataHandle($item);
        });
    }

    /**
     * 处理单个订单
     *
     * @param int|\Illuminate\Database\Eloquent\Model $item
     */
    protected function dataHandle($item)
    {
        try {
            if (!array_key_exists($item->status, $this->existStatus)) {
                PurchaseLog::where('id', $item->id)->delete();
            } else {
                $data = $item->toArray();
                foreach ($data as $key => &$val) {
                    $val = $this->cast($key, $val);
                }

                PurchaseLog::updateOrCreate(
                    ['id' => $item->id],
                    $data
                );
            }
        } catch (\Exception $e) {
            call_user_func($this->processHandle, $e);
        }

        if (is_callable($this->processHandle)) {
            call_user_func($this->processHandle);
        }
    }

    /**
     * 值转换
     *
     * @param string $key
     * @param mixed  $val
     *
     * @return mixed
     */
    protected function cast($key, $val)
    {
        switch ($key) {
            case 'buyer_type':
            case 'seller_type':
                // 转换用户类型ID为文字
                return DpShopInfo::getShopTypeName($val);

            case 'buyer_market':
            case 'seller_market':
                // 用户市场ID为文字
                return DpMarketInfo::where('pianquId', $val)->value('pianqu');

            case 'goods_type_node':
                // 转换商品类型为文字
                $goodsTypeIds = explode(',', $val);
                $goodsTypeNames = DpGoodsType
                    ::whereIn('id', $goodsTypeIds)
                    ->get(['id', 'sort_name'])
                    ->sort(function ($prev, $next) use ($goodsTypeIds) {
                        return array_search($prev->id, $goodsTypeIds) >
                               array_search($next->id, $goodsTypeIds);
                    })
                    ->lists('sort_name')
                    ->toArray();

                return implode(',', $goodsTypeNames);

            case 'goods_brand':
                // 转换品牌ID为文字
                $brand = DpBrands::find($val);
                if (empty($brand)) {
                    return '无品牌';
                } else {
                    return $brand->brand;
                }

            case 'pay_method':
                // 转换支付方式为文本
                return DpOpderForm::getPayMethodName($val);

            case 'pay_online_channel':
                // 转换线上支付渠道为文本
                return DpOpderForm::getPayTypeName($val);

            case 'delivery_method':
                // 转换物流方式为文本
                return DpOpderForm::getDeliveryName($val);

            case 'status':
                // 转换订单状态为文本
                return $this->castStatus($val);

            default:
                return $val;
        }
    }

    /**
     * 获取订单数据源
     *
     * @param Carbon|null $time
     * @param callable    $callback
     */
    public function source(Carbon $time = null, callable $callback)
    {
        $query = DpCartInfo::query();

        $query = $this->handleWhere($time, $query);

        $total = $query->count();

        if (is_callable($this->processHandle)) {
            call_user_func($this->processHandle, $total);
        }

        $pages = ceil($total / self::SOURCE_NUM_LIMIT);

        $this->handleJoin($query);
        $this->handleSelect($query);

        for ($p = 1; $p <= $pages; $p++) {
            $tmpQuery = clone $query;
            $tmpQuery->forPage($p, self::SOURCE_NUM_LIMIT)
                     ->get()
                     ->each($callback);
        }
    }

    /**
     * @param Carbon                             $time
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function handleWhere(Carbon $time = null, &$query)
    {
        if (empty($time)) {
            $time = Carbon
                ::createFromFormat('Y-m-d', static::DEFAULT_START_DAY)
                ->startOfDay();
        }

        $query->where('update_at', '>=', $time)
              ->where('goodid', '<>', 0);

        return $query;
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function handleJoin(&$query)
    {
        foreach ($this->joins as $join) {
            call_user_func_array([$query, 'leftJoin'], $join);
        }

        return $query;
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function handleSelect(&$query)
    {
        $query->select($this->fields);

        return $query;
    }

    /**
     * 获取状态文本
     *
     * @param int $status
     *
     * @return string|null
     */
    protected function castStatus($status)
    {
        return array_get($this->existStatus, $status);
    }

}