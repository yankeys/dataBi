<?php

namespace Zdp\BI\Services\Shop;

use App\Models\DpShopInfo;
use Zdp\BI\Services\Traits\Time;

class Filter
{
    use Time;
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

    /**
     * 获取统计筛选项
     *
     * @param array|null $time
     * @param array|null $select
     *
     * @return array
     */
    public function filter(array $time = null, array $select = null)
    {
        if (!$select) {
            $select = ['province'];
        }

        $this->query = DpShopInfo::query();
        $this->time = $this->parseTime($time);

        $this->parseSelect($select)
              ->handleTime();

        $reArr = [];
        foreach ($this->select as $select) {
            $reData = $this->query->select($select)
                                          ->distinct()
                                          ->lists($select)
                                          ->all();
            $reArr[$select] = array_filter($reData, function ($key){
                return !empty($key);
            },ARRAY_FILTER_USE_BOTH);
        }

        return $reArr;
    }

    // 解析选择项
    protected function parseSelect(array $select = null)
    {
        $tmp = [];

        if (!empty($select)) {
            $select = array_filter($select, function ($key) use ($select) {
                return $select[$key] != null;
            }, ARRAY_FILTER_USE_KEY);
            foreach ($select as $val) {
                if (in_array($val, $this->shopSelect)) {
                    $tmp = array_merge($tmp, [$val]);
                }
            }
        }
        $this->select = $tmp;

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

    /**
     * 根据传入的省市信息获取下级区域信息
     *
     * @param $name
     * @param $role
     */
    public function series($name, $role, $type)
    {
        if ($type == 'market'){
            $data = DpShopInfo
                ::join('dp_pianqu as p', 'p.pianquId', '=', 'dp_shopInfo.pianquId' )
                ->where('dp_shopInfo.'.$role, $name)
                ->selectRaw('DISTINCT pianqu AS `market`')
                ->get()
                ->toArray();
        } else {
            $this->parseRole($role);
            $data = DpShopInfo
                ::where($role, $name)
                ->selectRaw('DISTINCT(' . $this->column . ') AS `' . $this->column . '`')
                ->get()->toArray();
        }
        $data = array_filter($data, function ($key){
            return !empty($key);
        },ARRAY_FILTER_USE_BOTH);

        return $data;
    }

    // 处理选择查询结果项字段
    protected function parseRole($role)
    {
        switch ($role) {
            case 'province':
                $this->column = 'city';

                return;
            case 'city':
                $this->column = 'county';

                return;
        }
    }
}