<?php

namespace Zdp\BI\Services\Sync;

use Carbon\Carbon;
use Zdp\BI\Models\LoanSyncOrder;
use Zdp\Main\Data\Models\DpIousPayDetail;
use Zdp\Main\Data\Models\DpShopInfo;
use Zdp\Main\Data\Services\Areas;

class SyncLoanPayment
{
    /**
     * 同步数据
     */
    public function syncLoanPayment()
    {
        //
        DpIousPayDetail::where('updated_at', '>', self::getDateTime())
                       ->whereNotIn('status', [DpIousPayDetail::FAILURE, DpIousPayDetail::CANCEL])
                       ->select([
                           'id',
                           'shop_id',
                           'actual_amount',
                           'province_id',
                           'city_id',
                           'county_id',
                           'created_at',
                       ])
                       ->chunk(50, function ($pays) {
                           foreach ($pays as $pay) {
                               $info = ['ious_id'   => $pay->id];
                               $update = [
                                   'amount'    => $pay->actual_amount,
                                   'shop_id'   => $pay->shop_id,
                                   'shop_name' => self::handleName($pay->shop_id),
                                   'date'      => $pay->created_at->toDataString(),
                                   'province'  => self::handleArea($pay->province_id),
                                   'city'      => self::handleArea($pay->province_id, $pay->city_id),
                                   'district'  => self::handleArea($pay->province_id, $pay->city_id, $pay->county_id),
                               ];
                               LoanSyncOrder::updateOrCreate($info, $update);
                           }
                       });
    }

    // 获取店铺名字
    protected function handleName($shopId)
    {
        $shopName = DpShopInfo::where('shopId', $shopId)
                              ->value('dianPuName');

        return $shopName;
    }

    // 处理地址信息
    protected function handleArea($provinceId, $cityId = null, $countyId = null)
    {
        $areas = Areas::$areas;
        if (empty($cityId) && empty($countyId)) {
            $area = array_get($areas, 'province' . '.' . $provinceId, '无');
        } elseif (!empty($cityId) && empty($countyId)) {
            $area =
                array_get($areas,
                    'city' . '.' . $provinceId . '.' . $cityId, '无');
        } else {
            $area = array_get($areas,
                'county' . '.' . $provinceId . '.' . $cityId . '.' .
                $countyId);
        }

        return $area;
    }

    /**
     * 处理同步的开始时间
     */
    protected function getDateTime()
    {
        $id = LoanSyncOrder::orderBy('ious_id', 'desc')
                           ->value('ious_id');
        $time = DpIousPayDetail::where('id', $id)
                               ->value('created_at');

        if (!$time) {
            $time = Carbon::create(2017, 1, 1)->startOfDay();
        }

        return $time;
    }
}