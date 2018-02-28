<?php

namespace Zdp\BI\Services\Sync;

use Carbon\Carbon;
use Zdp\ServiceProvider\Data\Models\Area;
use Zdp\ServiceProvider\Data\Models\ShopType;
use Zdp\ServiceProvider\Data\Models\User as ServiceUser;
use Zdp\ServiceProvider\Data\Models\WechatAccount;
use Zdp\BI\Models\SpCustomer;

class CustomerProvider
{
    /**
     *
     * 同步每天注册用户信息
     */
    public function syncCustomer()
    {
        ServiceUser
            ::leftJoin('service_providers as sp', 'users.sp_id', '=', 'sp.zdp_user_id')
            ->where('users.updated_at', '>', self::getDatetime())
            # todo 以后如果用户的状态用起来了之后，这里添加状态的判断(判断时间方式也要修改)
            ->select([
                'users.id as user_id',
                'users.user_name',
                'users.shop_name as user_shop',
                'users.shop_type_id as user_type_id',
                'users.province_id',
                'users.city_id',
                'users.county_id',
                'users.sp_id',
                'users.created_at',

                'sp.user_name as sp_name',
                'sp.shop_name as sp_shop',
            ])
            ->chunk(50, function ($customers) {
                foreach ($customers as $customer) {
                    $info = [
                        'date'           => $customer->created_at->toDateString(),
                        'province'       => self::exchangeAddress($customer->province_id),
                        'city'           => self::exchangeAddress($customer->city_id),
                        'district'       => self::exchangeAddress($customer->county_id),
                        'type'           => self::exchangeType($customer->user_type_id),
                        'user_id'        => $customer->user_id,
                        'user_name'      => $customer->user_name ?: '未录入',
                        'user_shop'      => $customer->user_shop ?: '未录入',
                        'sp_id'          => $customer->sp_id ?: 0,
                        'sp_name'        => $customer->sp_name ?: '已删除',
                        'sp_shop'        => $customer->sp_shop ?: '已删除',
                        'wechat_account' => self::getWechatAccount($customer->sp_id),
                    ];
                    $userId = $info['user_id'];
                    $update = array_except($info, ['user_id']);
                    SpCustomer::updateOrCreate(['user_id' => $userId], $update);
                }
            });
    }

    /**
     * 区域id转换成字符串
     */
    protected function exchangeAddress($areaId)
    {
        $name = Area::where('id', $areaId)
                    ->value('name');
        if (!$name){
            $name = '未选择';
        }

        return $name;
    }

    /**
     * 店铺类型转换成字符串
     */
    protected function exchangeType($typeId)
    {
        $type = ShopType::where('id', $typeId)
                        ->value('type_name');
        if (!$type){
            $type = '未选择';
        }

        return $type;
    }

    /**
     * 获取公众号名字
     */
    protected function getWechatAccount($spId)
    {
        $wechatAccount = WechatAccount::where('sp_id', $spId)
                                      ->value('wechat_name');
        if (!$wechatAccount){
            $wechatAccount = '已删除';
        }

        return $wechatAccount;
    }

    /**
     * 获取同步命令的开始时间
     */
    protected function getDatetime()
    {
        $id = SpCustomer::orderBy('user_id', 'desc')
                        ->value('user_id');
        $time = ServiceUser::where('id', $id)
                           ->value('created_at');
        if (!$time) {
            $time = Carbon::create(2017, 1, 1)->startOfDay();
        }

        return $time;
    }
}
