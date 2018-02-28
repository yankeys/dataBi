<?php

namespace Zdp\BI\Services;

use Carbon\Carbon;
use Zdp\BI\Models\CallLog;
use Zdp\BI\Services\Traits\Time;

/**
 * Class Order
 *
 * @package Zdp\BI\Services
 *
 * @property array  $time   日期限制
 * @property string $group  分组方式
 * @property array  $split  分裂项
 * @property array  select  需要sum的字段
 * @property array  $filter 筛选项
 */
class Call
{
    use Time;

    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $statisticQuery;

    protected $statisticOption = [];

    protected $optionFilter = [
        'goods_sort',
        'goods_brand',

        'buyer_type',
        'buyer_name',
        'buyer_province',
        'buyer_city',
        'buyer_district',

        'seller_type',
        'seller_name',
        'seller_province',
        'seller_city',
        'seller_district',
        'seller_market',
    ];

    protected $optionSplit = [
        'goods_sort',
        'goods_brand',

        'buyer_type',
        'buyer_name',
        'buyer_province',
        'buyer_city',
        'buyer_district',

        'seller_type',
        'seller_name',
        'seller_province',
        'seller_city',
        'seller_district',
        'seller_market',
    ];

    protected $acceptGroup = [
        'day',
        'week',
        'month',
        'year',
        'none',
    ];

    /**
     * 获取商品筛选项
     *
     * @param array|null $time
     * @param array|null $filter
     *
     * @return array
     */
    public function filter(
        array $time = null,
        array $filter = null
    ) {
        $this->parseFilterTime($time)
             ->parseFilter($filter);

        $this->statisticQuery = CallLog::query();

        $this->handleTime();

        $query = $this->statisticQuery;

        $filters = [];

        foreach ($this->filter as $value) {
            $filters = array_add(
                $filters,
                $value,
                $this->querySingleFilter(clone $query, $value)
            );
        }

        return $filters;
    }

    protected function querySingleFilter($query, $name)
    {
        $data = $query->selectRaw('DISTINCT `' . $name . '`')
                      ->lists($name)
                      ->all();

        return $data;
    }

    /**
     * 处理时间限制参数
     *
     * @param array|null $time
     *
     * @return $this
     */
    protected function parseFilterTime(array $time = null)
    {
        if (empty($time)) {
            $this->time = [
                Carbon::now()->subDays(7),
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

    /**
     * @param array|null $filter
     *
     * @return $this
     */
    protected function parseFilter(array $filter = null)
    {
        if (empty($filter)) {
            $this->filter = $this->optionFilter;
        } else {
            $tmp = [];
            foreach ($this->optionFilter as $val) {
                if (in_array($val, $filter)) {
                    $tmp[] = $val;
                }
            }
            $this->filter = $tmp;
        }

        return $this;
    }

    /**
     * 处理时间限制
     *
     * @return $this
     */
    protected function handleTime()
    {
        list($start, $end) = $this->time;

        $this->statisticQuery->where('call_date', '>=', $start)
                             ->where('call_date', '<=', $end);

        return $this;
    }

    /**
     * 处理排名接口时间限制
     *
     * @return $this
     */
    protected function handleRankTime()
    {
        list($start, $end) = $this->time;

        $this->query->where('call_date', '>=', $start)
                    ->where('call_date', '<=', $end);

        return $this;
    }

    /**
     * 根据传入的省市信息获取下级区域信息
     *
     * @param $name
     * @param $role
     * @param $type
     *
     * @return array
     */
    public function series($name, $role, $type)
    {
        $column = 'seller_market';
        if ($type == 'area')
        {
            $column = $this->parseSelect($role);
        }
        $reData = CallLog::where($role, $name)
               ->selectRaw('DISTINCT(' . $column . ') AS `' . $column . '`')
               ->get()->toArray();
        $reData = array_filter($reData, function ($key){
            return !empty($key);
        }, ARRAY_FILTER_USE_BOTH);

        return $reData;
    }

    // 处理选择查询结果项字段
    protected function parseSelect($role)
    {
        switch ($role) {
            case 'buyer_province':
                $column = 'buyer_city';
                break;
            case 'buyer_city':
                $column = 'buyer_district';
                break;
            case 'seller_province':
                $column = 'seller_city';
                break;
            case 'seller_city':
                $column = 'seller_district';
                break;
        }

        return $column;
    }

    /**
     * 商品咨询统计
     *
     * @param null       $group
     * @param array|null $time
     * @param array|null $filter
     * @param array|null $split
     *
     * @return mixed
     */
    public function statistic(
        $group = null,
        array $time = null,
        array $filter = null,
        array $split = null
    ) {
        $this->statisticOption = [];
        $this->parseGroup($group)
             ->parseStatisticTime($time)
             ->parseStatisticFilter($filter)
             ->parseSplit($split);

        $this->statisticQuery = CallLog::query();

        $this->handleTime()
             ->handleFilter();

        $this->statisticQuery->selectRaw('SUM(`call_times`) AS `call_times`')
                             ->selectRaw('COUNT(DISTINCT `buyer_id`) AS `buyer_times`')
                             ->selectRaw('COUNT(DISTINCT `seller_id`) AS `seller_times`');

        if (empty($this->split)) {
            $queryTimes = clone $this->statisticQuery;
            $queryShop = clone $this->statisticQuery;
            $queryGoods = clone $this->statisticQuery;

            $totalNum = $queryTimes->sum('call_times');
            $totalShop = $queryShop->distinct()->count('buyer_id');
            $totalGoods = $queryGoods->distinct()->count('goods_id');

            $this->handleGroup($this->statisticQuery);

            $data['全部'] = [
                'group'       => $this->statisticQuery->get(),
                'total_times' => $totalNum,
                'total_buyer' => $totalShop,
                'total_goods' => $totalGoods,
            ];
        } else {
            $data = [];
            foreach ($this->split as $split) {
                $query = clone $this->statisticQuery;
                foreach ($split as $name => $value) {
                    $index = implode(",", $value);

                    $query->whereIn($name, $value);

                    $queryTimes = clone $query;
                    $queryShop = clone $query;
                    $queryGoods = clone $query;

                    $totalNum = $queryTimes->sum('call_times');
                    $totalShop = $queryShop->distinct()->count('buyer_id');
                    $totalGoods = $queryGoods->distinct()->count('goods_id');

                    $this->handleGroup($query);

                    $data[$index] = [
                        'group'       => $query->get(),
                        'total_times' => $totalNum,
                        'total_buyer' => $totalShop,
                        'total_goods' => $totalGoods,
                    ];
                }
            }
        }

        /** @var \Zdp\BI\Services\Format\Call $formatter */
        $formatter = \App::make(\Zdp\BI\Services\Format\Call::class);

        $data = $formatter->format(
            $this->time,
            $this->group,
            $data
        );

        return $data;
    }

    protected function parseGroup($group = null)
    {
        if (in_array($group, $this->acceptGroup) !== false) {
            $this->group = $group;
        } else {
            $this->group = 'day';
        }

        return $this;
    }

    protected function parseStatisticTime(array $time = null)
    {
        self::parseFilterTime($time);
        $this->ensureTimeRange();

        return $this;
    }

    protected function parseStatisticFilter(array $filter = null)
    {
        $filter = (array)$filter;

        $tmp = [];

        // 排除为空的字段
        $filter = array_filter($filter, function ($key) use ($filter) {
            return $filter[$key] != null;
        }, ARRAY_FILTER_USE_KEY);

        foreach ($filter as $name => $val) {
            if (in_array($name, $this->optionFilter)) {
                $tmp[] = [$name => $val];
            }
        }

        $this->filter = $tmp;

        return $this;
    }

    protected function parseSplit(array $split = null)
    {
        $split = (array)$split;

        $tmp = [];

        $splits = array_filter($split, function ($key) use ($split) {
            return $split[$key] != null;
        }, ARRAY_FILTER_USE_KEY);

        foreach ($splits as $split) {
            $splits = array_filter($split, function ($key) use ($split) {
                return $split[$key] != null;
            }, ARRAY_FILTER_USE_KEY);
            foreach ($splits as $name => $value) {
                if (in_array($name, $this->optionFilter)) {
                    $tmp[] = [$name => $value];
                }
            }
        }
        $this->split = $tmp;

        return $this;
    }

    /**
     * 根据分组方式定义默认时间分组
     */
    protected function ensureTimeRange()
    {
        if (empty($this->time)) {
            return;
        }

        /**
         * @var Carbon $start
         * @var Carbon $end
         */
        $start = $this->time[0];
        $end = $this->time[1];

        if (empty($start) || empty($end)) {
            return;
        }

        switch ($this->group) {
            case 'week':
                $this->time = [$start->startOfWeek(), $end->endOfWeek()];
                break;

            case 'month':
                $this->time = [$start->startOfMonth(), $end->endOfMonth()];
                break;

            case 'year':
                $this->time = [$start->startOfYear(), $end->endOfYear()];
                break;

            case 'day':
            default:
                $this->time = [$start->startOfDay(), $end->endOfDay()];
                break;
        }
    }

    protected function handleGroup($query)
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
            $query->selectRaw(
                'DATE_FORMAT(`call_date`, ?) as `time`',
                [$format]
            )
                  ->groupBy('time');
        }

        return $this;
    }

    protected function handleFilter()
    {
        foreach ($this->filter as $filter) {
            foreach ($filter as $name => $value) {
                $this->handleFilterItem($name, $value);
            }
        }

        return $this;
    }

    // 处理排名筛选项
    protected function handleRankFilter()
    {
        foreach ($this->filter as $filter) {
            foreach ($filter as $name => $value) {
                $this->query->whereIn($name, $value);
            }
        }

        return $this;
    }

    protected function handleFilterItem($name, $value)
    {
        if (is_numeric($value) || empty($value)) {
            return;
        }

        if (!in_array($name, $this->optionFilter)) {
            return;
        }

        $value = (array)$value;
        $this->statisticQuery
            ->whereIn($name, $value);
    }

    /**
     * 咨询排行
     *
     * @param array $time
     * @param array $filter
     * @param null  $column 排名字段
     * @param int   $page
     * @param int   $size
     *
     * @return array
     */
    public function rank(
        array $time = null,
        array $filter = null,
        $column = null,
        $page = 1,
        $size = 10
    ) {
        $this->query = CallLog::query();

        $this->time = $this->parseTime($time);
        $this->parseStatisticFilter($filter)
             ->parseColumn($column)
             ->handleRankTime()
             ->handleRankFilter()
             ->handleSelect();

        $pager = $this->query->orderBy('call_times', 'desc')
                             ->groupBy($this->column)
                             ->paginate($size, ['*'], null, $page);

        return [
            'detail'    => $pager->items(),
            'total'     => $pager->total(),
            'current'   => $pager->currentPage(),
            'last_page' => $pager->lastPage(),
        ];
    }

    // 解析传入的排名字段
    protected function parseColumn($column = null)
    {
        $this->column = 'goods_title';
        if ($column) {
            $this->column = $column;
        }

        return $this;
    }

    // 根据不同的排名项目，返回不同的字段
    protected function handleSelect()
    {
        switch ($this->column) {
            case 'buyer_name':
                $this->query->select(['buyer_name', 'buyer_type', 'telnumber'])
                            ->selectRaw('SUM(`call_times`) AS call_times')
                            ->selectRaw('COUNT(DISTINCT `seller_id`) AS seller_num');
                break;
            case 'seller_name':
                $this->query->select(['seller_name'])
                            ->selectRaw('SUM(`call_times`) AS call_times')
                            ->selectRaw('COUNT(DISTINCT `buyer_id`) AS buyer_num')
                            ->selectRaw('COUNT(DISTINCT `goods_id`) AS goods_num');
                break;
            default:
                $this->query->select([$this->column])
                            ->selectRaw('SUM(`call_times`) AS call_times');
                break;
        }
    }
}