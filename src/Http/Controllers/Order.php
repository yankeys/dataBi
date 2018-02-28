<?php

namespace Zdp\BI\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Zdp\BI\Services\Order as OrderService;

class Order extends Controller
{

    /**
     * 统计入口
     *
     * @param Request      $request
     * @param OrderService $service
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, OrderService $service)
    {
        $this->validate($request, [
            // 时间限制
            'time'   => 'array',
            'time.0' => 'date',
            'time.1' => 'date',

            // 时间分组方式 可按照 分/时/日/周/月/年/不分割 分割
            'group'  => 'string|in:minute,hour,day,week,month,year,none',

            'split'  => 'array', // 分裂项
            'select' => 'array', // 需要 SUM 的字段
            'filter' => 'array', // 筛选项
        ]);

        $statistics = $service->statistic(
            $request->input('group'),
            $request->input('time'),
            $request->input('split'),
            $request->input('select'),
            $request->input('filter')
        );

        return $this->render('user.list', $statistics);
    }

    /**
     * 获取筛选项
     *
     * @param Request      $request
     * @param OrderService $serivce
     *
     * @return \Illuminate\Http\Response
     */
    public function filter(Request $request, OrderService $serivce)
    {
        $this->validate($request, [
            'time'   => 'array',
            'time.0' => 'date',
            'time.1' => 'date',
            'filter' => 'array',
        ]);

        $filters = $serivce->filters(
            $request->input('time'),
            $request->input('filter')
        );

        return $this->render('user.list', $filters);
    }
}
