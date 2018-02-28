<?php

namespace Zdp\BI\Services;

use Carbon\Carbon;
use Zdp\BI\Models\GoodsStatistic;
use Zdp\Main\Data\Models\DpGoodsInfo;

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
class Goods
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $statisticQuery;

    protected $statisticOption = [];

    protected $optionFilter = [
        'sort',
        'brand',
        'province',
        'city',
        'district',
        'market',
    ];

    protected $optionSplit = [
        'sort',
        'brand',
        'province',
        'city',
        'district',
        'market',
    ];

    protected $optionSelect = [
        'audit',    // 待审核
        'normal',   // 已审核
        'close',    // 已下架
        'delete',   // 已删除
        'reject',   // 拒绝
        'modify_audit', // 修改待审核
        'perfect',  // 待完善

        'new',      // 新增
        'overdue',  // 过期

        'sale',     // 上架
        'not_sale', // 下架
    ];

    // 分布状况的可选状态
    protected $optionIndexSelect = [
        'audit',    // 待审核
        'normal',   // 已审核
        'close',    // 已下架
        'delete',   // 已删除
        'reject',   // 拒绝
        'modify_audit', // 修改待审核
        'perfect',  // 待完善

        'sale',     // 上架
        'not_sale', // 下架
    ];

    protected $acceptGroup = [
        'day',
        'week',
        'month',
        'year',
        'none',
    ];

    protected $optionsFields = [
        'time',
        'group', // IN (day,week,month,year,none)
        'split',
        'select',
        'filter',
    ];

    /**
     * 商品基础信息统计筛选项
     *
     * @param array|null $time
     * @param array|null $filter
     *
     * @return array
     */
    public function filter(array $time = null, array $filter = null)
    {
        $this->parseFilterTime($time)
             ->parseFilter($filter);

        $this->statisticQuery = GoodsStatistic::query();

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
     * 处理时间限制
     *
     * @return $this
     */
    protected function handleTime()
    {
        list($start, $end) = $this->time;

        $this->statisticQuery->where('date', '>=', $start)
                             ->where('date', '<=', $end);

        return $this;
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
     * @param array|null $filter
     *
     * @return $this
     */
    protected function parseIndexFilter(array $filter = null)
    {
        $this->filter = [];
        if ($filter) {
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
     * 根据传入的省市信息获取市场信息
     *
     * @param $name
     * @param $role
     * @param $type
     *
     * @return array
     */
    public function series($name, $role, $type)
    {
        if ($type == 'area') {
            $type = $this->parseRole($role);
        }

        $reData = GoodsStatistic::where($role, $name)
                      ->selectRaw('DISTINCT '.$type)
                      ->get()->toArray();
        $reData = array_filter($reData, function ($key) {
            return !empty($key);
        }, ARRAY_FILTER_USE_BOTH);

        return $reData;
    }

    // 处理选择查询结果项字段
    protected function parseRole($role)
    {
        switch ($role) {
            case 'province':
                $column = 'city';

                break;
            case 'city':
                $column = 'district';

                break;
        }

        return $column;
    }

    /**
     * 商品分布状况
     *
     * @param array $select
     * @param array $filter
     *
     * @return array
     */
    public function index(
        array $select = null,
        array $filter = null
    ) {
        $this->parseIndexFilter($filter)
             ->parseIndexSelect($select);
        $this->statisticQuery = DpGoodsInfo::query();
        $this->handleFilter();
        $data['status'] = $this->handleIndexSelect();
        $data['overdue'] = $this->judgeOverdue();

        return $data;
    }


    /**
     * 商品基础信息统计入口
     *
     * @param null       $group
     * @param array|null $time
     * @param array|null $split
     * @param array|null $select
     * @param array|null $filter
     *
     * @return array
     */
    public function statistic(
        $group = null,
        array $time = null,
        array $select = null,
        array $filter = null,
        array $split = null
    ) {
        $this->statisticOption = [];
        $this->parseGroup($group)
             ->parseStatisticTime($time)
             ->parseSelect($select)
             ->parseStatisticFilter($filter)
             ->parseSplit($split);

        $this->statisticQuery = GoodsStatistic::query();

        $this->handleTime()
             ->handleGroup()
             ->handleSelect()
             ->handleFilter();

        $query = $this->statisticQuery;

        if (empty($this->split)) {
            $data['全部'] = $query->get();
        } else {
            $data = [];
            foreach ($this->split as $split) {
                $query = clone $this->statisticQuery;
                foreach ($split as $name => $value) {
                    $index = implode(",", $value);
                    $data[$index] = $query->whereIn($name, $value)->get();
                }
            }
        }

        /** @var \Zdp\BI\Services\Format\Goods $formatter */
        $formatter = \App::make(
            'Zdp\BI\Services\Format\Goods',
            [$this->statisticOption]
        );

        return $formatter->format(collect($data));
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

    protected function parseSelect(array $select = null)
    {
        $this->select = array_intersect($this->optionSelect, (array)$select);

        if (empty($this->select)) {
            $this->select = $this->optionSelect;
        }

        return $this;
    }

    protected function parseIndexSelect(array $select = null)
    {
        $this->select = array_intersect($this->optionIndexSelect, (array)$select);

        if (empty($this->select)) {
            $this->select = $this->optionSelect;
        }

        return $this;
    }

    protected function parseStatisticFilter(array $filter = null)
    {
        $filter = (array)$filter;

        $tmp = [];

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
            $this->statisticQuery
                ->selectRaw(
                    'DATE_FORMAT(`date`, ?) as `time`',
                    [$format]
                )
                ->groupBy('time');
        }

        return $this;
    }

    protected function handleSelect()
    {
        if (array_search('audit', $this->select) !== false) {
            $this->statisticQuery->selectRaw('SUM(`audit`) AS `total_audit`');
        }
        if (array_search('normal', $this->select) !== false) {
            $this->statisticQuery->selectRaw('SUM(`normal`) AS `total_normal`');
        }
        if (array_search('close', $this->select) !== false) {
            $this->statisticQuery->selectRaw('SUM(`close`) AS `total_close`');
        }
        if (array_search('delete', $this->select) !== false) {
            $this->statisticQuery->selectRaw('SUM(`delete`) AS `total_delete`');
        }
        if (array_search('reject', $this->select) !== false) {
            $this->statisticQuery->selectRaw('SUM(`reject`) AS `total_reject`');
        }
        if (array_search('modify_audit', $this->select) !== false) {
            $this->statisticQuery
                ->selectRaw('SUM(`modify_audit`) AS `total_modify_audit`');
        }
        if (array_search('perfect', $this->select) !== false) {
            $this->statisticQuery
                ->selectRaw('SUM(`perfect`) AS `total_perfect`');
        }
        if (array_search('new', $this->select) !== false) {
            $this->statisticQuery->selectRaw('SUM(`new`) AS `total_new`');
        }
        if (array_search('overdue', $this->select) !== false) {
            $this->statisticQuery
                ->selectRaw('SUM(`overdue`) AS `total_overdue`');
        }
        if (array_search('sale', $this->select) !== false) {
            $this->statisticQuery->selectRaw('SUM(`sale`) AS `total_sale`');
        }
        if (array_search('not_sale', $this->select) !== false) {
            $this->statisticQuery
                ->selectRaw('SUM(`not_sale`) AS `total_not_sale`');
        }

        return $this;
    }

    protected function handleIndexSelect()
    {
        $data = [];
        foreach ($this->select as $select)
        {
            $query = clone $this->statisticQuery;
            switch ($select){
                case 'audit':
                    $data[] = $this->handleIndexSelectRaw($query,$select,'shenghe_act',DpGoodsInfo::STATUS_AUDIT);
                    break;
                case 'normal':
                    $data[] = $this->handleIndexSelectRaw($query,$select,'shenghe_act',DpGoodsInfo::STATUS_NORMAL);
                    break;
                case 'close':
                    $data[] = $this->handleIndexSelectRaw($query,$select,'shenghe_act',DpGoodsInfo::STATUS_CLOSE);
                    break;
                case 'delete':
                    $data[] = $this->handleIndexSelectRaw($query,$select,'shenghe_act',DpGoodsInfo::STATUS_DEL);
                    break;
                case 'reject':
                    $data[] = $this->handleIndexSelectRaw($query,$select,'shenghe_act',DpGoodsInfo::STATUS_REJECT);
                    break;
                case 'modify_audit':
                    $data[] = $this->handleIndexSelectRaw($query,$select,'shenghe_act',DpGoodsInfo::STATUS_MODIFY_AUDIT);
                    break;
                case 'perfect':
                    $data[] = $this->handleIndexSelectRaw($query,$select,'shenghe_act',DpGoodsInfo::WAIT_PERFECT);
                    break;
                case 'sale':
                    $data[] = $this->handleIndexSelectRaw($query,$select,'on_sale',DpGoodsInfo::GOODS_SALE);
                    break;
                case 'not_sale':
                    $data[] = $this->handleIndexSelectRaw($query,$select,'on_sale',DpGoodsInfo::GOODS_NOT_ON_SALE);
                    break;
            }
        }

        return $data;
    }

    protected function handleIndexSelectRaw($query, $select, $column, $status)
    {
        return $query->selectRaw('COUNT(id) AS total_'.$select)
                     ->where($column, $status)
                     ->groupBy($column)
                     ->get();
    }

    // 判断是否过期
    protected function judgeOverdue()
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $this->statisticQuery->join(
            'dp_goods_basic_attributes',
            'dp_goods_info.id',
            '=',
            'dp_goods_basic_attributes.goodsid'
        );
        $queryCount = clone $this->statisticQuery;
        // 商品总数
        $goodsNum = $this->statisticQuery->count('id');
        // 过期商品数
        $overdue = $queryCount->where('dp_goods_basic_attributes.auto_soldout_time','<',$now)
                              ->count('id');

        return [
            'all_goods' => $goodsNum,
            'overdue'   => $overdue,
        ];
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

    protected function handleFilterItem($name, $value)
    {
        if (is_numeric($name) || empty($value)) {
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
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        if ($name == 'query') {
            return isset($this->statisticQuery);
        } elseif (array_search($name, $this->optionsFields) !== false) {
            return array_has($this->statisticOption, $name);
        }

        return false;
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if ($name == 'query') {
            return $this->statisticQuery;
        } elseif (array_search($name, $this->optionsFields) !== false) {
            return array_get($this->statisticOption, $name);
        }

        return null;
    }

    public function __set($name, $value)
    {
        if (array_search($name, $this->optionsFields) !== false) {
            array_set($this->statisticOption, $name, $value);
        }
    }
}
