<?php

namespace Zdp\BI\Services\ServiceProvider;

use Carbon\Carbon;
use Zdp\BI\Models\SpCustomer;
use Zdp\BI\Models\SpGoods;
use Zdp\ServiceProvider\Data\Models\ServiceProvider;

/**
 * Class StatsFilter
 * 获取统计筛选项
 *
 * @package Zdp\BI\Services\ServiceProvider
 * @property array  $time            日期限制
 * @property array  $filters         筛选项
 * @property array  $currentFilter   当前筛选项
 * @property object $query           需要查询的model
 */
class StatsFilter
{
    protected $customerFilter = [
        'province',
        'city',
        'district',
        'type',
        'sp_id',
        'sp_name',
        'sp_shop',
        'wechat_account',
    ];
    protected $orderFilter    = [
        'province',
        'city',
        'district',
        'type',
        'sp_id',
        'sp_name',
        'sp_shop',
        'wechat_account',

        'goods_brand',
        'goods_sort',
        'user_name',
        'user_shop',

        'shop_type',
        'shop_name',
        'shop_province',
        'shop_city',
        'shop_district',
        'shop_market',
    ];

    public function filter(
        $project,
        array $time = null,
        array $filter = null
    ) {
        $this->parseTime($time)
             ->parseProject($project)
             ->parseFilter($filter);

        $this->handleTime();

        $query = $this->query;

        $filters = [];

        foreach ($this->filters as $value) {

            $filters = array_add(
                $filters,
                $value,
                $this->querySingleFilter(clone $query, $value)
            );
        }

        return $filters;
    }

    protected function parseTime(array $time = null)
    {
        if (empty($time)) {
            $this->time = [
                Carbon::now()->subDay(7),
                Carbon::now(),
            ];
        } elseif (count($time) == 1) {
            $this->time = [
                new Carbon($time[0]),
                new Carbon($time[0]),
            ];
        } else {
            $tmp = [
                new Carbon($time[0]),
                new Carbon($time[1]),
            ];
            sort($tmp);
            $this->time = $tmp;
        }

        return $this;
    }

    // 处理需要筛选项
    protected function parseFilter(array $filter = null)
    {
        if (empty($filter)) {
            $this->filters = $this->currentFilter;
        } else {
            $tmp = [];
            foreach ($this->customerFilter as $val) {
                if (in_array($val, $filter)) {
                    $tmp[] = $val;
                }
            }
            $this->filters = $tmp;
        }

        return $this;
    }

    // 处理查询项和筛选项
    protected function parseProject($project)
    {
        switch ($project) {
            case 'customer':
                $this->query = SpCustomer::query();
                $this->currentFilter = $this->customerFilter;
                break;
            case 'else':
                $this->query = SpGoods::query();
                $this->currentFilter = $this->orderFilter;
                break;
        }

        return $this;
    }

    protected function handleTime()
    {
        list($start, $end) = $this->time;

        $this->query->where('date', '>=', $start)
                    ->where('date', '<=', $end);

        return $this;
    }

    protected function querySingleFilter($query, $value)
    {
        $data = $query->selectRaw('DISTINCT `' . $value . '`')
                      ->lists($value)
                      ->all();

        return $data;
    }
}