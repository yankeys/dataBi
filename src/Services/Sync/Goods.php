<?php

namespace Zdp\BI\Services\Sync;

use App\Models\DpGoodsInfo;
use Carbon\Carbon;
use Zdp\BI\Models\GoodsSnapshot;
use Zdp\BI\Models\GoodsStatistic;

class Goods
{
    const SNAPSHOT_FILTER = [
        'date',
        'sort',
        'brand',
        'province',
        'city',
        'district',
        'market',
    ];
    const DIFF_STATUS = [
        'shenghe_act',
        'on_sale',
        'overdue',
    ];

    // 获取当前所有的商品以及基本信息
    public function syncAllGoods()
    {
        DpGoodsInfo
            ::join(
                'dp_shopInfo', 'dp_goods_info.shopId', '=', 'dp_shopInfo.shopid'
            )
            ->join(
                'dp_pianqu', 'dp_shopInfo.pianquId', '=',
                'dp_pianqu.pianquId'
            )
            ->join(
                'dp_goods_types',
                'dp_goods_info.goods_type_id',
                '=',
                'dp_goods_types.id'
            )
            ->join(
                'dp_goods_basic_attributes',
                'dp_goods_info.id',
                '=',
                'dp_goods_basic_attributes.goodsid'
            )
            ->select([
                'dp_goods_info.id as goods_id',                 // 商品id
                'dp_goods_info.gname as goods_name',            // 商品名称
                'dp_goods_info.shenghe_act as examine_status',  // 商品状态
                'dp_goods_info.on_sale as shelves_status',      // 商品上下架状态
                'dp_goods_info.brand as brand_name',        // 商品品牌名称
                'dp_goods_basic_attributes.auto_soldout_time',  // 自动下架时间
                'dp_shopInfo.shopId as shop_id',            // 店铺id
                'dp_shopInfo.province as shop_province',    // 省
                'dp_shopInfo.city as shop_city',            // 市
                'dp_shopInfo.county as shop_district',      // 县/区
                'dp_pianqu.pianqu as market_name',          // 市场名字
                'dp_goods_types.sort_name',                 // 分类名字
            ])
            ->chunk(100,function ($goods){
                self::sync($goods);
            });
    }

    /**
     * 同步商品基础信息
     *
     * @param $currentGoods
     */
    protected function sync($currentGoods)
    {
        // 获取现在的日期
        $today = Carbon::yesterday()->toDateString();
        // 需要对比的字段
        $compareParam = self::SNAPSHOT_FILTER;
        $compareStatus = self::DIFF_STATUS;
        // 判断是否过期
        foreach ($currentGoods as $currentGood) {
            // 对比快照表数据准备
            $readySnapshot= self::formatAllGoods($currentGood);
            // 找出当前数据中包含空字符串的数据
            $prepareEmpty = $readySnapshot;
            unset($prepareEmpty['overdue']);
            $emptyData = array_filter($prepareEmpty,function($key)use($prepareEmpty){
                return $prepareEmpty[$key] == null;
            },ARRAY_FILTER_USE_KEY);
            if (!empty($emptyData)){
                // 如果筛选项中含有空字段，那就跳过这条数据
                continue;
            }
            // 商品所有基础信息(所有状态都转化成字段)
            $readyStatistic = self::formatAllGoods($currentGood,true);
            // 获取商品基本信息字段(分类、品牌、省市县、市场)
            $goodsBasic = self::filterData($readySnapshot,$compareParam);
            // 商品状态(删除、上架、下架、过期 等)
            $goodsStatus = self::filterData($readyStatistic,$compareStatus);
            \DB::transaction(function ()use(
                $currentGood,
                $today,
                $readySnapshot,
                $readyStatistic,
                $compareParam,
                $compareStatus,
                $goodsBasic,
                $goodsStatus
            ){
                // 判断是否已经存在该商品快照，且判断是否需要更新
                $getSnapshot = self::getRecord($currentGood->goods_id);
                if (empty($getSnapshot)) {   // 不存在这个商品的任何快照，新商品统计 +1
                    // 插入快照表
                    $snapshotData = array_merge(['new'=>1,'date' => $today,],$readySnapshot);
                    GoodsSnapshot::create($snapshotData);
                    // 获取统计表信息
                    $insertStatistic = self::getStatisticRecord($goodsBasic,$today);
                    // 统计表中所有项目数量 +1
                    $isNew = array_merge(['new'],$goodsStatus);
                    $isNew = array_filter($isNew,function ($key) use($isNew) {
                        return $isNew[$key] != null;
                    }, ARRAY_FILTER_USE_KEY);
                    self::updateNum($insertStatistic->id,$isNew,true);
                } else {        // 已经存在商品之前的快照，不是新商品
                    // 对比,返回不一样的键值
                    $existsSnapshot = $getSnapshot->toArray();
                    $diffParams = array_filter($readySnapshot, function ($key) use($readySnapshot,$existsSnapshot){
                        return $readySnapshot[$key] != $existsSnapshot[$key];
                    },ARRAY_FILTER_USE_KEY);

                    $nowStatisticRecord = self::getStatisticRecord($goodsBasic,$today);

                    if (!empty($diffParams)) {    // 商品信息有变化(基本属性或状态)
                        // 插入快照表
                        $reSnapshotData = array_merge(['date'=> $today],$readySnapshot);
                        GoodsSnapshot::create($reSnapshotData);

                        // 获取改变以前的快照数据
                        $goodsBasicPre = self::filterData($existsSnapshot,$compareParam);
                        $goodsStatusPre = self::filterData($existsSnapshot,$compareStatus);
                        // 将快照状态改为统计状态格式(审核状态，上下架状态，过期)
                        $goodsStatisticPre = self::formatStatusToStatistic($goodsStatusPre);
                        // 去掉数组中的空值
                        $goodsStatisticPre = array_filter($goodsStatisticPre,function ($key) use($goodsStatisticPre) {
                            return $goodsStatisticPre[$key] != null;
                        }, ARRAY_FILTER_USE_KEY);
                        // 获取统计数据
                        $preStatisticRecord = self::getStatisticRecord($goodsBasicPre,$today);

                        // 信息改变项
                        $diffShop = self::filterData($diffParams,$compareParam);
                        $diffStatus = self::filterData($diffParams,$compareStatus);

                        // 如果是地址信息发生改变，以前地区的统计表信息存在或创建 以前所有状态 num -1
                        if (!empty($diffShop)){
                            // 以前所在地的以前状态记录  -1
                            self::updateNum($preStatisticRecord->id,$goodsStatisticPre,false);
                            // 现在所在地现在的状态记录 +1
                            $goodsStatusNow = array_merge(['new'],$goodsStatus);
                            self::updateNum($nowStatisticRecord->id,$goodsStatusNow,true);
                        }
                        // 如果只是状态改变，则之前的状态表中记录的 num -1 改变后的状态 num+1
                        if (!empty($diffStatus) && empty($diffShop)){
                            // 以前统计信息中状态 -1 (排除 new)
                            self::updateNum($preStatisticRecord->id,$goodsStatisticPre,false);
                            // 现在的状态 +1
                            self::updateNum($nowStatisticRecord->id,$goodsStatus,true);
                        }
                    }
                }
            });
        }
    }

    // 格式化商品的基础数据
    protected function formatAllGoods(
        $goodsData,
        $isSnapshot = false
    )
    {
        if (empty($goodsData)) {
            throw new \Exception('商品基础信息获取失败');
        }

        return [
            'sort'        => $goodsData->sort_name,
            'brand'       => $goodsData->brand_name,
            'province'    => $goodsData->shop_province,
            'city'        => $goodsData->shop_city,
            'district'    => $goodsData->shop_district,
            'market'      => $goodsData->market_name,
            'shop_id'     => $goodsData->shop_id,
            'goods_id'    => $goodsData->goods_id,
            'goods_name'  => $goodsData->goods_name,
            'shenghe_act' => self::changeExamine($goodsData->examine_status,$isSnapshot),
            'on_sale'     => self::changeShelves($goodsData->shelves_status,$isSnapshot),
            'overdue'     => self::judgeOverdue($goodsData->auto_soldout_time,$isSnapshot)
        ];
    }

    // 将快照中商品状态改为统计表中的字段格式
    protected function formatStatusToStatistic($status)
    {
        if (empty($status)) {
            throw new \Exception('商品基础信息获取失败');
        }
        switch ($status['shenghe_act'])
        {
            case '待审核':
                $examineStatus = 'audit';
                break;
            case '已审核':
                $examineStatus = 'normal';
                break;
            case '已下架':
                $examineStatus = 'close';
                break;
            case '已删除':
                $examineStatus = 'delete';
                break;
            case '拒绝':
                $examineStatus = 'reject';
                break;
            case '修改待审核':
                $examineStatus = 'modify_audit';
                break;
            case '待完善':
                $examineStatus = 'perfect';
                break;
            default:
                $examineStatus = '';
        }
        switch ($status['on_sale'])
        {
            case -1 :
                $saleStatus = 'not_sale';
                break;
            case 1 :
                $saleStatus = 'sale';
                break;
            default:
                $saleStatus = '';
        }

        $status = [
            'shenghe_act' => $examineStatus,
            'on_sale'     => $saleStatus
        ];

        if ($status['overdue'] = 1)
        {
            $status['overdue'] = 'overdue';
        }

        return $status;
    }

    // 对需要插入数据库的商品上下架状态进行字符串转换
    protected function changeShelves($num,$isSnapshot = false)
    {
        $status = 0;
        if (empty($num)) {
            throw new \Exception('商品状态码不存在');
        }
        if (!$isSnapshot){
            switch ($num) {
                case DpGoodsInfo::GOODS_NOT_ON_SALE:
                    $status = -1;
                    break;
                case DpGoodsInfo::GOODS_SALE:
                    $status = 1;
                    break;
                default:
                    echo '商品上下架状态码' . $num . '未解释';
            }
        }else{
            switch ($num) {
                case DpGoodsInfo::GOODS_NOT_ON_SALE:
                    $status = 'not_sale';
                    break;
                case DpGoodsInfo::GOODS_SALE:
                    $status = 'sale';
                    break;
                default:
                    echo '商品上下架状态码' . $num . '未解释';
            }
        }

        return $status;
    }

    // 对需要插入数据库的商品审核状态进行字符串转换
    protected function changeExamine($num,$isSnapshot = false)
    {
        $string = '';
        if (empty($num)) {
            throw new \Exception('商品状态码不存在');
        }
        if (!$isSnapshot){
            switch ($num) {
                case DpGoodsInfo::STATUS_AUDIT:
                    $string = '待审核';
                    break;
                case DpGoodsInfo::STATUS_NORMAL:
                    $string = '已审核';
                    break;
                case DpGoodsInfo::STATUS_CLOSE:
                    $string = '已下架';
                    break;
                case DpGoodsInfo::STATUS_DEL:
                    $string = '已删除';
                    break;
                case DpGoodsInfo::STATUS_REJECT:
                    $string = '拒绝';
                    break;
                case DpGoodsInfo::STATUS_MODIFY_AUDIT:
                    $string = '修改待审核';
                    break;
                case DpGoodsInfo::WAIT_PERFECT:
                    $string = '待完善';
                    break;
                default:
                    echo '商品审核状态码' . $num . '未解释';
            }
        }else{
            switch ($num)
            {
                case DpGoodsInfo::STATUS_AUDIT:
                    $string = 'audit';
                    break;
                case DpGoodsInfo::STATUS_NORMAL:
                    $string = 'normal';
                    break;
                case DpGoodsInfo::STATUS_CLOSE:
                    $string = 'close';
                    break;
                case DpGoodsInfo::STATUS_DEL:
                    $string = 'delete';
                    break;
                case DpGoodsInfo::STATUS_REJECT:
                    $string = 'reject';
                    break;
                case DpGoodsInfo::STATUS_MODIFY_AUDIT:
                    $string = 'modify_audit';
                    break;
                case DpGoodsInfo::WAIT_PERFECT:
                    $string = 'perfect';
                    break;
                default:
                    echo '商品审核状态码' . $num . '未解释';
            }
        }

        return $string;
    }

    // 判断是否过期
    protected function judgeOverdue($time , $isSnapshot = false)
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        if ($isSnapshot)
        {
            if (empty($time) || !strtotime($time))
            {
                return '';
            }
            if ($time > $now) {
                $output = '';
            }else{
                $output = 'overdue';
            }
        } else{
            if (empty($time) || !strtotime($time))
            {
                return 0;
            }
            if ($time > $now) {
                $output = -1;
            } else {
                $output = 1;
            }
        }

        return $output;
    }

    // 获取单个的商品距离现在时间最近的快照
    protected function getRecord($param, $isSnapshot = false)
    {
        if ($isSnapshot){
            $data = GoodsStatistic::where('sort', $param['sort'])
                                  ->where('brand', $param['brand'])
                                  ->where('province', $param['province'])
                                  ->where('city', $param['city'])
                                  ->where('district', $param['district'])
                                  ->where('market', $param['market'])
                                  ->orderBy('id', 'desc')
                                  ->first();
        }else{
            $data = GoodsSnapshot::where('goods_id', $param)
                                 ->orderBy('id', 'desc')
                                 ->first();
        }

        return $data;
    }

    /**
     * 获取当前属性的统计信息(没有就新建)
     *
     * @param $goodsBasic
     * @param $today
     *
     * @return GoodsStatistic
     */
    protected function getStatisticRecord($goodsBasic,$today)
    {
        $historyData = self::getRecord($goodsBasic,true);
        if (empty($historyData))
        {
            $createData = array_merge(['date'=>$today],$goodsBasic);
            $insertStatistic = GoodsStatistic::create($createData);
        }else{
            // 今天已经有记录，直接返回
            if ($historyData->date == $today){
                $insertStatistic = $historyData;
            }else{  // 今天没有记录
                // 新建记录(复制昨天的统计数据)
                $existsStatistic = $historyData->toArray();
                unset($existsStatistic['id'],$existsStatistic['new']);
                $existsStatistic['date'] = $today;
                $insertStatistic = GoodsStatistic::create($existsStatistic);
            }
        }

        return $insertStatistic;
    }

    // 数据筛选
    protected function filterData($diff,$compareParam)
    {
        $diffData = array_filter($diff, function ($key) use($compareParam){
            return in_array($key,$compareParam);
        },ARRAY_FILTER_USE_KEY);

        return $diffData;
    }

    // 更新统计个数
    protected function updateNum($id,array $columns,$isAdd = false)
    {
        if ($isAdd) {
            $num = 1;
        } else {
            $num = -1;
        }
        foreach ($columns as $column) {
            GoodsStatistic::where('id', $id)
                          ->increment($column, $num);
        }
    }
}