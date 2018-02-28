<?php

namespace Zdp\BI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Zdp\BI\Services\SaleRank\Filter;
use Zdp\BI\Services\SaleRank\RankGoods;
use Zdp\BI\Services\SaleRank\RankShop;

class SaleRank extends Controller
{
    /**
     * 商品排名的筛选项获取
     */
    public function gfilter(Request $request, RankGoods $service)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',    // 时间限制
                'time.0' => 'date',
                'time.1' => 'date',
                'filter' => 'array',    // 筛选项
                'column' => 'string|in:name,brand,sort',
                'order'  => 'string|in:num,amount',
                'page'   => 'integer|min:1|max:99999',
                'size'   => 'integer|min:1|max:50',
            ]
        );
        $data = $service->filter(
            $request->input('time'),
            $request->input('filter'),
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
     * 商品排名
     *
     * @param Request   $request
     * @param RankGoods $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function grank(Request $request, RankGoods $service)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',    // 时间限制
                'time.0' => 'date',
                'time.1' => 'date',
                'filter' => 'array',    // 筛选项
                'column' => 'string|in:name,brand,sort',
                'sc'     => 'string|in:asc,desc',
                'order'  => 'string|in:num,amount',
                'page'   => 'integer|min:1|max:99999',
                'size'   => 'integer|min:1|max:50',
            ]
        );

        $data = $service->rank(
            $request->input('time'),
            $request->input('filter'),
            $request->input('column', 'name'),
            $request->input('order', 'num'),
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
     * 销售排行筛选项
     */
    public function saleFilter(Request $request, Filter $service)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',    // 时间限制
                'time.0' => 'date',
                'time.1' => 'date',
                'type'   => 'string|in:buyer,seller',
                'column' => 'array',    // 筛选项
            ]
        );

        $data = $service->filter(
            $request->input('time'),
            $request->input('type','buyer'),
            $request->input('column')
        );

        return response()->json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * 整体销售排名
     *
     * @param Request  $request
     * @param RankShop $service
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function saleRank(Request $request, RankShop $service)
    {
        $this->validate(
            $request,
            [
                'time'   => 'array',    // 时间限制
                'time.0' => 'date',
                'time.1' => 'date',
                'filter' => 'array',    // 筛选项
                'column' => 'string|in:shopid,uid,province,city,county',
                'type'   => 'string|in:shop,area',
                'sc'     => 'string|in:desc,asc',
                'order'  => 'string|in:num,amount,order',
                'page'   => 'integer|min:1|max:99999',
                'size'   => 'integer|min:1|max:50',
            ]
        );

        $data = $service->rank(
            $request->input('time'),
            $request->input('filter'),
            $request->input('column', 'shopid'),
            $request->input('type', 'shop'),
            $request->input('sc', 'desc'),
            $request->input('order', 'num'),
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