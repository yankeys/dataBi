<?php

namespace Zdp\BI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Zdp\BI\Services\Loans\Payment;
use Zdp\BI\Services\Loans\User;
use Zdp\BI\Services\Loans\Filter;

class Loans extends Controller
{
    /**
     * 冻品贷用户统计筛选项
     *
     * @param Request $request
     * @param Filter  $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function filter(Request $request, Filter $service)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array'
            ], [
                'time.array'   => '时间参数需为数组'
            ]
        );
        $data = $service->filter(
            $request->input('time')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 冻品贷总人数统计
     *
     * @param Request $request
     * @param User    $user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function total(Request $request, User $user)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',
                'filter' => 'array',
            ]
        );

        $data = $user->total(
            $request->input('time'),
            $request->input('filter')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 冻品贷用户趋势
     *
     * @param Request $request
     * @param User    $user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function trend(Request $request, User $user)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',
                'filter' => 'array',
                // 时间分组方式 可按照 日/周/月/年/不分割 分割
                'group'  => 'string|in:day,week,month,year,none',
            ]
        );

        $data = $user->trend(
            $request->input('time'),
            $request->input('filter'),
            $request->input('group', 'day')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 冻品贷用户区域分析
     *
     * @param Request $request
     * @param User    $user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function customLayout(Request $request, User $user)
    {
        $this->validate(
            $request,
            [
                'time'     => 'array',
                'column'   => 'array',
                'order_by' => 'string|in:white_list,opened,paid',
            ]
        );

        $data = $user->layout(
            $request->input('time'),
            $request->input('column'),
            $request->input('order_by', 'white_list')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 冻品贷支付趋势
     *
     * @param Request $request
     * @param Payment $payment
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentTrend(Request $request, Payment $payment)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',
                'filter' => 'array',
                'show'   => 'string|in:shop_num,pay_num,amount',
                'group'  => 'string|in:day,week,month,year,none',
            ]
        );
        $data = $payment->trend(
            $request->input('time'),
            $request->input('filter'),
            $request->input('show', 'shop_num'),
            $request->input('group', 'day')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 冻品贷支付分布
     *
     * @param Request $request
     * @param Payment $payment
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentLayout(Request $request, Payment $payment)
    {
        $this->validate(
            $request,
            [
                'time'     => 'array',
                'filter'   => 'array',
                'order_by' => 'string|in:shop_num,pay_num,amount',
            ]
        );
        $data = $payment->layout(
            $request->input('time'),
            $request->input('filter'),
            $request->input('order_by', 'shop_num')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }
}