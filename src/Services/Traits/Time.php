<?php

namespace Zdp\BI\Services\Traits;

use Carbon\Carbon;

trait Time
{
    /**
     * 根据时间分组方式返回所有时间字符串
     *
     * @param Carbon $start  起始时间
     * @param Carbon $end    结束时间
     * @param string $method 时间分组方式
     *
     * @return array
     */
    public function groupByGiven($start, $end, $method)
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
     * 解析时间(不传入则默认返回当前时间之前七天)
     *
     * @param array|string $time 传入时间
     *
     * @return array
     */
    public function parseTime($time)
    {
        $time = (array)$time;
        if (empty($time)) {
            $tmp = [
                Carbon::now()->subDays(7),
                Carbon::now(),
            ];
        } elseif (count($time) == 1) {
            $tmp = [
                new Carbon($time[0]),
                new Carbon($time[0]),
            ];
        } else {
            $tmp = [
                new Carbon($time[0]),
                new Carbon($time[1]),
            ];
            sort($tmp);
        }

        return $tmp;
    }
}