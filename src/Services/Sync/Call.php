<?php

namespace Zdp\BI\Services\Sync;

use App\Models\DpShopInfo;
use App\Models\DpUserConsultLog;
use Zdp\BI\Models\CallLog;

class Call
{
    public function syncAllCall()
    {
        $date = CallLog::orderBy('call_date', 'desc')
                       ->value('call_date');
        $date = $date ?: '2015-01-01';

        $query = DpUserConsultLog
            ::join(
                'dp_shangHuInfo',
                'dp_bodalog.bduid', '=', 'dp_shangHuInfo.shid'
            )->join(
                'dp_shopInfo',
                'dp_shangHuInfo.shopId', '=', 'dp_shopInfo.shopId'
            )->join(
                'dp_goods_info', 'dp_bodalog.bdgid', '=', 'dp_goods_info.id'
            )->join(
                'dp_goods_basic_attributes',
                'dp_goods_info.id', '=', 'dp_goods_basic_attributes.goodsid'
            )->join(
                'dp_goods_types',
                'dp_goods_info.goods_type_id', '=', 'dp_goods_types.id'
            )->join(
                'dp_shopInfo as shop',
                'dp_goods_info.shopid', '=', 'shop.shopId'
            )->join(
                'dp_pianqu as pianqu', 'shop.pianquId', '=', 'pianqu.pianquId'
            );

        if (!empty($date)) {
            $query->where('dp_bodalog.bddate', '>=', $date);
        }

        $query->select([
            'dp_bodalog.bdnum as call_times',
            'dp_bodalog.bddate as call_date',

            'dp_bodalog.bduid as buyer_id',
            'dp_shangHuInfo.xingming as buyer_name',
            'dp_shopInfo.dianPuName as buyer_shop',
            'dp_shopInfo.trenchnum as buyer_type',
            'dp_shopInfo.province as buyer_province',
            'dp_shopInfo.city as buyer_city',
            'dp_shopInfo.county as buyer_district',

            'dp_bodalog.telnumber',

            'dp_bodalog.bdgid as goods_id',
            'dp_goods_info.sortid as goods_type_node',
            'dp_goods_info.gname as goods_name',
            'dp_goods_info.goods_title as goods_title',
            'dp_goods_info.brand as goods_brand',
            'dp_goods_types.sort_name as goods_sort',
            'dp_goods_basic_attributes.goods_price as goods_price',

            'shop.shopId as seller_id',
            'shop.trenchnum as seller_type',
            'shop.dianPuName as seller_name',
            'shop.province as seller_province',
            'shop.city as seller_city',
            'shop.county as seller_district',
            'pianqu.pianqu as seller_market',
        ])->chunk(100, function ($goods) {
            self::sync($goods);
        });
    }

    protected function sync($goods)
    {
        foreach ($goods as $good) {
            $good->buyer_type =
                DpShopInfo::getShopTypeName($good->buyer_type);
            $good->seller_type =
                DpShopInfo::getShopTypeName($good->seller_type);

            $good = $good->toArray();

            foreach ($good as $key => $item) {
                if (empty($item)) {
                    $good[$key] = '未知';
                }
            }

            $update = array_except($good, 'call_times');

            CallLog::updateOrCreate($update, ['call_times'=> $good['call_times']]);
        }
    }
}