<?php

namespace Zdp\BI\Services;

use Carbon\Carbon;
use Zdp\BI\Models\PurchaseLog;

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
class Order
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $statisticQuery;

    /**
     * @var callable 自定义的查询编辑器
     */
    protected $customQueryEditor;

    protected $statisticOption = [];

    protected $optionSplit = [
        'buyer.type',   // 1
        'buyer.geo',    // 1,1,1 按照省市区分 1,1 按照省市分 1 按照省分
        'buyer.market', // 1

        // 同 buyer
        'seller.type',
        'seller.geo',
        'seller.market',

        'goods.type',  // IN(-1:当前等级,1:一级分类,2,3,4...)
        'goods.brand', // 1
        'goods.price', // 分割单位

        'num',
        'price',

        'pay', // method, 支付方式 channel, 支付渠道
        'delivery',
        'status',
    ];

    protected $optionFilter = [
        'buyer.id'           => 'buyer_id',
        'buyer.type'         => 'buyer_type',
        'buyer.geo.province' => 'buyer_province',
        'buyer.geo.city'     => 'buyer_city',
        'buyer.geo.district' => 'buyer_district',
        'buyer.market'       => 'buyer_market',

        'seller.id'           => 'seller_id',
        'seller.type'         => 'seller_type',
        'seller.geo.province' => 'seller_province',
        'seller.geo.city'     => 'seller_city',
        'seller.geo.district' => 'seller_district',
        'seller.market'       => 'seller_market',

        'goods.id'    => 'goods_id',
        'goods.type'  => 'goods_type_node',
        'goods.brand' => 'goods_brand',
        'goods.price' => 'goods_price',

        'num'   => 'num',
        'price' => 'price',

        'pay.method'  => 'pay_method',
        'pay.channel' => 'pay_online_channel',

        'delivery' => 'delivery_method',

        'status' => 'status',
    ];

    protected $optionsFields = [
        'time',
        'group', // IN (minute,hour,day,week,month,year,none)
        'split',
        'select',
        'filter',
    ];

    protected $acceptGroup = [
        'minute',
        'hour',
        'day',
        'week',
        'month',
        'year',
        'none',
    ];

    /**
     * 统计入口
     *
     * @param null|string $group  时间分组单位
     * @param null|array  $time   时间限制
     * @param null|array  $split  分裂项
     * @param null|array  $select 统计值
     * @param null|array  $filter 过滤器
     *
     * @return array
     */
    public function statistic(
        $group = null,
        array $time = null,
        array $split = null,
        array $select = null,
        array $filter = null
    ) {
        call_user_func_array([$this, 'parseOption'], func_get_args());

        $data = $this->queryData();

        /** @var \Zdp\BI\Services\Format\Order $formatter */
        $formatter = \App::make(
            'Zdp\BI\Services\Format\Order',
            [$this->statisticOption]
        );

        return $formatter->format($data);
    }

    public function filters(array $time = null, array $filter = null)
    {
        $this->statisticOption = [];

        $this->parseTime($time)
             ->parseFilter($filter);

        $this->statisticQuery = PurchaseLog::query();

        $this->handleTime()
             ->handleFilter();

        $query = $this->statisticQuery;

        if (is_callable($this->customQueryEditor)) {
            $query = call_user_func($this->customQueryEditor, $query);
        }

        $filters = [];

        foreach ($this->optionFilter as $name => $value) {
            if (strpos($name, '.id') === false &&
                strpos($name, 'num') === false &&
                strpos($name, 'price') === false
            ) {
                $filters = array_add(
                    $filters,
                    $name,
                    $this->querySingleFilter(clone $query, $value)
                );
            }
        }

        return $filters;
    }

    protected function querySingleFilter($query, $name)
    {
        $data = $query->selectRaw('DISTINCT `' . $name . '`')
                      ->lists($name)
                      ->all();

        if ($name == 'goods_type_node') {
            $tmp = [];
            foreach ($data as $node) {
                $tmp = array_merge($tmp, explode(',', $node));
            }
            $data = array_unique($tmp);
        }

        return $data;
    }

    /**
     * 集中处理参数
     *
     * @param null|string $group  时间分组单位
     * @param null|array  $time   时间限制
     * @param null|array  $split  分裂项
     * @param null|array  $select 统计值
     * @param null|array  $filter 过滤器
     *
     * @return $this
     */
    protected function parseOption(
        $group = null,
        array $time = null,
        array $split = null,
        array $select = null,
        array $filter = null
    ) {
        $this->statisticOption = [];

        $this->parseGroup($group)
             ->parseTime($time)
             ->parseSplit($split)
             ->parseSelect($select)
             ->parseFilter($filter);

        return $this;
    }

    /**
     * 生成查询语句
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    protected function queryData()
    {
        $this->statisticQuery = PurchaseLog::query();

        $this->handleTime()
             ->handleGroup()
             ->handleSplit()
             ->handleSelect()
             ->handleFilter();

        $query = $this->statisticQuery;

        if (is_callable($this->customQueryEditor)) {
            $query = call_user_func($this->customQueryEditor, $query);
        }

        return $query->get();
    }

    /**
     * 处理分组方式
     *
     * @return $this
     */
    protected function handleGroup()
    {
        switch ($this->group) {
            case 'minute':
                $format = '%Y-%m-%d %H:%i';
                break;

            case 'hour':
                $format = '%Y-%m-%d %k点';
                break;

            case 'day':
                $format = '%Y-%m-%d';
                break;

            case 'week':
                $format = '%x年 第%v周';
                break;

            case 'month':
                $format = '%Y年%m月';
                break;

            case 'year':
                $format = '%Y年';
                break;

            case 'none':
                break;
        }

        if (!empty($format)) {
            $this->statisticQuery
                ->selectRaw(
                    'DATE_FORMAT(`created_at`, ?) as `time`',
                    [$format]
                )
                ->groupBy('time');
        }

        return $this;
    }

    /**
     * 解析分组方式
     *
     * @param null $group
     *
     * @return $this
     */
    protected function parseGroup($group = null)
    {
        if (array_search($group, $this->acceptGroup) !== false) {
            $this->group = $group;
        } else {
            $this->group = 'day';
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

        $this->statisticQuery->where('created_at', '>=', $start)
                             ->where('created_at', '<=', $end);

        return $this;
    }

    /**
     * 处理时间限制参数
     *
     * @param array|null $time
     *
     * @return $this
     */
    protected function parseTime(array $time = null)
    {
        if (empty($time)) {
            $this->time = $this->defaultTime();
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

        $this->ensureTimeRange();

        return $this;
    }

    /**
     * 默认时间范围
     *
     * @return array
     */
    protected function defaultTime()
    {
        switch ($this->group) {
            case 'minute':
                // 30 分钟内
                return [Carbon::now()->subMinutes(15), Carbon::now()];

            case 'hour':
                // 48 个小时内
                return [Carbon::now()->subHours(12), Carbon::now()];

            case 'week':
                // 过去一个季度
                return [Carbon::now()->subWeeks(7), Carbon::now()];

            case 'month':
                // 过去一年
                return [Carbon::now()->subMonths(6), Carbon::now()];

            case 'year':
                // 过去 6 年
                return [Carbon::now()->subYears(6), Carbon::now()];

            case 'day':
            default:
                // 过去一个月
                return [Carbon::now()->subDays(7), Carbon::now()];
        }
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
            case 'minute':
                $this->time = [$start->second(0), $end->second(59)];
                break;

            case 'hour':
                $this->time = [
                    $start->minute(0)->second(0),
                    $end->minute(0)->second(0),
                ];
                break;

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

    /**
     * 处理分裂项
     *
     * @return $this
     */
    protected function handleSplit()
    {
        foreach ($this->split as $name => $value) {
            $this->handleSplitItem($name, $value);
        }

        return $this;
    }

    /**
     * 处理单个分裂项
     *
     * @param string $name
     * @param mixed  $value
     */
    protected function handleSplitItem($name, $value)
    {
        switch ((string)$name) {
            case 'seller.geo':
            case 'buyer.geo':
                $geo = explode(',', $value);
                $fieldPrefix = strstr($name, '.', true) . '_';

                if (!empty(array_get($geo, 0))) {
                    $this->statisticQuery->addSelect($fieldPrefix . 'province')
                                         ->groupBy($fieldPrefix . 'province');
                }

                if (!empty(array_get($geo, 1))) {
                    $this->statisticQuery->addSelect($fieldPrefix . 'city')
                                         ->groupBy($fieldPrefix . 'city');
                }

                if (!empty(array_get($geo, 2))) {
                    $this->statisticQuery->addSelect($fieldPrefix . 'district')
                                         ->groupBy($fieldPrefix . 'district');
                }
                break;

            case 'buyer.type':
            case 'buyer.market':
            case 'seller.type':
            case 'seller.market':
                $field = str_replace('.', '_', $name);
                $this->statisticQuery->addSelect($field)
                                     ->groupBy($field);
                break;

            case 'goods.type':
                $fieldName = "goods_type";

                $fieldExp = "SUBSTRING_INDEX(`goods_type_node`, ',', {$value})";
                if (abs($value) != 1) {
                    $fieldExp = "SUBSTRING_INDEX($fieldExp, ',', -1)";
                }

                $fieldExp .= " as `{$fieldName}`";

                $this->statisticQuery->selectRaw($fieldExp)
                                     ->groupBy($fieldName);

                break;

            case 'goods.brand':
                $this->statisticQuery->addSelect('goods_brand')
                                     ->groupBy('goods_brand');
                break;

            case 'goods.price':
                if (empty($value)) {
                    $value = 10;
                }

                $fieldName = 'goods_price_range';
                $raw = "FLOOR(`goods_price`/{$value}) as `{$fieldName}`";

                $this->statisticQuery->selectRaw($raw)
                                     ->groupBy($fieldName);
                break;

            case 'num':
            case 'price':
                if (empty($value)) {
                    $value = 10;
                }

                $fieldName = "{$name}_range";
                $raw = "FLOOR(`{$name}`/{$value}) as `{$fieldName}`";

                $this->statisticQuery->selectRaw($raw)
                                     ->groupBy($fieldName);
                break;

            case 'pay':
                $fieldName =
                    $value == 'channel' ? 'pay_online_channel' : 'pay_method';

                $this->statisticQuery->addSelect($fieldName)
                                     ->groupBy($fieldName);

                if ($value == 'channel') {
                    $this->statisticQuery->where('pay_method', '付款到平台');
                }
                break;

            case 'delivery':
                $this->statisticQuery->addSelect('delivery_method')
                                     ->groupBy('delivery_method');
                break;

            case 'status':
                $this->statisticQuery->addSelect('status')
                                     ->groupBy('status');
                break;
        }
    }

    /**
     * @param null|array $split
     *
     * @return $this
     */
    protected function parseSplit(array $split = null)
    {
        $this->split = array_filter(array_dot((array)$split), function ($key) {
            return array_search($key, $this->optionSplit) !== false &&
                   !array_has((array)$this->filter, $key);
        }, ARRAY_FILTER_USE_KEY);

        return $this;
    }

    /**
     * 处理需要SUM的字段 num:销量 price:金额
     *
     * @return $this
     */
    protected function handleSelect()
    {
        if (array_search('price', $this->select) !== false) {
            $this->statisticQuery->selectRaw('SUM(`price`) AS `total_price`');
        }

        if (array_search('num', $this->select) !== false) {
            $this->statisticQuery->selectRaw('SUM(`num`) AS `total_num`');
        }

        return $this;
    }

    /**
     * @param array|null $select
     *
     * @return $this
     */
    protected function parseSelect(array $select = null)
    {
        $this->select = array_intersect(['price', 'num'], (array)$select);

        if (empty($this->select)) {
            $this->select = ['price', 'num'];
        }

        return $this;
    }

    /**
     * 处理筛选项
     *
     * @return $this
     */
    protected function handleFilter()
    {
        foreach ($this->filter as $name => $value) {
            $this->handleFilterItem($name, $value);
        }

        return $this;
    }

    /**
     * 处理单个 filter
     *
     * @param string $name
     * @param mixed  $value
     */
    protected function handleFilterItem($name, $value)
    {
        if (empty($value)) {
            return;
        }

        $fieldName = array_get($this->optionFilter, $name);

        if (empty($fieldName)) {
            return;
        }

        if ($fieldName == 'goods_type_node') {
            $this->statisticQuery
                ->whereRaw("FIND_IN_SET(?, `{$fieldName}`)", [$value]);
        } elseif (in_array($fieldName, ['goods_price', 'num', 'price'])) {
            $this->statisticQuery
                ->whereBetween($fieldName, (array)$value);
        } elseif (!empty($fieldName)) {
            $value = (array)$value;
            if ($value[0] == 'in') {
                $this->statisticQuery
                    ->whereIn($fieldName, $value[1]);
            } else {
                call_user_func_array(
                    [$this->statisticQuery, 'where'],
                    array_merge([$fieldName], $value)
                );
            }
        }
    }

    /**
     * @param array|null $filter
     *
     * @return $this
     */
    protected function parseFilter(array $filter = null)
    {
        $filter = (array)$filter;
        if (array_get($filter, 'pay.channel') == '支付宝') {
            array_add($filter, 'pay.method', '付款到平台');
        }

        $tmp = [];
        foreach ($this->optionFilter as $key => $val) {
            if (array_has($filter, $key)) {
                $tmp[$key] = array_get($filter, $key);
            }
        }
        $this->filter = $tmp;

        return $this;
    }

    /**
     * 自定义更改 Query
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function customQuery(callable $callback)
    {
        $this->customQueryEditor = $callback;

        return $this;
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
