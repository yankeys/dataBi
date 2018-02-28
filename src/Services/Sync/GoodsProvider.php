<?php

namespace Zdp\BI\Services\Sync;

use App\Models\DpGoodsInfo;
use App\Models\DpGoodsType;
use Zdp\Main\Data\Models\DpShopInfo;
use Carbon\Carbon;
use Zdp\BI\Models\SpGoods;
use Zdp\ServiceProvider\Data\Models\Order;
use Zdp\ServiceProvider\Data\Models\OrderGoods;

/**
 * 服务商订单统计同步
 * Class GoodsProvider
 *
 * @package Zdp\BI\Services\Sync
 */
class GoodsProvider
{
    public function syncGoods()
    {
        OrderGoods
            ::leftJoin('orders as o', 'order_goods.order_id', '=', 'o.id')
            ->whereIn('o.status', Order::STATUS_NORMAL)
            ->where('o.updated_at', '>', self::getDatetime())
            ->join('service_providers as sp', 'o.sp_id', '=', 'sp.zdp_user_id')
            ->join('wechat_accounts as wa', 'o.sp_id', '=', 'wa.sp_id')
            ->leftJoin('users as u', 'o.user_id', '=', 'u.id')
            ->leftJoin('area as ap', 'u.province_id', '=', 'ap.id')
            ->leftJoin('area as ac', 'u.city_id', '=', 'ac.id')
            ->leftJoin('area as ad', 'u.county_id', '=', 'ad.id')
            ->leftJoin('shop_type as st', 'u.shop_type_id', '=', 'st.id')
            ->select([
                'order_goods.order_id',
                'order_goods.goods_id',
                'order_goods.goods_info',
                'order_goods.created_at',

                'o.payment as method',
                'o.status as order_status',

                'sp.zdp_user_id as sp_id',
                'sp.shop_name as sp_shop',
                'sp.user_name as sp_name',
                'wa.wechat_name as wechat_account',

                'u.id as user_id',
                'u.user_name as user_name',
                'u.shop_name as user_shop',
                'ap.name as province',
                'ac.name as city',
                'ad.name as district',
                'st.type_name as type',
            ])
            ->chunk(100, function ($goods) {
//                dd($goods->toArray());
                foreach ($goods as $good) {
                    $user = array_except($good->toArray(), ['created_at', 'goods_info']);
                    // 处理商品信息
                    $goodsInfo = json_decode($good->goods_info);
                    $goodInfo = self::formatInfo($goodsInfo);
                    $data = array_merge($user, $goodInfo);
                    $shopInfo = self::getShop($good->goods_id);
                    $data['date'] = $good->created_at->toDateString();
                    $data['shop_type'] = DpShopInfo::getShopTypeName($shopInfo->shop_type);
                    $data = array_merge($data, array_except($shopInfo->toArray(), 'shop_type'));
//                    dd($data);
                    $orderId = $data['order_id'];
                    $goodsId = $data['goods_id'];
                    $spId = $data['sp_id'] ?: 0;
                    $date = $data['date'];
                    $userId = $data['user_id'] ?: 0;
                    $update = array_except($data, ['order_id', 'goods_id', 'sp_id', 'date', 'user_id']);
                    $update['sp_name'] = $data['sp_name'] ?: '已删除';
                    $update['sp_shop'] = $data['sp_shop'] ?: '已删除';
                    $update['wechat_account'] = $data['wechat_account'] ?: '已删除';
                    $update['user_name'] = $data['user_name'] ?: '已删除';
                    $update['user_shop'] = $data['user_shop'] ?: '已删除';
                    $update['province'] = $data['province'] ?: '已删除';
                    $update['city'] = $data['city'] ?: '已删除';
                    $update['type'] = $data['type'] ?: '已删除';
                    // 写入数据库
                    SpGoods::updateOrCreate(
                        [
                            'order_id' => $orderId,
                            'goods_id' => $goodsId,
                            'sp_id' => $spId,
                            'date' => $date,
                            'user_id' => $userId
                        ], $update);
                }
            });
    }

    // 处理商品信息
    protected function formatInfo($goodsInfo)
    {
        return [
            'goods_name'  => $goodsInfo->gname,
            'goods_brand' => $goodsInfo->brand,
            'goods_num'   => $goodsInfo->buy_num,
            'goods_price' => $goodsInfo->goods_price,
            'goods_sort'  => DpGoodsType::where('id', $goodsInfo->sortid)
                                        ->value('sort_name'),
        ];
    }

    protected function getShop($goodsId)
    {
        $shop = DpGoodsInfo
            ::leftJoin('dp_shopInfo as dsi', 'dp_goods_info.shopid', '=', 'dsi.shopId')
            ->leftJoin('dp_pianqu as dp', 'dsi.pianquId', '=', 'dp.pianquId')
            ->where('dp_goods_info.id', $goodsId)
            ->select([
                'dp_goods_info.gname as goods_name',
                'dp_goods_info.sortid as goods_type_node',

                'dsi.shopId as shop_id',
                'dsi.trenchnum as shop_type',
                'dsi.dianPuName as shop_name',
                'dsi.province as shop_province',
                'dsi.city as shop_city',
                'dsi.county as shop_district',

                'dp.pianqu as shop_market',
            ])
            ->first();

        return $shop;
    }

    /**
     * 获取同步命令的开始时间
     */
    protected function getDatetime()
    {
        $log = SpGoods::orderBy('order_id', 'desc')
                      ->first();

        if ($log) {
            $time = Order::where('id', $log->order_id)
                         ->value('created_at');
        } else {
            $time = Carbon::create(2017, 1, 1)->startOfDay();
        }

        return $time;
    }
}