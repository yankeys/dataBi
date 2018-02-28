<?php

namespace Zdp\BI\Services\Loans;

use Zdp\BI\Services\Traits\Time;
use Zdp\Main\Data\Models\DpIousWhiteList;
use Zdp\Main\Data\Services\Areas;

class Filter
{
    use Time;

    /**
     * 获取筛选项
     *
     * @param array|null $time
     *
     * @return array
     */
    public function filter(
        array $time = null
    ) {
        $this->time = $this->parseTime($time);
        $this->query = DpIousWhiteList::query();
        $this->handleTime();
        $reArr = $this->query->select('province_id')
                             ->distinct('province_id')
                             ->get()
                             ->toArray();
        // 将所获得数据格式化，返回省名字
         $reArr = self::formatArea($reArr);

        return $reArr;
    }

    // 处理时间
    protected function handleTime()
    {
        $this->query->where('created_at', '>', $this->time[0])
                    ->where('created_at', '<', $this->time[1]);

        return $this;
    }

    // 格式化返回的省结构
    protected function formatArea($provinces)
    {
        $areas = Areas::$areas;
        $provinceData = $areas['province'];
        $reProvince = [];
        array_map(function ($province) use (&$reProvince, $provinceData) {
            $name = array_get($provinceData, $province['province_id']);
            if ($name) {
                $reProvince[$province['province_id']] = $name;
            }
        }, $provinces);

        return $reProvince;
    }
}