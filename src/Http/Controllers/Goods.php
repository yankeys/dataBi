<?php

namespace Zdp\BI\Http\Controllers;

use App\Http\Controllers\Controller;
use \Zdp\BI\Services\Goods as GoodsService;
use Illuminate\Http\Request;

class Goods extends Controller
{
    /**
     * 商品分布状态
     */
    public function index(Request $request, GoodsService $service)
    {
        $this->validate(
            $request,
            [
                'select' => 'array', // 需要 SUM 的字段
                'filter' => 'array', // 筛选项
            ]
        );
        $statistic = $service->index(
            $request->input('select'),
            $request->input('filter')
        );

        return $this->render('goods.list', $statistic, 'OK');
    }


    /**
     * 商品趋势变化
     *
     * @param Request      $request
     * @param GoodsService $service
     *
     * @return \Illuminate\Http\Response
     */
    public function trend(Request $request, GoodsService $service)
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
                'select' => 'array', // 需要 SUM 的字段
                'filter' => 'array', // 筛选项
                'split'  => 'array', // 分裂项
            ]
        );
        $statistic = $service->statistic(
            $request->input('group'),
            $request->input('time'),
            $request->input('select'),
            $request->input('filter'),
            $request->input('split')
        );

        return $this->render('goods.list', $statistic, 'OK');
    }

    /**
     * 获取筛选项接口
     *
     * @param Request      $request
     * @param GoodsService $service
     *
     * @return \Illuminate\Http\Response
     */
    public function filter(Request $request, GoodsService $service)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',
                'time.0' => 'date',
                'time.1' => 'date',
                'filter' => 'array',
            ]
        );
        $filters = $service->filter(
            $request->input('time'),
            $request->input('filter')
        );

        return $this->render('goods.list', $filters, 'OK');
    }

    /**
     * 筛选项中根据省市获取对应市场信息
     *
     * @param Request      $request
     * @param GoodsService $service
     *
     * @return \Illuminate\Http\Response
     */
    public function series(Request $request, GoodsService $service)
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
            ]
        );
        $data = $service->series(
            $request->input('areaName'),
            $request->input('role'),
            $request->input('type', 'area')
        );

        return $this->render('goods.list', $data, 'OK');
    }
}