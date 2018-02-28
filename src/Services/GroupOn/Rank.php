<?php

namespace Zdp\BI\Services\GroupOn;

use App\Exceptions\AppException;
use Carbon\Carbon;
use Zdp\Main\Data\Models\DpGoodsInfo;
use Zdp\Main\Data\Models\DpOpderForm;
use Zdp\Main\Data\Models\DpShopInfo;

/**
 * Class Rank
 *
 * 集中统计排行榜
 *
 * @package Zdp\BI\Services\GroupOn
 */
class Rank
{
    const TYPE_NAME = [
        'seller'        => '卖家排行',
        'seller.detail' => '卖家详情',
        'buyer'         => '买家排行',
        'goods'         => '商品排行',
    ];

    /**
     * @var \Illuminate\Database\Query\Builder
     */
    protected $query;

    /**
     * @var array
     */
    protected $select = ['*'];

    /**
     * 获取集中采购排行榜列表
     *
     * @param string  $type
     * @param array   $order
     * @param integer $page
     * @param integer $size
     * @param array   $time
     * @param mixed   $other
     *
     * @return array
     */
    public function page(
        $type = 'seller',
        $order = ['deal_money', 'desc'],
        $page = 1,
        $size = 100,
        $time = null,
        $other = null
    ) {
        $paginator = $this->handleType($type, $other)
                          ->handleOrder($order[0], $order[1])
                          ->handleTime($time)
                          ->getList($page, $size);

        return $paginator->toArray();
    }

    /**
     * @param string $type
     * @param mixed  $other
     *
     * @return $this
     * @throws AppException
     */
    protected function handleType($type, $other)
    {
        switch ($type) {
            case 'seller':
                $this->query = DpShopInfo
                    ::join(
                        'dp_opder_form',
                        function ($join) {
                            $join
                                ->on(
                                    'dp_opder_form.shopid',
                                    '=',
                                    'dp_shopinfo.shopid'
                                )
                                ->where(
                                    'dp_opder_form.buy_way',
                                    '=',
                                    DpOpderForm::CENTRALIZED_BUY
                                )
                                ->where(
                                    'dp_opder_form.method',
                                    '=',
                                    DpOpderForm::ORDER_PAY_METHOD_COMPANY
                                );
                        }
                    )
                    ->join(
                        'dp_cart_info',
                        'dp_cart_info.coid',
                        '=',
                        'dp_opder_form.order_code'
                    )
                    ->groupBy('dp_shopinfo.shopId')
                    ->selectRaw('dp_shopinfo.dianPuName as name')
                    ->addSelect('dp_shopinfo.shopId');
                break;

            case 'buyer':
                $this->query = DpShopInfo
                    ::join(
                        'dp_shanghuinfo',
                        'dp_shanghuinfo.shopid',
                        '=',
                        'dp_shopinfo.shopid'
                    )
                    ->join(
                        'dp_opder_form',
                        function ($join) {
                            $join
                                ->on(
                                    'dp_opder_form.uid',
                                    '=',
                                    'dp_shanghuinfo.shid'
                                )
                                ->where(
                                    'dp_opder_form.buy_way',
                                    '=',
                                    DpOpderForm::CENTRALIZED_BUY
                                )
                                ->where(
                                    'dp_opder_form.method',
                                    '=',
                                    DpOpderForm::ORDER_PAY_METHOD_COMPANY
                                );
                        }
                    )
                    ->join(
                        'dp_cart_info',
                        'dp_cart_info.coid',
                        '=',
                        'dp_opder_form.order_code'
                    )
                    ->groupBy('dp_shopinfo.shopId')
                    ->selectRaw('dp_shopinfo.dianPuName as name');
                break;

            case 'seller.detail':
            case 'goods':
                $this->query = DpGoodsInfo
                    ::join(
                        'dp_shopinfo',
                        'dp_shopinfo.shopid',
                        '=',
                        'dp_goods_info.shopid'
                    )
                    ->join(
                        'dp_cart_info',
                        'dp_cart_info.goodid',
                        '=',
                        'dp_goods_info.id'
                    )
                    ->join(
                        'dp_opder_form',
                        function ($join) {
                            $join
                                ->on(
                                    'dp_cart_info.coid',
                                    '=',
                                    'dp_opder_form.order_code'
                                )
                                ->where(
                                    'dp_opder_form.buy_way',
                                    '=',
                                    DpOpderForm::CENTRALIZED_BUY
                                )
                                ->where(
                                    'dp_opder_form.method',
                                    '=',
                                    DpOpderForm::ORDER_PAY_METHOD_COMPANY
                                );
                        }
                    )
                    ->groupBy('dp_goods_info.id')
                    ->selectRaw('dp_goods_info.gname as gname')
                    ->selectRaw('dp_shopinfo.dianPuName as name');
                break;

            default:
                throw new AppException('ILLEGAL TYPE!');
        }

        $validOrderStatusStr =
            implode(',', DpOpderForm::VALID_ORDER_STATUS);
        $this->query
            ->selectRaw(
                'sum(if(dp_opder_form.orderact IN (' . $validOrderStatusStr .
                '),dp_cart_info.buy_num,0)) AS deal_num'
            )
            ->selectRaw('sum(dp_cart_info.buy_num) as ordered_num')
            ->selectRaw(
                'sum(if(dp_opder_form.orderact IN (' . $validOrderStatusStr .
                '),dp_cart_info.count_price,0)) AS deal_money'
            )
            ->selectRaw('sum(dp_cart_info.count_price) as ordered_money')
            ->selectRaw(
                'sum(if(dp_opder_form.orderact IN (' . $validOrderStatusStr .
                '),(dp_cart_info.goods_price - dp_cart_info.good_new_price) * dp_cart_info.buy_num,0)) AS diff_money'
            )
            ->selectRaw(
                'sum(if(dp_opder_form.orderact IN (' . $validOrderStatusStr .
                '),dp_cart_info.count_price,0)) / sum(dp_cart_info.count_price) as percentage'
            );

        if ($type == 'seller.detail') {
            $shopId = array_get($other, 'shop_id');
            if (!is_numeric($shopId)) {
                throw new AppException('店铺ID错误');
            }
            $this->query->where('dp_goods_info.shopid', $shopId);
        }

        return $this;
    }

    /**
     * @param string $orderType
     * @param string $order
     *
     * @return $this
     */
    protected function handleOrder($orderType, $order)
    {
        $this->query->orderBy($orderType, $order);

        return $this;
    }

    /**
     * 处理时间
     *
     * @param $time
     *
     * @return $this
     */
    protected function handleTime($time)
    {
        if (empty($time)) {
            $limit = [Carbon::now()->subWeeks(2), Carbon::now()];
        } else {
            $limit = [
                Carbon::createFromFormat('Y-m-d H:i:s', $time[0]),
                Carbon::createFromFormat('Y-m-d H:i:s', $time[1]),
            ];
            sort($limit);
        }

        $this->query->where('dp_opder_form.addtime', '>', $limit[0])
                    ->where('dp_opder_form.addtime', '<=', $limit[1]);

        return $this;
    }

    /**
     * 获取数据
     *
     * @param $page
     * @param $size
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function getList($page, $size)
    {
        return $this->query->paginate($size, $this->select, null, $page);
    }
}