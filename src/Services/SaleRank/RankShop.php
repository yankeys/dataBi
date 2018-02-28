<?php

namespace Zdp\BI\Services\SaleRank;

use Zdp\BI\Services\Traits\Time;
use Zdp\Main\Data\Models\DpOpderForm;
use Zdp\Main\Data\Models\DpShopInfo;

class RankShop
{
    use Time;

    protected $shopFilter = [
        'trenchnum',      // 店铺类型
        'province',  // 店铺省份
        'city',      // 店铺城市
        'county',  // 店铺区域
        'method',         // 订单支付方式
    ];

    /**
     * @param array|null $time   统计的时间区域
     * @param array|null $filter 统计的筛选项
     * @param null       $column 确定卖家/买家
     * @param null       $type   确定统计的类型(店铺统计/区域统计)
     * @param null       $sc     升序，降序排列
     * @param string     $order  排序方式
     * @param int        $page   请求页
     * @param int        $size   单页数据量
     *
     * @return array
     */
    public function rank(
        array $time = null,
        array $filter = null,
        $column = null,
        $type = null,
        $sc = null,
        $order = null,
        $page = 1,
        $size = 20
    ) {
        $this->time = $this->parseTime($time);
        $id = 'uid';
        if ($column == 'shopid') {
            $id = 'shopid';
        }
        $this->query = DpOpderForm
            ::query()
            ->join('dp_shopInfo as s', 's.shopId', '=', 'dp_opder_form.' . $id)
            ->whereIn('dp_opder_form.orderact', DpOpderForm::ORDERACT_NORMAL);
        $this->parseFilter($filter)
             ->handleTime()
             ->handleFilter()
             ->handleSelect($type, $column)
             ->handleRaw();

        if (in_array($column, ['shopid', 'uid'])) {
            $column = 'dp_opder_form.' . $column;
        }

        $items = $this->query->groupBy($column)
                             ->orderBy($order, $sc)
                             ->paginate($size, ['*'], null, $page);

        return [
            'total'        => $items->total(),
            'current_page' => $items->currentPage(),
            'last_page'    => $items->lastPage(),
            'detail'       => $items->items(),
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
                    $tmp[] = [$name => $val];
                }
            }
        }
        $this->filter = $tmp;

        return $this;
    }

    // 解析筛选项
    protected function handleTime()
    {

        $this->query->where('dp_opder_form.addtime', '>', $this->time[0])
                    ->where('dp_opder_form.addtime', '<', $this->time[1]);

        return $this;
    }

    // 解析筛选项
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

    // 解析选择排名字段
    protected function handleSelect($type, $column)
    {
        if ($type == 'shop') {
            $this->query->select([
                'dp_opder_form.shopid',
                'dp_opder_form.uid',
                'user_tel',
                'dianPuName',
            ]);
        } else {
            switch ($column) {
                case 'city':
                    $area = ['city'];
                    break;
                case 'county':
                    $area = ['county'];
                    break;
                case 'province':
                default:
                    $area = ['province'];
                    break;
            }
            $this->query->select($area);
        }


        return $this;
    }

    // 需要聚合项目
    protected function handleRaw()
    {
        $this->query->selectRaw("SUM(good_count) as `num`")
                    ->selectRaw("SUM(total_price) as `amount`")
                    ->selectRaw("COUNT(DISTINCT `codenumber`) as `order`");

        return $this;
    }
}