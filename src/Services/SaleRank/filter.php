<?php

namespace Zdp\BI\Services\SaleRank;

use Zdp\BI\Services\Traits\Time;
use Zdp\Main\Data\Models\DpMarketInfo;
use Zdp\Main\Data\Models\DpOpderForm;

class Filter
{
    use Time;

    protected $defaultColumn = [
        'province',
        'city',
        'county',
        'method',
        'trenchnum',
        'pianquId',
    ];

    /**
     * 获取销量统计的筛选项
     *
     * @param array $time   需要获取的数据时间范围
     * @param null  $type   需要获取的数据类型(买家/卖家)
     * @param array $column 需要获取的筛选项字段
     *
     * @return
     */
    public function filter(
        array $time = null,
        $type = null,
        array $column = null
    ) {
        $this->time = $this->parseTime($time);
        $id = 'uid';
        if ($type == 'seller') {
            $id = 'shopid';
        }
        $this->query = DpOpderForm
            ::join('dp_shopInfo as s', 's.shopId', '=', 'dp_opder_form.' . $id);

        $this->parseColumn($column)
             ->handleTime();
        $reArr = [];
        foreach ($this->column as $value) {
            $reArr[$value] =
                $this->query->select($value)->distinct($value)->get();
        }
        $reData = self::format($reArr);

        return $reData;
    }

    protected function parseColumn($column)
    {
        $this->column = $this->defaultColumn;

        $column = (array)$column;
        $tmp =
            array_filter($this->defaultColumn, function ($key) use ($column) {
                return in_array($key, $column);
            }, ARRAY_FILTER_USE_BOTH);
        if ($tmp) {
            $this->column = $tmp;
        }

        return $this;
    }

    protected function handleTime()
    {
        $this->query->where('dp_opder_form.addtime', '>', $this->time[0])
                    ->where('dp_opder_form.addtime', '<', $this->time[1]);

        return $this;
    }

    protected function format($data)
    {
        $reArr = [];
        foreach ($data as $key => $items){
            $reData = array_dot($items->toArray());
            $reArr[$key] = array_filter($reData, function ($key){
                return !empty($key);
            },ARRAY_FILTER_USE_BOTH);
        }
        if (array_has($reArr,'pianquId'))
        {
            $rePianqu = [];
            foreach($reArr['pianquId'] as $id){
                $rePianqu[$id] = DpMarketInfo::where('pianquId',$id)->value('pianqu');
            }
            $reArr['pianquId'] = $rePianqu;
        }

        return $reArr;
    }
}