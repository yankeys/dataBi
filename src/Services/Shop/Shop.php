<?php

namespace Zdp\BI\Services\Shop;

use App\Models\DpShopInfo;
use Zdp\BI\Services\Traits\BlankFillIn;
use Zdp\BI\Services\Traits\Time;
use Zdp\Main\Data\Models\DpGoodsInfo;

/**
 * Class Shop
 *
 * @property array       $time
 * @property string      $group
 * @property array       $filter
 * @property array       $select
 * @property array       $split
 * @property DpGoodsInfo $query
 *
 * @package Zdp\BI\Services\Shop
 */
class Shop
{
    use Time, BlankFillIn;

    // 店铺选择项目
    protected $shopSelect = [
        'dianPuName',           // string 店铺名称
        'license_act',          // int 营业执照状态, see LICENCE_STATUS_XXX
        'pianquId',             // ind 市场id, dp_pianqu.pianquId，海霸王，青白江
        'trenchnum',            // int 店铺类型分组编号
        'province',             // string 省名称
        'city',                 // string 市名称
        'county',               // string 县名称
        'business',             // int 是否上线, see STATUS_XXXX
        'state',                // int see STATE_XXX
    ];

    // 店铺筛选项字段
    protected $shopFilter = [
        'dianPuName',           // string 店铺名称
        'license_act',          // int 营业执照状态, see LICENCE_STATUS_XXX
        'pianquId',             // ind 市场id, dp_pianqu.pianquId，海霸王，青白江
        'trenchnum',            // int 店铺类型分组编号
        'province',             // string 省名称
        'city',                 // string 市名称
        'county',               // string 县名称
        'business',             // int 是否上线, see STATUS_XXXX
        'state',                // int see STATE_XXX
    ];

    // 店铺分裂项字段
    protected $shopSplit = [
        'dianPuName',           // string 店铺名称
        'license_act',          // int 营业执照状态, see LICENCE_STATUS_XXX
        'pianquId',             // ind 市场id, dp_pianqu.pianquId，海霸王，青白江
        'trenchnum',            // int 店铺类型分组编号
        'province',             // string 省名称
        'city',                 // string 市名称
        'county',               // string 县名称
        'business',             // int 是否上线, see STATUS_XXXX
        'state',                // int see STATE_XXX
    ];

    /**
     * 店铺统计的总统计项
     *
     * @param array|null $time
     * @param null       $select
     * @param array|null $filter
     *
     * @return array
     */
    public function total(
        $select = null,
        array $time = null,
        array $filter = null
    ) {
        if (!$select) {
            $select = 'trenchnum';
        }
        $this->query = DpShopInfo::query();
        $this->time = $this->parseTime($time);
        $this->parseFilter($filter)
             ->handleTime()
             ->handleFilter();
        $totalQuery = clone $this->query;
        $total = $totalQuery->where('trenchnum', '<>', 0)->count();

        $detail = $this->query->select($select)
                              ->selectRaw('COUNT(`shopId`) AS num')
                              ->groupBy($select)
                              ->get();
        $reArr = [
            'total'  => $total,
            'detail' => $detail,
        ];

        return $reArr;
    }

    /**
     * 店铺统计的详细数据
     *
     * @param null       $group
     * @param array|null $time
     * @param array|null $split
     * @param array|null $filter
     *
     * @return array
     */
    public function detail(
        $group = null,
        array $time = null,
        array $split = null,
        array $filter = null
    ) {
        $this->query = DpShopInfo::query();
        $this->time = $this->parseTime($time);
        $this->parseGroup($group)
             ->parseFilter($filter)
             ->parseSplit($split)
             ->handleTime()
             ->handleGroup()
             ->handleFilter()
             ->handleSelect();
        // 分裂项
        if (empty($this->split)) {
            $detail['全部'] = $this->query->get()->toArray();
        } else {
            $detail = [];
            foreach ($this->split as $split) {
                $query = clone $this->query;
                foreach ($split as $name => $value) {
                    $index = implode(",", $value);
                    $detail[$index] =
                        $query->whereIn($name, $value)->get()->toArray();
                }
            }
        }
        $reArr = $this->format($detail);

        return $reArr;
    }

    // 解析选择项
    protected function parseSelect(array $select = null, $defaultSelect)
    {
        $tmp = [];

        if (!empty($select)) {
            $select = array_filter($select, function ($key) use ($select) {
                return $select[$key] != null;
            }, ARRAY_FILTER_USE_KEY);
            foreach ($select as $val) {
                if (in_array($val, $defaultSelect)) {
                    $tmp = array_merge($tmp, [$val]);
                }
            }
        }

        $this->select = $tmp;

        return $this;
    }

    // 解析组别
    protected function parseGroup($group = null)
    {
        if ($group) {
            $this->group = $group;
        } else {
            $this->group = 'day';
        }

        return $this;
    }

    // 解析筛选项
    protected function parseFilter($filter = null)
    {
        $tmp = [];

        if (!empty($filter)) {
            $filter = array_filter($filter, function ($key) use ($filter) {
                return $filter[$key] != null;
            }, ARRAY_FILTER_USE_KEY);
            foreach ($filter as $name => $val) {
                if (in_array($name, $this->shopFilter)) {
                    $tmp[] = [$name => $val];
                }
            }
        }
        $this->filter = $tmp;

        return $this;
    }

    protected function parseSplit($split = null)
    {
        // 定义split为数组，即使为空也是数组格式
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
                if (in_array($name, $this->shopSplit)) {
                    $tmp[] = [$name => $value];
                }
            }
        }
        $this->split = $tmp;

        return $this;
    }

    // 处理时间
    protected function handleTime()
    {
        list($start, $end) = $this->time;

        $this->query->where('date', '>=', $start)
                    ->where('date', '<=', $end);

        return $this;
    }

    // 处理分组
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
            $this->query->selectRaw('DATE_FORMAT(`date`,?) as time', [$format])
                        ->groupBy('time');
        }

        return $this;
    }

    // 处理筛选项
    protected function handleFilter()
    {
        foreach ($this->filter as $filter) {
            foreach ($filter as $name => $value) {
                if (empty($value)) {
                    return;
                }
                $this->query->whereIn($name, $value);
            }
        }

        return $this;
    }

    // 处理选择项
    protected function handleSelect()
    {
        $this->query->selectRaw('COUNT(shopId) AS num');

        return $this;
    }

    // 格式化数据
    protected function format($detail)
    {
        $time = $this->time;
        // 获取时间范围内所有的时间字段
        $series = $this->groupByGiven($time[0], $time[1], $this->group);
        // 填充数据
        $reArr = [];
        foreach ($detail as $key => $item) {
            $reArr[$key] = $this->blankFillIn($item, $series, 'time', ['num' => 0]);
        }

        return $reArr;
    }
}