<?php

namespace Zdp\BI\Services\Format;

use Illuminate\Support\Collection;

class GroupOn
{
    protected $group;

    protected $time;

    protected $data;

    protected $dataField = [
        '成交金额' => 'money',
        '订单金额' => 'money',

        '成交数量' => 'num',
        '下单数量' => 'num',
        '改价数量' => 'num',

        '通知卖家' => 'seller',
        '改价卖家' => 'seller',

        '报价单数' => 'count',
        '支付单数' => 'count',
    ];

    public function __construct($group, $time)
    {
        $this->group = $group;
        $this->time = $time;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function format($data)
    {
        $this->data = new Collection($data);

        $this->process();

        return $this->data->toArray();
    }

    protected function process()
    {
        list($start, $end) = $this->time;

        switch ($this->group) {
            case 'minute':
                $format = 'Y-m-d H:i';
                $func = 'addMinute';
                break;

            case 'hour':
                $format = 'Y-m-d G点';
                $func = 'addHour';
                break;

            case 'week':
                $format = 'Y年 第W周';
                $func = 'addWeek';
                break;

            case 'month':
                $format = 'Y年m月';
                $func = 'addMonth';
                break;

            case 'year':
                $format = 'Y年';
                $func = 'addYear';
                break;

            case 'day':
            default:
                $format = 'Y-m-d';
                $func = 'addDay';
                break;
        }

        $data = new Collection();

        for ($i = $start; $i <= $end; call_user_func([$i, $func])) {
            $time = $i->format($format);
            $single = new Collection();

            foreach ($this->data as $field => $item) {
                $tmp = $item->get($time);
                $fieldName = $this->dataField[$field];
                $value = empty($tmp) ? 0 : $tmp->$fieldName;
                $single->put($field, $value);
            }

            $data->put($time, $single);
        }

        $this->data = $data;

        return $this;
    }
}