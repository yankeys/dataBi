<?php

namespace Zdp\BI\Services\Format;

use Carbon\Carbon;

class Call
{
    /**
     * 格式化前台咨询数据
     *
     * @param array  $time  当前筛选时间段
     * @param string $group 当前时间分组方式
     * @param array  $data  需要格式化的数据
     *
     * @return array
     */
    public function format($time, $group, $data)
    {
        // 获取所有时间数据
        $timeInfo = self::handleTimeGroup($time[0], $time[1], $group);
        $reArr = [];
        // 获取单个items
        foreach ($data as $key => $value) {
            $reArr[$key] = [
                'group'       => $this->handleSingleGroup($value['group'], $timeInfo),
                'total_times' => $value['total_times'],
                'total_buyer' => $value['total_buyer'],
                'total_goods' => $value['total_goods'],
            ];
        }

        return $reArr;
    }

    /**
     * 根据method返回时间段内所有的时间数据
     *
     * @param Carbon $start  开始时间
     * @param Carbon $end    结束时间
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
     * @param array $timeInfo 当前存在的时间字段值
     *
     * @return array
     */
    protected function handleSingleGroup($items, $timeInfo)
    {
        $dates = [];
        foreach ($items as $item) {
            $dates[] = $item->time;
        }
        // 当前时间内所有的分组时间值
        $addItem = [];
        foreach ($timeInfo as $time) {
            // 返回字段中是否存在此时间内的信息
            if (!in_array($time, $dates)) {
                //填充数据
                $addItem[] = array_merge([
                    'call_times'   => 0,
                    'buyer_times'  => 0,
                    'seller_times' => 0,
                ],
                    ['time' => $time]);
            }
        }

        $items = array_merge($addItem, $items->toArray());

        // 对数组进行时间排序
        $reArr = [];
        foreach ($items as $key => $v) {
            $reArr[$key] = $v['time'];
        }
        array_multisort($reArr, SORT_ASC, $items);

        return $items;
    }
}