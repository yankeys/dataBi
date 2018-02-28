<?php

namespace Zdp\BI\Services\Format;

use Carbon\Carbon;

/**
 * Class Goods
 *
 * @property array  $time   时间限制
 * @property string $group  分组方式
 * @property array  $split  分裂项
 * @property array  select  需要sum的字段
 * @property array  $filter 筛选项
 *
 * @package Zdp\BI\Services\Format
 */
class Goods
{
    /**
     * @var mixed
     */
    protected $option;

    /**
     * @var array
     */
    protected $data;

    /**
     * Goods constructor.
     *
     * @param array $statisticOption
     */
    public function __construct($statisticOption = [])
    {
        $this->option = $this->parseOption($statisticOption);
    }

    protected function parseOption($statisticOption)
    {
        return $statisticOption;
    }

    /**
     * @param $data
     *
     * @return array
     */
    public function format($data)
    {
        $this->data = collect($data);

        return $this->process();
    }

    // 处理数据
    protected function process()
    {
        $time = $this->time;
        $items = $this->data->toArray();
        $times = self::handleTimeGroup($time[0], $time[1], $this->group);
        if (empty($this->split)) {
            $data['全部'] = $this->handleSingleGroup($items['全部'], $times);
        } else {
            $data = [];
            foreach ($items as $key => $item) {
                $data[$key] = $this->handleSingleGroup($item, $times);
            }
        }

        return $data;
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
     * @param array $times 当前存在的时间字段值
     *
     * @return array
     */
    protected function handleSingleGroup($items, $times)
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
                    array_merge(['time' => $time], self::defaultDataValue());
            }
        }

        $items = array_merge($addItem, $items);

        // 对数组进行时间排序
        $reArr = [];
        foreach ($items as $key => $v) {
            $reArr[$key]  = $v['time'];
        }
        array_multisort($reArr, SORT_ASC, $items);

        return $items;
    }

    /**
     * 获取默认填充值
     *
     * @return array
     */
    protected function defaultDataValue()
    {
        $ret = [];

        foreach ($this->select as $select) {
            $ret['total_' . $select] = 0;
        }

        return $ret;
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return array_get($this->option, $name);
    }

    public function __set($name, $value)
    {
        array_set($this->option, $name, $value);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return array_has($this->option, $name);
    }
}