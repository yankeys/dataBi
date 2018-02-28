<?php

namespace Zdp\BI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Zdp\BI\Services\GroupOn\Basic;
use Zdp\BI\Services\GroupOn\Rank;

/**
 * Class GroupOn
 *
 * 集中采购统计
 *
 * @package Zdp\BI\Http\Controllers
 */
class GroupOn extends Controller
{
    /**
     * 集中采购订单统计
     *
     * @param Request $request
     * @param Basic   $basic
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Basic $basic)
    {
        $this->validate(
            $request,
            [
                'select' => 'array',
                'group'  => 'in:hour,day,week,month,year',
                'time'   => 'array|size:2',
                'time.0' => 'required_with:time.1|date_format:"Y-m-d H:i:s"',
                'time.1' => 'required_with:time.0|date_format:"Y-m-d H:i:s"',
            ],
            [
                'select.array' => '取值必须为数组',
                'group.in'     => '分组方式错误',
            ]);

        $statistic = $basic->statics(
            $request->input('select'),
            $request->input('group'),
            $request->input('time')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $statistic,
        ]);
    }

    /**
     * 集中采购排行榜
     *
     * @param Request $request
     * @param Rank    $rank
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function rank(Request $request, Rank $rank)
    {
        $this->validate(
            $request,
            [
                'type'    => 'in:seller,seller.detail,buyer,goods',
                'order'   => 'array',
                'order.0' => 'required_with:order.1|in:deal_money,percentage',
                'order.1' => 'required_with:order.0|in:asc,desc',
                'other'   => 'array',
                'time'    => 'array',
                'time.0'  => 'required_with:time.1|date_format:"Y-m-d H:i:s"',
                'time.1'  => 'required_with:time.0|date_format:"Y-m-d H:i:s"',
                'page'    => 'integer',
                'size'    => 'integer|between:1,100',
            ]
        );

        $list = $rank->page(
            $request->input('type', 'seller'),
            $request->input('order', ['deal_money', 'desc']),
            $request->input('page', 1),
            $request->input('size', 100),
            $request->input('time', null),
            $request->input('other', null)
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $list,
        ]);
    }
}