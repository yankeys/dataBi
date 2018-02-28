<?php

namespace Zdp\BI\Services\Loans;

use Zdp\BI\Models\LoanSyncOrder;
use Zdp\BI\Services\Traits\Time;

class Payment
{
    use Time;

    protected $defaultFilter = [
        'province',
        'city',
        'county',
    ];

    protected $timeGroup = [
        'day',
        'week',
        'month',
        'year',
        'none',
    ];

    /**
     * 支付趋势
     *
     * @param array|null $time
     * @param array|null $filter
     * @param            $show
     *
     * @param            $group
     *
     * @return array
     */
    public function trend(
        array $time = null,
        array $filter = null,
        $show,
        $group
    ) {
        $this->time = $this->parseTime($time);
        $this->query = LoanSyncOrder::query();
        $this->parseFilter($filter)
             ->parseGroup($group)
             ->handleShow($show)
             ->handleTime()
             ->handleGroup()
             ->handleFilter();

        $reData = $this->query->get()->toArray();
        // 格式化数据
        $reData = self::formatTrend($reData);

        return $reData;
    }

    // 解析时间分组项
    protected function parseGroup($group)
    {
        if (in_array($group, $this->timeGroup) !== false) {
            $this->group = $group;
        } else {
            $this->group = 'day';
        }

        return $this;
    }

    // 解析筛选项
    protected function parseFilter($filter)
    {
        $tmp = [];

        if (!empty($filter)) {
            $filter = array_filter($filter, function ($key) use ($filter) {
                return $filter[$key] != null;
            }, ARRAY_FILTER_USE_KEY);
            foreach ($filter as $name => $val) {
                if (in_array($name, $this->defaultFilter)) {
                    $tmp[] = [$name => $val];
                }
            }
        }
        $this->filter = $tmp;

        return $this;
    }

    // 处理展示项
    protected function handleShow($show)
    {
        switch ($show) {
            case 'pay_num':
                $this->query->selectRaw('COUNT(`id`) AS `value`');
                break;
            case 'amount':
                $this->query->selectRaw('SUM(`amount`) AS `value`');
                break;
            default:
                $this->query->selectRaw('COUNT(DISTINCT `shop_id`) `value`');
                break;
        }

        return $this;
    }

    // 处理时间
    protected function handleTime()
    {
        $this->query->where('date', '>', $this->time[0])
                    ->where('date', '<', $this->time[1]);

        return $this;
    }

    // 处理筛选项
    protected function handleFilter()
    {
        foreach ($this->filter as $filter) {
            foreach ($filter as $name => $value) {
                $this->handleFilterItem($name, $value);
            }
        }

        return $this;
    }

    // 处理筛选项单元
    protected function handleFilterItem($name, $value)
    {
        if (empty($value)) {
            return;
        }
        $this->query->whereIn($name, $value);
    }

    // 处理时间
    protected function handleGroup()
    {
        switch ($this->group) {
            case 'day':
                $format = '%Y-%m-%d';
                break;

            case 'week':
                $format = '%x-%v';
                break;

            case 'month':
                $format = '%Y-%m';
                break;

            case 'year':
                $format = '%Y';
                break;

            case 'none':
                break;
        }

        if (!empty($format)) {
            $this->query->selectRaw('DATE_FORMAT(`date`, ?) as `time`',
                [$format])
                        ->groupBy('time');
        }

        return $this;
    }

    // 格式化取得的数据
    protected function formatTrend($data)
    {
        // 获取所有的时间数据
        $timeInfo =
            $this->groupByGiven($this->time[0], $this->time[1], $this->group);
        if (empty($data)) {
            $reArr = $this->handleSingleGroup([], $timeInfo, ['value']);
        } else {
            $reArr = $this->handleSingleGroup($data, $timeInfo, ['value']);
        }

        return $reArr;
    }

    // 单个切割项内缺失数据的填充
    protected function handleSingleGroup($items, $times, $select)
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
                    array_merge(['time' => $time],
                        self::defaultDataValue($select));
            }
        }

        $items = array_merge($addItem, $items);

        // 对数组进行时间排序
        $reArr = [];
        foreach ($items as $key => $v) {
            $reArr[$key] = $v['time'];
        }
        array_multisort($reArr, SORT_ASC, $items);

        return $items;
    }

    // 获取默认填充值
    protected function defaultDataValue($selects)
    {
        $ret = [];

        foreach ($selects as $select) {
            $ret[$select] = 0;
        }

        return $ret;
    }

    /**
     * 冻品贷支付分布
     *
     * @param array|null $time
     * @param array|null $filter
     * @param string     $orderBy
     *
     * @return array
     */
    public function layout(
        array $time = null,
        array $filter = null,
        $orderBy
    ) {
        $this->time = $this->parseTime($time);
        $this->query = LoanSyncOrder::query();
        $this->parseFilter($filter)
             ->handleTime()
             ->handleFilter();
        $reData = [];
        // 处理总共数据
        $totalQuery = clone $this->query;
        $totalData =
            $totalQuery->selectRaw('COUNT(DISTINCT `shop_id`) `shop_num`')
                       ->selectRaw('COUNT(`id`) AS `pay_num`')
                       ->selectRaw('SUM(`amount`) AS `amount`')
                       ->first();
        $reData['total']['shop_num'] = $totalData->shop_num;
        $reData['total']['pay_num'] = $totalData->pay_num;
        $reData['total']['amount'] = $totalData->amount;
        // 根据筛选项处理区域限制数据
        $this->handleLimit($filter);
        // 获取详细数据
        $reData['detail'] = self::getDetail($orderBy);

        return $reData;
    }

    // 根据筛选项处理区域限制数据
    protected function handleLimit($filter)
    {
        $hasProvince = array_get($filter, 'province');
        $hasCity = array_get($filter, 'city');
        if (!empty($hasProvince) && empty($hasCity)) {
            // 返回省下面所有市
            $areaQuery = clone $this->query;
            $this->areaInfo = $areaQuery->whereIn('province', $hasProvince)
                                        ->selectRaw('DISTINCT `city` AS `city`')
                                        ->get()
                                        ->toArray();
            $this->query->whereIn('province', $hasProvince);
        } elseif (!empty($hasProvince) && !empty($hasCity)) {
            // 返回所有区县
            $areaQuery = clone $this->query;
            $this->areaInfo = $areaQuery->whereIn('province', $hasProvince)
                                        ->whereIn('city', $hasCity)
                                        ->selectRaw('DISTINCT `district` AS `district`')
                                        ->get()
                                        ->toArray();;
            $this->query->whereIn('province', $hasProvince)
                        ->whereIn('city', $hasCity);
        } else {
            $areaQuery = clone $this->query;
            $this->areaInfo =
                $areaQuery->selectRaw('DISTINCT `province` AS `province`')
                          ->get()
                          ->toArray();
        }

        return $this;
    }

    // 综合统计项
    protected function getDetail($orderBy)
    {
        $reData = [];
        foreach ($this->areaInfo as $areaInfo) {
            foreach ($areaInfo as $key => $value) {
                $areaQuery = clone $this->query;
                $areaData = $areaQuery
                    ->where($key, $value)
                    ->selectRaw('COUNT(DISTINCT `shop_id`) `shop_num`')
                    ->selectRaw('COUNT(`id`) AS `pay_num`')
                    ->selectRaw('SUM(`amount`) AS `amount`')
                    ->first();
                $queryData['shop_num'] = $areaData->shop_num;
                $queryData['pay_num'] = $areaData->pay_num;
                $queryData['amount'] = $areaData->amount;

                $reData[$value] = $queryData;
            }
        }
        // 对结果进行排序
        $reArr = [];
        foreach ($reData as $key => $v) {
            $reArr[$key] = $v[$orderBy];
        }
        array_multisort($reArr, SORT_DESC, $reData);

        return $reData;
    }
}