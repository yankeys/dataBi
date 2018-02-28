<?php

namespace Zdp\BI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Zdp\BI\Services\Shop\RankShangHu;
use Zdp\BI\Services\Shop\RankShop;
use Zdp\BI\Services\Shop\Filter;
use Zdp\BI\Services\Shop\Shop as ShopService;

class Shop extends Controller
{
    /**
     * 店铺统计筛选项
     *
     * @param Request $request
     * @param Filter  $filter
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function filter(Request $request, Filter $filter)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',    // 查询时间范围
                'time.0' => 'date',
                'time.1' => 'date',
                'select' => 'array',     // 选择获取项
            ]
        );
        $data = $filter->filter(
            $request->input('time'),
            $request->input('select')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 根据传入省/市 获取下级区域/市场信息
     *
     * @param Request $request
     * @param Filter  $filter
     *
     * @return \Illuminate\Http\Response
     */
    public function series(Request $request, Filter $filter)
    {
        $this->validate(
            $request,
            [
                'areaName' => 'required|string',
                'role'     => 'required|in:province,city',
                'type'     => 'string|in:area,market',
            ],
            [
                'areaName.required' => '查询父级区域必须有',
                'role.required'     => '输入区域信息的类型必须填入',
                'type.required'     => '选择获取信息类型必须有',
            ]
        );

        $data = $filter->series(
            $request->input('areaName'),
            $request->input('role'),
            $request->input('type', 'area')
        );

        return $this->render('call.list', $data, 'OK');
    }

    /**
     * 店铺统计(店铺统计总计)
     *
     * @param Request     $request
     * @param ShopService $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function total(Request $request, ShopService $service)
    {
        $this->validate(
            $request,
            [
                // 时间限制
                'time'   => 'array',
                'time.0' => 'date',
                'time.1' => 'date',
                // 统计需要分组统计的字段
                'select' => 'string|in:trenchnum,state,business,license_act',
                'filter' => 'array', // 筛选项
            ]
        );

        $data = $service->total(
            $request->input('select'),
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
     * 店铺统计(详细数据)
     *
     * @param Request     $request
     * @param ShopService $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail(Request $request, ShopService $service)
    {
        $this->validate(
            $request,
            [
                // 时间限制
                'time'   => 'array',
                'time.0' => 'date',
                'time.1' => 'date',
                // 时间分组方式 可按照 日/周/月/年/不分割 分割
                'group'  => 'string|in:day,week,month,year,none',
                'split'  => 'array',    // 分裂项
                'filter' => 'array',    // 筛选项
            ]
        );

        $data = $service->detail(
            $request->input('group'),
            $request->input('time'),
            $request->input('split'),
            $request->input('filter')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 店铺排名
     *
     * @param Request  $request
     * @param RankShop $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function rank(Request $request, RankShop $service)
    {
        $this->validate(
            $request,
            [
                'time'       => 'array',    // 时间限制
                'time.0'     => 'date',
                'time.1'     => 'date',
                'filter'     => 'array',    // 筛选项
                'group'      => 'string|in:province,city,county',
                'is_shenghe' => 'string|in:is',
                'page'       => 'integer|min:1|max:99999',
                'size'       => 'integer|min:1|max:50',
            ]
        );

        $data = $service->rank(
            $request->input('time'),
            $request->input('filter'),
            $request->input('group', 'province'),
            $request->input('is_shenghe'),
            $request->input('page', 1),
            $request->input('size', 20)
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }
}