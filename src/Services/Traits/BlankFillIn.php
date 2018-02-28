<?php

namespace Zdp\BI\Services\Traits;

trait BlankFillIn
{
    /**
     * 填充空白以获取需要的连续数据
     *
     * @param array|object $items  需要被填充的数据
     * @param array|string $column 需要插入的字段
     * @param array        $series 连续数据字段
     * @param array        $fields 需要填充的数组
     *
     * @return array
     */
    public function blankFillIn($items, $series, $column, $fields)
    {
        $dates = [];
        foreach ($items as $item) {
            $dates[] = $item[$column];
        }
        // 数据填充
        $addItem = [];
        foreach ($series as $single) {
            // 原数据不存在字段值则填充
            if (!in_array($single, $dates)) {
                $addItem[] = array_merge([$column=>$single],$fields);
            }
        }
        $items = array_merge($addItem, $items);
        // 对二维数组进行排序
        $reKey = [];
        foreach ($items as $key => $v) {
            $reKey[$key]  = $v[$column];
        }
        array_multisort($reKey, SORT_ASC, $items);

        return $items;
    }
}