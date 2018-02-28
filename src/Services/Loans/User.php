<?php

namespace Zdp\BI\Services\Loans;

use Zdp\BI\Models\LoanUserLog;
use Zdp\BI\Services\Traits\Time;
use Zdp\Main\Data\Models\DpIousWhiteList;
use Zdp\Main\Data\Services\Areas;

class User
{
    use Time;

    protected $defaultTotalFilter = [
        'province_id',
        'city_id',
        'county_id',
    ];
    protected $defaultTrendFilter = [
        'province',
        'city',
        'county',
    ];
    protected $timeGroup          = [
        'day',
        'week',
        'month',
        'year',
        'none',
    ];


    /**
     * 个状态的人数统计
     *
     * @param array|null $time
     * @param array|null $filter
     *
     * @return array
     */
    public function total(
        array $time = null,
        array $filter = null
    ) {
        $this->time = $this->parseTime($time);
        $this->query = DpIousWhiteList::query();
        $this->parseFilter($filter, $this->defaultTotalFilter)
             ->handleTotalTime()
             ->handleTotalFilter();
        // 综合统计项
        $reData = [];
        $reData['white_list'] = $this->query->count();
        $openQuery = clone $this->query;
        $reData['opened'] = $openQuery->where('status', DpIousWhiteList::PASS)
                                      ->count();
        $reData['paid'] = $this->query->whereNotNull('first_pay_time')->count();

        return $reData;
    }

    // 解析传入的筛选项
    protected function parseFilter($filter, $defaultFilter)
    {
        $tmp = [];

        if (!empty($filter)) {
            $filter = array_filter($filter, function ($key) use ($filter) {
                return $filter[$key] != null;
            }, ARRAY_FILTER_USE_KEY);
            foreach ($filter as $name => $val) {
                if (in_array($name, $defaultFilter)) {
                    $tmp[] = [$name => $val];
                }
            }
        }
        $this->filter = $tmp;

        return $this;
    }

    // 处理输入的时间
    protected function handleTotalTime()
    {
        $this->query->where('created_at', '>', $this->time[0])
                    ->where('created_at', '<', $this->time[1]);

        return $this;
    }

    // 处理筛选项
    protected function handleTotalFilter()
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

    /**
     * 冻品贷用户趋势
     *
     * @param array|null $time
     * @param array|null $filter
     * @param null       $group
     *
     * @return mixed
     */
    public function trend(
        array $time = null,
        array $filter = null,
        $group = null
    ) {
        $this->time = $this->parseTime($time);
        $this->query = LoanUserLog::query();

        $this->parseGroup($group)
             ->parseFilter($filter, $this->defaultTrendFilter)
             ->handleTrendTime()
             ->handleFilter()
             ->handleGroup();
        $reData['white_list'] = $this->query
            ->selectRaw('COUNT("id") as `num`')
            ->get()
            ->toArray();
        $openQuery = clone $this->query;
        $reData['opened'] = $openQuery->whereIn('status', ['已开通', '已支付'])
                                      ->selectRaw('COUNT("id") as `num`')
                                      ->get()
                                      ->toArray();
        $reData['paid'] = $this->query->where('status', '已支付')
                                      ->selectRaw('COUNT("id") as `num`')
                                      ->get()
                                      ->toArray();
        // 格式化数据
        $reData = self::formatForTrend($reData);

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

    protected function handleTrendTime()
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

    // 解析时间
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
            $this->query
                ->selectRaw(
                    'DATE_FORMAT(`date`, ?) as `time`',
                    [$format]
                )
                ->groupBy('time');
        }

        return $this;
    }

    // 格式化数据
    protected function formatForTrend($datas)
    {
        // 获取所有的时间数据
        $timeInfo =
            $this->groupByGiven($this->time[0], $this->time[1], $this->group);
        $reArr = [];
        foreach ($datas as $key => $item) {
            if (empty($item)) {
                $reArr[$key] = $this->handleSingleGroup([], $timeInfo, ['num']);
            } else {
                $reArr[$key] =
                    $this->handleSingleGroup($item, $timeInfo, ['num']);
            }
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
     * 用户分布统计
     *
     * @param array|null $time
     * @param array|null $column
     *
     * @return array
     *
     */
    public function layout(
        array $time = null,
        array $column = null,
        $orderBy
    ) {
        $this->time = $this->parseTime($time);
        $this->query = DpIousWhiteList::query();
        $this->handleTotalTime()
             ->handleColumn($column);
        // 综合统计项
        $reData = [];
        foreach ($this->areaInfo as $key => $value) {
            $this->areaQuery = clone $this->query;
            $this->areaQuery->where($this->limit, $key);
            $queryData['white_list'] = $this->areaQuery->count();
            $openQuery = clone $this->areaQuery;
            $queryData['opened'] =
                $openQuery->where('status', DpIousWhiteList::PASS)->count();
            $queryData['paid'] =
                $this->areaQuery->whereNotNull('first_pay_time')->count();

            $reData[$value] = $queryData;
        }
        // 对结果进行排序
        $reArr = [];
        foreach ($reData as $key => $v) {
            $reArr[$key] = $v[$orderBy];
        }
        array_multisort($reArr, SORT_DESC, $reData);

        return $reData;
    }

    protected function handleColumn($column)
    {
        $areas = Areas::$areas;
        $hasProvince = array_get($column, 'province_id');
        $hasCity = array_get($column, 'city_id');
        if (!empty($hasProvince) && is_null($hasCity)) {
            // 返回省下面所有市
            $this->areaInfo = array_get($areas, 'city.' . $column['province_id']);
            $this->query->where('province_id', $column['province_id']);
            $this->limit = 'city_id';
        } elseif (!empty($hasProvince) && !is_null($hasCity)) {
            // 返回所有区县
            $this->areaInfo = array_get($areas,
                'county.' . $column['province_id'] . '.' . $column['city_id']);
            $this->query->where('province_id', $column['province_id'])
                        ->where('city_id', $column['city_id']);
            $this->limit = 'county_id';
        } else {
            // 返回所有省
            $this->areaInfo = array_get($areas, 'province');
            $this->limit = 'province_id';
        }

        return $this;
    }
}