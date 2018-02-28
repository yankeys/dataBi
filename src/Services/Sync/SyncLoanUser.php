<?php

namespace Zdp\BI\Services\Sync;

use Carbon\Carbon;
use Zdp\BI\Models\LoanUserLog;
use Zdp\Main\Data\Models\DpIousWhiteList;
use Zdp\Main\Data\Models\DpShopInfo;
use Zdp\Main\Data\Services\Areas;

class SyncLoanUser
{
    public function syncLoanUser()
    {
        // 记录日期
        $date = Carbon::yesterday()->toDateString();
        // 开始进行统计的时间
        $startTime = self::getDateTime();
        // 日志表
        DpIousWhiteList::where('updated_at', '>', $startTime)
                       ->select([
                           'shop_id',
                           'province_id',
                           'city_id',
                           'county_id',
                           'pass_date',
                           'first_pay_time',
                           'status',
                       ])
                       ->chunk(50, function ($users) use ($date) {
                           foreach ($users as $user) {
                               $info = [
                                   'shop_id'   => $user->shop_id,
                                   'shop_name' => self::handleName($user->shop_id),
                                   'date'      => $date,
                                   'status'    => self::handleStatus($user->status, $user->first_pay_time),
                                   'province'  => self::handleArea($user->province_id),
                                   'city'      => self::handleArea($user->province_id, $user->city_id),
                                   'district'  => self::handleArea($user->province_id, $user->city_id, $user->county_id),
                               ];
                               // 数据是否已存在。如果已存在则不写入
                               $isExist = LoanUserLog::where('shop_id', $info['shop_id'])
                                                     ->where('status', $info['status'])
                                                     ->first();
                               if(!empty($isExist)){
                                   continue;
                               }
                               LoanUserLog::create($info);
                           }
                       });
    }
    // 获取店铺名字
    protected function handleName($shopId)
    {
        $shopName = DpShopInfo::where('shopId',$shopId)
            ->value('dianPuName');

        return $shopName;
    }

    // 处理地址信息
    protected function handleArea($provinceId, $cityId = null, $countyId = null)
    {
        $areas = Areas::$areas;
        if (empty($cityId) && empty($countyId)) {
            $area = array_get($areas, 'province' . '.' . $provinceId,'无');
        } elseif (!empty($cityId) && empty($countyId)) {
            $area =
                array_get($areas, 'city' . '.' . $provinceId . '.' . $cityId,'无');
        } else {
            $area = array_get($areas, 'county' . '.' . $provinceId . '.' . $cityId . '.' . $countyId);
        }

        return $area;
    }

    /**
     * 判断当前用户状态
     *
     * @param string $status  未开通 已开通 已支付 （已支付状态在已开通之后）
     * @param $firstPayTime
     *
     * @return string
     */
    protected function handleStatus($status, $firstPayTime)
    {
        $status = array_get(DpIousWhiteList::LOAN_STATUS, $status);
        if (!empty($firstPayTime))
        {
            $status = '已支付';
        }
        return $status;
    }

    /**
     * 处理同步的开始时间
     */
    protected function getDateTime()
    {
        $isRecord = LoanUserLog::first();

        if (empty($isRecord)) {
            $time = Carbon::create(2017, 1, 1)->startOfDay();
        }else{
            $time = Carbon::now()->subDays(1);
        }

        return $time;
    }
}