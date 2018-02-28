<?php

namespace Zdp\BI\Services\Format;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Zdp\ServiceProvider\Data\Models\ServiceProvider;

class FormatForSp
{
    /**
     * @param $time
     * @param $group
     * @param $split
     * @param $select
     * @param array $data
     * @return array
     */
    public function format($time, $group, $split, $select, $data)
    {
        // 获取所有时间数据
        $timeInfo = self::handleTimeGroup($time[0], $time[1], $group);
        if (array_key_exists('sp_id', (array)$split) && count($split) == 1) {

            $serviceProviders = ServiceProvider
                ::query()
                ->where('status', ServiceProvider::PASS)
                ->get()
                ->keyBy('zdp_user_id');

            $newData = [];
            foreach ($serviceProviders as $spId => $sp) {
                $spName = $sp->shop_name;
                $spData = array_get($data, $spId);
                if (empty($spData)) {
                    $newData[$spName] = [];
                } else {
                    $newData[$spName] = $spData;
                }
            }
            $data = $newData;
        }
        $reArr = [];
        if (empty($data) || empty($split)) {
            $reArr = $this->handleSingleGroup($data, $timeInfo, $select, $split);
        } else {
            foreach ($data as $key => $value) {
                $reArr[$key] = $this->handleSingleGroup($value, $timeInfo, $select, $split);
            }
        }
        return $reArr;
    }

    /**
     * 根据method返回时间段内所有的时间数据
     *
     * @param Carbon $start 开始时间
     * @param Carbon $end 结束时间
     * @param string $method 数据类型
     *
     * @return array
     */
    protected function handleTimeGroup($start, $end, $method)
    {
        switch ($method) {
            case 'week':
                $format = 'Y-W';
                $func = 'addWeek';
                break;

            case 'month':
                $format = 'Y-m';
                $func = 'addMonth';
                break;

            case 'year':
                $format = 'Y';
                $func = 'addYear';
                break;

            case 'day':
            default:
                $format = 'Y-m-d';
                $func = 'addDay';
                break;
        }

        $times = [];
        for ($i = $start; $i <= $end; call_user_func([$i, $func])) {
            $time = $i->format($format);
            $times[] = $time;
        }

        return $times;
    }

    /**
     * 单个切割项内缺失数据的填充
     *
     * @param array $items
     * @param array $times 当前存在的时间字段值
     * @param       $select
     *
     * @return array
     */
    protected function handleSingleGroup($items, $times, $select, $split)
    {
        $dates = [];
        foreach ($items as $item) {
            $dates[] = $item['time'];
        }
        // 当前时间内所有的分组时间值
        $addItem = [];
        foreach ($times as $time) {
            // 返回字段中是否存在此时间内的信息
            if (!in_array($time, $dates)) {
                //填充数据
                $addItem[] =
                    array_merge(['time' => $time], self::defaultDataValue($select));
            }
        }

        $items = array_merge($addItem, $items);
        // 对数组进行时间排序
        $reArr = [];
        foreach ($items as $key => $v) {
            $reArr[$key] = $v['time'];
        }
        array_multisort($reArr, SORT_ASC, $items);
        if(empty($dates)&&!empty($split)){
            $items = array_merge($items,[
                ['time' => '合计','number' => 0],
                ['time' => '总计','number' => 0]
            ]);
        }
        return $items;
    }

    /**
     * 获取默认填充值
     *
     * @param array $selects
     *
     * @return array
     */
    protected function defaultDataValue($selects)
    {
        $ret = [];

        foreach ($selects as $select) {
            $ret[$select] = 0;
        }

        return $ret;
    }
}