<?php

namespace Zdp\BI\Services\GroupOn;

use Carbon\Carbon;
use Zdp\Main\Data\Models\DpGoodsGrouponConfirmLog;
use Zdp\Main\Data\Models\DpOpderForm;

/**
 * Class GroupOn
 *
 * 集中采购统计
 *
 * @package Zdp\BI\Services
 */
class Basic
{
    const STATICS_SELECT = [
        'paid.money'    => '成交金额',
        'ordered.money' => '订单金额',

        'paid.num'     => '成交数量',
        'ordered.num'  => '下单数量',
        'modified.num' => '改价数量',

        'ordered.seller'  => '通知卖家',
        'modified.seller' => '改价卖家',

        'ordered.count' => '报价单数',
        'paid.count'    => '支付单数',
    ];

    const STATICS_GROUP = [
        'hour'  => '小时',
        'day'   => '日',
        'week'  => '周',
        'month' => '月',
        'year'  => '年',
    ];

    protected $select = ['paid.money', 'ordered.money'];

    protected $group = 'day';

    protected $time;

    /**
     * 统计
     *
     * @param array  $select
     * @param string $group day/hour
     * @param array  $time
     *
     * @return array
     */
    public function statics($select = null, $group = null, $time = null)
    {
        $this->filterSelect($select)
             ->filterGroup($group)
             ->filterTime($time);

        $ret = [];

        foreach ($this->select as $queryKey) {
            $_ = $this->query($queryKey);
            if (!empty($_)) {
                $ret[static::STATICS_SELECT[$queryKey]] =
                    $_->keyBy('time')->toBase();
            }
        }

        /**
         * @var \Zdp\BI\Services\Format\GroupOn $format
         */
        $format = \App::make(
            'Zdp\BI\Services\Format\GroupOn',
            [$this->group, $this->time]
        );

        return $format->format($ret);
    }

    /**
     * 处理 选取数据
     *
     * @param array $select
     *
     * @return $this
     */
    protected function filterSelect($select = null)
    {
        if (empty($select)) {
            return $this;
        }

        $select = array_intersect($select, array_keys(static::STATICS_SELECT));

        if (!empty($select)) {
            $this->select = $select;
        }

        return $this;
    }

    /**
     * 处理 时间限制
     *
     * @param array $time
     *
     * @return $this
     */
    protected function filterTime($time = null)
    {
        if (empty($time)) {
            return $this->useDefaultTime();
        }

        $this->time = [
            Carbon::createFromFormat('Y-m-d H:i:s', $time[0]),
            Carbon::createFromFormat('Y-m-d H:i:s', $time[1]),
        ];

        sort($this->time);

        return $this;
    }

    /**
     * @return $this
     */
    protected function useDefaultTime()
    {
        $now = Carbon::now();

        switch ($this->group) {
            case 'hour':
                $this->time = [$now->copy()->subDays(2), $now];
                break;
            case 'day':
            default:
                $this->time = [$now->copy()->subWeeks(2), $now];
                break;
        }

        return $this;
    }

    /**
     * 处理 日期分组
     *
     * @param null $group
     *
     * @return $this
     */
    protected function filterGroup($group = null)
    {
        if (empty($group)) {
            return $this;
        }

        if (array_key_exists($group, static::STATICS_GROUP)) {
            $this->group = $group;
        }

        if (in_array('ordered.seller', $this->select)
            || in_array('modified.seller', $this->select)
        ) {
            $this->group = 'day';
        }

        return $this;
    }

    /**
     * 请求数据
     *
     * @param string $select
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function query($select)
    {
        $query = DpOpderForm::query();

        switch ($select) {
            case 'ordered.money':
                $query->where('buy_way', DpOpderForm::CENTRALIZED_BUY)
                      ->where('method', DpOpderForm::ORDER_PAY_METHOD_COMPANY)
                      ->selectRaw('sum(`total_price`) as `money`');
                break;

            case 'paid.money':
                $query->where('buy_way', DpOpderForm::CENTRALIZED_BUY)
                      ->where('method', DpOpderForm::ORDER_PAY_METHOD_COMPANY)
                      ->whereIn('orderact', DpOpderForm::PAID_ORDER_STATUS)
                      ->selectRaw('sum(`total_price`) as `money`');
                break;

            case 'ordered.num':
                $query->where('buy_way', DpOpderForm::CENTRALIZED_BUY)
                      ->where('method', DpOpderForm::ORDER_PAY_METHOD_COMPANY)
                      ->selectRaw('sum(`good_count`) as `num`');
                break;

            case 'paid.num':
                $query->where('buy_way', DpOpderForm::CENTRALIZED_BUY)
                      ->where('method', DpOpderForm::ORDER_PAY_METHOD_COMPANY)
                      ->whereIn('orderact', DpOpderForm::PAID_ORDER_STATUS)
                      ->selectRaw('sum(`good_count`) as `num`');
                break;

            case 'ordered.count':
                $query->where('buy_way', DpOpderForm::CENTRALIZED_BUY)
                      ->where('method', DpOpderForm::ORDER_PAY_METHOD_COMPANY)
                      ->selectRaw('count(`id`) as `count`');
                break;

            case 'paid.count':
                $query->where('buy_way', DpOpderForm::CENTRALIZED_BUY)
                      ->where('method', DpOpderForm::ORDER_PAY_METHOD_COMPANY)
                      ->whereIn('orderact', DpOpderForm::PAID_ORDER_STATUS)
                      ->selectRaw('count(`id`) as `count`');
                break;

            case 'modified.num':
                $query
                    ->where(
                        'dp_opder_form.buy_way',
                        DpOpderForm::CENTRALIZED_BUY
                    )
                    ->where(
                        'dp_opder_form.method',
                        DpOpderForm::ORDER_PAY_METHOD_COMPANY
                    )
                    ->join(
                        'dp_cart_info',
                        'dp_opder_form.order_code',
                        '=',
                        'dp_cart_info.coid'
                    )
                    ->whereRaw(
                        'dp_cart_info.goods_price <> dp_cart_info.good_new_price'
                    )
                    ->selectRaw('sum(`dp_cart_info`.`buy_num`) as `num`');
                break;

            case 'ordered.seller':
            case 'modified.seller':
                return $this->querySeller($select);

            default:
                return null;
        }

        switch ($this->group) {
            case 'minute':
                $format = '%Y-%m-%d %H:%i';
                break;

            case 'hour':
                $format = '%Y-%m-%d %k点';
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

            case 'day':
            default:
                $format = $format = '%Y-%m-%d';
                break;
        }

        $query
            ->selectRaw(
                'DATE_FORMAT(`dp_opder_form`.`addtime`, ?) as `time`',
                [$format]
            )
            ->groupBy('time');

        $query->where('dp_opder_form.addtime', '>', $this->time[0])
              ->where('dp_opder_form.addtime', '<=', $this->time[1]);

        return $query->get();
    }

    /**
     * @param $select
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function querySeller($select)
    {
        $query = DpGoodsGrouponConfirmLog
            ::where('created', '>', $this->time[0])
            ->where('created', '<=', $this->time[1])
            ->selectRaw(
                'DATE_FORMAT(`created`, ?) as `time`',
                ['%Y-%m-%d']
            )
            ->groupBy('time')
            ->selectRaw('count(distinct shop_id) as seller');

        switch ($select) {
            case 'ordered.seller':
                break;

            case 'modified.seller':
                $query->whereRaw('original <> modified');
                break;
        }

        return $query->get();
    }
}