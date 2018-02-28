<?php

namespace Zdp\BI\Services\SaleRank;

use App\Models\DpGoodsInfo;
use Zdp\BI\Models\PurchaseLog;
use Zdp\BI\Services\Traits\Time;

/**
 * Class RankGoods
 *
 * @property array       $time
 * @property array       $filter
 * @property array       $group
 * @property PurchaseLog $query
 *
 * @package Zdp\BI\Services\SaleRank
 */
class RankGoods
{
    use Time;

    protected $goodsFilter = [
        'buyer_id',        // 买家ID
        'buyer_type',      // 买家类型
        'buyer_province',  // 买家省份
        'buyer_city',      // 买家城市
        'buyer_district',  // 卖家区域
        'buyer_market',    // 买家市场
        'seller_id',        // 卖家ID
        'seller_type',      // 卖家类型
        'seller_province',  // 卖家省份
        'seller_city',      // 卖家城市
        'seller_district',  // 卖家区域
        'seller_market',    // 卖家市场
        'goods_id',        // 商品ID
        'goods_type_node', // 商品类别
        'goods_brand',     // 商品品牌
        'pay_method',         // 支付方式
        'pay_online_channel', // 线上支付渠道
        'delivery_method', // 物流方式
        'status', // 订单状态
    ];

    /**
     * @param array|null $time
     * @param array|null $filter
     * @param null       $column
     * @param string     $order
     * @param null       $sc
     * @param int        $page
     * @param int        $size
     *
     * @return array
     */
    public function rank(
        array $time = null,
        array $filter = null,
        $column = null,
        $order = null,
        $sc = null,
        $page = 1,
        $size = 20
    ) {
        $this->query = PurchaseLog::query();
        $this->time = $this->parseTime($time);
        $this->parseFilter($filter)
             ->handleTime()
             ->handleFilter()
             ->handleSelect()
             ->handleColumn($column);

        $queryNum = clone $this->query;
        $items = $this->query->groupBy($column)
                             ->orderBy($order, $sc)
                             ->simplePaginate($size, ['*']);

        // 处理店铺名字和商品名字
        $detail = [];
        foreach ($items as $item) {
            $goodsName = DpGoodsInfo::where('id', $item->goods_id)
                                    ->value('gname');
            $detail[] = array_merge(
                $item->toArray(),
                ['goods_name' => $goodsName]
            );
        }

        return [
            'total'        => $queryNum->count(),
            'current_page' => $items->currentPage(),
            'detail'       => $detail,
        ];
    }

    // 解析筛选项
    protected function parseFilter(array $filter = null)
    {
        $tmp = [];

        if (!empty($filter)) {
            $filter = array_filter($filter, function ($key) use ($filter) {
                return $filter[$key] != null;
            }, ARRAY_FILTER_USE_KEY);
            foreach ($filter as $name => $val) {
                if (in_array($name, $this->goodsFilter)) {
                    $tmp[] = [$name => $val];
                }
            }
        }
        $this->filter = $tmp;

        return $this;
    }


    // 解析选择排名字段
    protected function handleTime()
    {
        $this->query->where('created_at', '>', $this->time[0])
                    ->where('created_at', '<', $this->time[1]);

        return $this;
    }

    // 解析选择排名字段
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
    protected function handleSelect()
    {
        $this->query->select(['goods_id'])
                    ->selectRaw("SUM(num) as `num`")
                    ->selectRaw("SUM(price) as `amount`");

        return $this;
    }

    // 解析选择排名字段
    protected function handleColumn($column)
    {
        switch ($column) {
            case 'name':
                $this->query->selectRaw("`goods_id` AS name");
                break;
            case 'sort':
                $this->query->selectRaw("SUBSTRING_INDEX(`goods_type_node`, ',' ,-1) AS sort");
                break;
            case 'brand':
                $this->query->selectRaw("`goods_brand` AS brand");
                break;
        }

        return $this;
    }
}