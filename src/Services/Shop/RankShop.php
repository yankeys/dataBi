<?php

namespace Zdp\BI\Services\Shop;

use App\Models\DpShopInfo;
use Zdp\BI\Services\Traits\Time;


/**
 * Class Shop
 *
 * @property array      $time
 * @property array      $filter
 * @property array      $group
 * @property DpShopInfo $query
 *
 * @package Zdp\BI\Services\Shop
 */
class RankShop
{
    use Time;

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
        'shengheAct',           // 店铺审核状态
    ];

    /**
     * 区域内店铺数量排名
     *
     * @param array|null $time
     * @param array|null $filter
     * @param null       $group
     * @param null       $isShenghe
     * @param int        $page
     * @param int        $size
     *
     * @return array
     */
    public function rank(
        array $time = null,
        array $filter = null,
        $group = null,
        $isShenghe = null,
        $page = 1,
        $size = 20
    ) {
        $this->query = DpShopInfo
            ::join('dp_shangHuInfo as u','u.shopId','=','dp_shopInfo.shopid')
            ->where('u.laoBanHao', 0);;
        $this->time = $this->parseTime($time);
        $this->parseFilter($filter)
             ->parseGroup($group)
             ->handleTime($isShenghe)
             ->handleFilter()
             ->handleGroup();

        $items = $this->query->groupBy($group)
                             ->orderBy('num', 'desc')
                             ->paginate($size, ['*'], null, $page);

        return [
            'detail'    => $items->items(),
            'total'     => $items->total(),
            'current'   => $items->currentPage(),
            'last_page' => $items->lastPage(),
        ];
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
                if (in_array($name, $this->shopFilter)) {
                    $tmp[$name] = $val;
                }
            }
        }
        $this->filter = $tmp;

        return $this;
    }

    // 解析分组
    protected function parseGroup($group)
    {
        switch ($group) {
            case 'province':
                $select = ['province'];
                break;
            case 'city':
                $select = ['province', 'city'];
                break;
            case 'county':
            default:
                $select = ['province', 'city', 'county'];
        }

        $this->group = $select;

        return $this;
    }

    // 处理时间项
    protected function handleTime($isShenghe)
    {
        $time = $this->time;

        if ($isShenghe){
            $this->query->where('zhuceTime', '>=', $time[0])
                        ->where('zhuceTime', '<=', $time[1]);
        } else {
            $this->query->where('date', '>=', $time[0])
                        ->where('date', '<=', $time[1]);
        }

        return $this;
    }

    // 处理筛选项
    protected function handleFilter()
    {
        foreach ($this->filter as $key => $value) {
            $this->query->whereIn($key, $value);
        }

        return $this;
    }

    // 处理排行的分组
    protected function handleGroup()
    {
        $this->query->select($this->group)
                    ->selectRaw('COUNT(shId) AS `num`');
    }
}