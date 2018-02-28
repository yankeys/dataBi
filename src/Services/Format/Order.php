<?php

namespace Zdp\BI\Services\Format;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class Order
 *
 * @property array  $time   时间限制
 * @property string $group  分组方式
 * @property array  $split  分裂项
 * @property array  select  需要sum的字段
 * @property array  $filter 筛选项
 *
 * @package Zdp\BI\Services\Format
 */
class Order
{
    protected $option;

    /**
     * @var Collection
     */
    protected $data;

    /**
     * @var array 出现的标签项组别
     */
    protected $splitGroup;

    /**
     * @var array 非标签项字段名
     */
    protected $noneSplitFieldNames = ['time', 'total_num', 'total_price'];

    /**
     * @var array Data Field
     */
    protected $dataFieldNames = ['total_num', 'total_price'];

    protected $hidden = ['time'];

    public function __construct($statisticOption = [])
    {
        $this->option = $this->parseOption($statisticOption);
    }

    protected function parseOption($statisticOption)
    {
        return $statisticOption;
    }

    /**
     * 格式化数据
     *
     * @param Collection $data
     *
     * @return array
     */
    public function format(Collection $data)
    {
        $this->data = $data->toBase();

        $this->process();

        return $this->data->toArray();
    }

    /**
     * 处理数据
     */
    protected function process()
    {
        if ($this->group != 'none') {
            $this->parseSplitGroup();
            $this->data = $this->data->groupBy('time');
            $this->fillGroup();
        } else {
            $this->data = $this->handleSingleGroup($this->data);
        }
    }

    /**
     * 处理传入数据中的非数据项, 以准备填充空白数据
     */
    protected function parseSplitGroup()
    {
        $this->splitGroup = [];

        // 筛选分组项, 生成键值 并检查组中是否有此键, 没有则加入组中
        foreach ($this->data as $model) {
            $values = $model->toArray();

            $splitKeys = array_filter($values, function ($key) {
                return array_search($key, $this->noneSplitFieldNames) === false;
            }, ARRAY_FILTER_USE_KEY);

            if (empty($splitKeys)) {
                $key = "全部";
            } else {
                ksort($splitKeys);
                $key = implode('-', array_values($splitKeys));
            }

            if (!key_exists($key, $this->splitGroup)) {
                $this->splitGroup[$key] = $splitKeys;
            }
        }

        ksort($this->splitGroup);
    }

    /**
     * @param Collection $group
     * @param string     $time
     *
     * @return Collection
     */
    protected function handleSingleGroup($group, $time = null)
    {
        $items = $group->keyBy(function ($model) {
            $values = $model->toArray();

            $splitKeys = array_filter($values, function ($key) {
                return array_search($key, $this->noneSplitFieldNames) === false;
            }, ARRAY_FILTER_USE_KEY);

            if (empty($splitKeys)) {
                return "全部";
            }

            ksort($splitKeys);

            return implode('-', array_values($splitKeys));
        });

        if ($this->group == 'none') {
            return $items;
        }

        // 填充分组
        $missingSplitGroup = array_diff_key($this->splitGroup, $items->all());
        if (!empty($missingSplitGroup)) {
            foreach ($missingSplitGroup as $key => $val) {
                if (!empty($time)) {
                    $val['time'] = $time;
                }
                $items->put($key, array_merge($val, $this->defaultDataValue()));
            }
        }

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

        if (array_search('price', $this->select) !== false) {
            $ret['total_price'] = '0.00';
        }

        if (array_search('num', $this->select) !== false) {
            $ret['total_num'] = '0';
        }

        return $ret;
    }

    /**
     * 填充数据项为0的分组
     */
    protected function fillGroup()
    {
        /**
         * @var Carbon $start
         * @var Carbon $end
         */
        list($start, $end) = $this->time;

        switch ($this->group) {
            case 'minute':
                $format = 'Y-m-d H:i';
                $func = 'addMinute';
                break;

            case 'hour':
                $format = 'Y-m-d G点';
                $func = 'addHour';
                break;

            case 'week':
                $format = 'Y年 第W周';
                $func = 'addWeek';
                break;

            case 'month':
                $format = 'Y年m月';
                $func = 'addMonth';
                break;

            case 'year':
                $format = 'Y年';
                $func = 'addYear';
                break;

            case 'day':
            default:
                $format = 'Y-m-d';
                $func = 'addDay';
                break;
        }

        for ($i = $start; $i <= $end; call_user_func([$i, $func])) {
            $time = $i->format($format);

            $item = $this->data->get($time, new Collection());

            $this->data->put($time, $this->handleSingleGroup($item, $time));
        }

        $items = $this->data->all();

        ksort($items);

        $this->data = new Collection($items);
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