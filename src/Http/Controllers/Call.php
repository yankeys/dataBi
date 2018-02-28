<?php

namespace Zdp\BI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Zdp\BI\Services\Call as CallService;

class Call extends Controller
{
    /**
     * 咨询统计入口
     *
     * @param Request     $request
     * @param CallService $service
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, CallService $service)
    {
        $this->validate($request,
            [
                // 时间限制
                'time'   => 'array',
                'time.0' => 'date',
                'time.1' => 'date',

                // 时间分组方式 可按照 分/时/日/周/月/年/不分割 分割
                'group'  => 'string|in:day,week,month,year,none',

                'split'  => 'array', // 分裂项
                'filter' => 'array', // 筛选项
            ]);
        $callLogs = $service->statistic(
            $request->input('group'),
            $request->input('time'),
            $request->input('filter'),
            $request->input('split')
        );

        return $this->render('call.list', $callLogs, 'OK');
    }

    /**
     * 咨询统计筛选项
     *
     * @param Request     $request
     * @param CallService $service
     *
     * @return \Illuminate\Http\Response
     */
    public function filter(Request $request, CallService $service)
    {
        $this->validate($request,
            [
                'time'   => 'array',
                'time.0' => 'date',
                'time.1' => 'date',
                'filter' => 'array',
            ]);

        $filters = $service->filter(
            $request->input('time'),
            $request->input('filter')
        );

        return $this->render('call.list', $filters, 'OK');
    }

    /**
     * 根据传入省/市 获取下属级联关系
     *
     * @param Request     $request
     * @param CallService $service
     *
     * @return \Illuminate\Http\Response
     */
    public function series(Request $request, CallService $service)
    {
        $this->validate(
            $request,
            [
                'areaName' => 'required|string',
                'role'     => 'required|in:buyer_province,buyer_city,seller_province,seller_city',
                'type'     => 'string|in:area,market',
            ],
            [
                'areaName.required' => '查询父级区域必须有',
                'role.required'     => '输入区域信息的类型必须填入',
            ]
        );

        $data = $service->series(
            $request->input('areaName'),
            $request->input('role'),
            $request->input('type', 'area')
        );

        return $this->render('call.list', $data, 'OK');
    }

    /**
     * 咨询排行
     *
     * @param Request     $request
     * @param CallService $service
     *
     * @return \Illuminate\Http\Response
     */
    public function rank(Request $request, CallService $service)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',
                'time.0' => 'date',
                'time.1' => 'date',
                'filter' => 'array',
                'column' => 'string|in:goods_name,buyer_type,buyer_name,buyer_province,buyer_city,buyer_district,seller_type,seller_name,seller_province,seller_city,seller_district,seller_market,goods_sort,goods_brand,call_date',
                'page'   => 'integer|min:1|max:999',
                'size'   => 'integer|min:10|max:50',
            ]
        );

        $data = $service->rank(
            $request->input('time'),
            $request->input('filter'),
            $request->input('column'),
            $request->input('page', 1),
            $request->input('size', 10)
        );

        return $this->render('call.list', $data, 'OK');
    }
}