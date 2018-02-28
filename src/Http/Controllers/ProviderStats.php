<?php

namespace Zdp\BI\Http\Controllers;

use Zdp\BI\Services\ServiceProvider\Rank;
use Zdp\BI\Services\ServiceProvider\StatsCustomer;
use Zdp\BI\Services\ServiceProvider\StatsFilter;
use Zdp\BI\Services\ServiceProvider\StatsOrder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProviderStats extends Controller
{
    protected $customerService;
    protected $orderService;
    protected $filterService;
    protected $rank;

    public function __construct(
        StatsCustomer $customerService,
        StatsOrder $orderService,
        StatsFilter $statsFilter,
        Rank $rank
    ) {
        $this->customerService = $customerService;
        $this->orderService = $orderService;
        $this->filterService = $statsFilter;
        $this->rank = $rank;
    }

    /**
     * 查看服务商列表
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->validate(
            $request,
            [
                'search_type' => 'in:1,2',
                'content'     => 'required_with:1,2',
                'page'        => 'integer|min:1',
                'size'        => 'integer|min:10|max:50',
            ]
        );
        $list = $this->customerService->index(
            $request->input('page', 1),
            $request->input('size', 20),
            $request->input('search_type'),
            $request->input('content')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $list,
        ]);
    }

    /**
     * 客户统计获取筛选项
     */
    public function filter(Request $request)
    {
        $this->validate(
            $request,
            [
                'project' => 'required|in:customer,else',
                'time'    => 'array',
                'time.0'  => 'date',
                'time.1'  => 'date',
                'filter'  => 'array',
            ]
        );
        $filter = $this->filterService->filter(
            $request->input('project'),
            $request->input('time'),
            $request->input('filter')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $filter,
        ]);
    }

    /**
     * 客户统计
     */
    public function customers(Request $request)
    {
        $this->validate(
            $request,
            [
                // 时间限制
                'time'   => 'array',
                'time.0' => 'date',
                'time.1' => 'date',
                // 传入查询的电话号码
                'mobile' => 'regex:/^1[34578][0-9]{9}$/',
                // 时间分组方式 可按照 日/周/月/年/不分割 分割
                'group'  => 'string|in:day,week,month,year,none',

                'split'  => 'array', // 分裂项
                'filter' => 'array', // 筛选项
            ]
        );

        $data = $this->customerService->customerStats(
            $request->input('mobile'),
            $request->input('group'),
            $request->input('time'),
            $request->input('filter'),
            $request->input('split')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }


    /**
     * 订单统计
     */
    public function order(Request $request)
    {
        $this->validate(
            $request,
            [
                // 时间限制
                'time'   => 'array',
                'time.0' => 'date',
                'time.1' => 'date',
                // 传入查询的电话号码
                'mobile' => 'regex:/^1[34578][0-9]{9}$/',
                // 时间分组方式 可按照 日/周/月/年/不分割 分割
                'group'  => 'string|in:day,week,month,year,none',

                'split'  => 'array', // 分裂项
                'filter' => 'array', // 筛选项
                'show'   => 'array', // 显示项
                'select' => 'array', // 选择项
            ]
        );
        $data = $this->orderService->orderStats(
            $request->input('mobile'),
            $request->input('group'),
            $request->input('time'),
            $request->input('filter'),
            $request->input('split'),
            $request->input('show'),
            $request->input('select')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 订单排行
     */
    public function orank(Request $request)
    {
        $this->validate(
            $request,
            [
                // 时间限制
                'time'     => 'array',
                'time.0'   => 'date',
                'time.1'   => 'date',
                // 选择统计规则
                'rule'     => 'required|string|in:amount,num,avg',
                // 选择对比纬度
                'contrast' => 'required|string',
                'sc'       => 'string|in:asc,desc',
                'page'     => 'integer|min:1',
                'size'     => 'integer|min:10|max:50',
            ]
        );

        $data = $this->rank->orderRank(
            $request->input('rule'),
            $request->input('contrast'),
            $request->input('time'),
            $request->input('sc','desc'),
            $request->input('page', 1),
            $request->input('size', 20)
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 商品排行
     */
    public function grank(Request $request)
    {
        $this->validate(
            $request,
            [
                // 时间限制
                'time'   => 'array',
                'time.0' => 'date',
                'time.1' => 'date',
                'show'   => 'array',
                // 选择统计规则
                'rule'   => 'required|string|in:amount,num',
                // 选择筛选项
                'select' => 'array',
                'sc' => '',
                'page'   => 'integer|min:1',
                'size'   => 'integer|min:10|max:50',
            ]
        );

        $data = $this->rank->goodsRank(
            $request->input('rule'),
            $request->input('select'),
            $request->input('time'),
            $request->input('show'),
            $request->input('sc','desc'),
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
