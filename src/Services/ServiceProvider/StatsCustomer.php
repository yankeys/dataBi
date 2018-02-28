<?php

namespace Zdp\BI\Services\ServiceProvider;

use App\Exceptions\AppException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Zdp\BI\Models\SpCustomer;
use Carbon\Carbon;
use Zdp\ServiceProvider\Data\Models\ServiceProvider;
use Zdp\ServiceProvider\Data\Models\ShopType;

/**
 * Class StatsService
 *
 * @property array  $time   日期限制
 * @property string $group  分组方式
 * @property array  $split  分裂项
 * @property array  $filter 筛选项
 */
class StatsCustomer
{
    /**
     * @var Builder
     */
    protected $statisticQuery;
    protected $statisticOption = [];
    protected $customerFilter  = [
        'province',
        'city',
        'district',
        'type',
        'sp_id',
        'sp_name',
        'sp_shop',
        'wechat_account',
    ];
    protected $customSplit     = [
        'province',
        'city',
        'district',
        'type',
        'sp_id',
        'sp_name',
        'sp_shop',
        'wechat_account',
    ];
    protected $customerGroup   = [
        'day',
        'week',
        'month',
        'year',
        'none',
    ];

    /**
     * 服务商管理(统计列表/搜索)
     *
     * @param      $page
     * @param      $size
     * @param null $searchType
     * @param null $content
     *
     * @return array
     */
    public function index(
        $page,
        $size,
        $searchType = null,
        $content = null
    ) {
        $query = ServiceProvider::query();

        if (!empty($searchType)) {
            switch ($searchType) {
                case 1:
                    $query->where('shop_name', 'like', '%' . $content . '%');
                    break;
                case 2:
                    $query->where('mobile', $content);
                    break;
            }
        }

        $allProviders = $query->where('status', ServiceProvider::PASS)
                              ->paginate($size, ['*'], null, $page);

        $applies = array_map(function ($a) {
            return self::formatForAdmin($a);
        }, $allProviders->items());

        return [
            'list'      => $applies,
            'total'     => $allProviders->total(),
            'current'   => $allProviders->currentPage(),
            'last_page' => $allProviders->lastPage(),
        ];
    }

    protected function formatForAdmin(ServiceProvider $provider)
    {
        return [
            'uid'          => $provider->zdp_user_id,
            'shop_name'    => $provider->shop_name,
            'user_name'    => $provider->user_name,
            'mobile'       => $provider->mobile,
            'province'     => $provider->province,
            'city'         => $provider->city,
            'district'     => $provider->county,
            'market_ids'   => $provider->market_ids,
            'customer_num' => $provider->customerNum,
        ];
    }

    /**
     * 客户统计页面
     *
     * @param null       $mobile
     * @param null       $group
     * @param array|null $time
     * @param array|null $filter
     * @param array|null $split
     * @param bool       $withTotal
     *
     * @return array
     * @throws AppException
     */
    public function customerStats(
        $mobile = null,
        $group = null,
        array $time = null,
        array $filter = null,//筛选
        array $split = null  //分裂项
    )
    {
        $this->parseGroup($group)->parseStatisticTime($time);
        // 验证电话号码
        if ($mobile) {
            // 获取当前mobile的服务商id
            $id = ServiceProvider::where('mobile', $mobile)
                                 ->value('zdp_user_id');
            if (!$id) {
                throw new AppException('没有与当前手机号码相对应服务商');
            }

            $this->filter = [['sp_id' => [$id]]];
        } else {
            $this->parseStatisticFilter($filter);   //过滤筛选项
        }
        $this->parseSplit($split);      //过滤分裂项
        $this->statisticQuery = SpCustomer::query()
            // 2017-06-15 添加未完成注册用户的筛选
                                          ->where('type', '<>', '未选择');
        $this->handleFilter();
        $rawQuery = clone $this->statisticQuery;

        $this->handleTime()->handleSort($this->split);
        $query = clone $this->statisticQuery;

        $total = $query->count('id');
        $this->statisticQuery = clone $rawQuery;

        if (!empty($this->split)) {
            $this->handleSplit();
        }

        $this->statisticQuery->selectRaw('COUNT(`id`) AS `number`');

        $allQuery = clone $this->statisticQuery;
        $this->handleGroup();
        $this->handleTime();

        $data = $this->statisticQuery->get();
        if (!empty($this->split)) {
            $data = $data->groupBy(function ($item) {
                return implode('-',
                    array_intersect_key($item->toArray(), $this->split));
            });

            if (!empty($this->split)) {
                $allData = $allQuery->get()->keyBy(function ($item) {
                    return implode('-',
                        array_intersect_key($item->toArray(), $this->split));
                });
                foreach ($allData as $groupKey => $row) {
                    if (!empty($data[$groupKey])) {
                        $data[$groupKey][] = [
                            'time'   => '合计',
                            'number' => $data[$groupKey]->sum('number'),
                        ];
                    } else {
                        $data[$groupKey] = new Collection([
                            [
                                'time'   => '合计',
                                'number' => 0,
                            ],
                        ]);
                    }
                    $data[$groupKey][] = [
                        'time'   => '总计',
                        'number' => $row['number'],
                    ];
                }
            }
        }
        /* @var \Zdp\BI\Services\Format\FormatForSp $format */
        $format = \App::make(\Zdp\BI\Services\Format\FormatForSp::class);
        $reArr = $format->format(
            $this->time, $this->group, $this->split, ['number'],
            $data->toArray()
        );

        return [
            'total'    => $total,
            'sort_num' => $this->sortData,
            'detail'   => $reArr,
        ];
    }

    protected function parseGroup($group = null)
    {
        if (in_array($group, $this->customerGroup) !== false) {
            $this->group = $group;
        } else {
            $this->group = 'day';
        }

        return $this;
    }

    protected function parseStatisticTime(array $time = null)
    {
        self::parseFilterTime($time);
        $this->ensureTimeRange();

        return $this;
    }

    protected function parseStatisticFilter(array $filter = null)
    {
        $tmp = [];

        if (!empty($filter)) {
            $filter = array_filter($filter, function ($key) use ($filter) {
                return $filter[$key] != null;
            }, ARRAY_FILTER_USE_KEY);
            foreach ($filter as $name => $val) {
                if (in_array($name, $this->customerFilter)) {
                    $tmp[] = [$name => $val];
                }
            }
        }

        $this->filter = $tmp;

        return $this;
    }

    protected function parseSplit(array $split = null)
    {
        if (empty($split)) {
            $this->split = null;

            return $this;
        }
        $tmp = [];
        foreach ($split as $splits) {
            $tmp[] = array_filter((array)$splits, function ($key) {
                return in_array($key, $this->customSplit);
            }, ARRAY_FILTER_USE_KEY);
        }
        foreach ($tmp as $splits) {
            foreach ($splits as $k_split => $v_split) {
                $tmpSplit[$k_split] = $v_split[0];
            }
        }
        $this->split = $tmpSplit;

        return $this;
    }

    protected function handleSplit()
    {
        if (array_key_exists('sp_name', $this->split)) {
            $this->split['sp_id'] = $this->split['sp_name'];
            unset($this->split['sp_name']);
        }

        foreach ($this->split as $name => $value) {
            $this->handleSplitItem($name, $value);
        }

        return $this;
    }

    protected function handleSplitItem($name, $value)
    {
        switch ($name) {
            case 'sp_name':
            case 'sp_id':
                // 按照服务商名称进行分组
                if ($value != 1) {
                    return;
                }
                $this->statisticQuery->groupBy('sp_id')
                                     ->addSelect('sp_id');
                break;

            case 'province':
            case 'city':
            case 'district':
            case 'type':
            case 'sp_shop':
            case 'wechat_account':
            default:
                break;
        }
    }

    protected function handleFilter()
    {
        foreach ($this->filter as $filter) {
            foreach ($filter as $name => $value) {
                $this->handleFilterItem($name, $value);
            }
        }

        return $this;
    }

    protected function handleFilterItem($name, $value)
    {
        if (is_numeric($value) || empty($value)) {
            return;
        }

        if (!in_array($name, $this->customerFilter)) {
            return;
        }

        $value = (array)$value;
        $this->statisticQuery->whereIn($name, $value);
    }

    protected function handleGroup()
    {
        switch ($this->group) {
            case 'day':
                $format = '%Y-%m-%d';
                break;

            case 'week':
                $format = '%x-%v';
                break;

            case 'month':
                $format = '%Y-%m';
                break;

            case 'year':
                $format = '%Y';
                break;

            case 'none':
                break;
        }

        if (!empty($format)) {
            $this->statisticQuery
                ->selectRaw(
                    'DATE_FORMAT(`date`, ?) as `time`',
                    [$format]
                )
                ->groupBy('time');
        }

        return $this;
    }

    /**
     * 根据分组方式定义默认时间分组
     */
    protected function ensureTimeRange()
    {
        if (empty($this->time)) {
            return;
        }

        /**
         * @var Carbon $start
         * @var Carbon $end
         */
        $start = $this->time[0];
        $end = $this->time[1];

        if (empty($start) || empty($end)) {
            return;
        }

        switch ($this->group) {
            case 'week':
                $this->time = [$start->startOfWeek(), $end->endOfWeek()];
                break;

            case 'month':
                $this->time = [$start->startOfMonth(), $end->endOfMonth()];
                break;

            case 'year':
                $this->time = [$start->startOfYear(), $end->endOfYear()];
                break;

            case 'day':
            default:
                $this->time = [$start->startOfDay(), $end->endOfDay()];
                break;
        }
    }

    protected function parseFilterTime(array $time = null)
    {
        if (empty($time)) {
            $this->time = [
                Carbon::now()->subDays(7),
                Carbon::now(),
            ];
        } elseif (count($time) == 1) {
            $this->time = [
                new Carbon($time[0]),
                new Carbon($time[0]),
            ];
        } else {
            $tmp = [
                new Carbon($time[0]),
                new Carbon($time[1]),
            ];
            sort($tmp);

            $this->time = $tmp;
        }

        return $this;
    }

    protected function handleTime()
    {
        list($start, $end) = $this->time;
        $this->statisticQuery->where('date', '>=', $start)
                             ->where('date', '<=', $end);

        return $this;
    }

    protected function handleSort($splits)
    {
        $sortNum = [];
        $splits = ShopType::select('type_name as type')->get()->toArray();
        foreach ($splits as $split) {
            foreach ($split as $name => $value) {
                $query = clone $this->statisticQuery;
                $sortNum[$value] = $query->where($name, $value)->count('id');
            }
        }

        $this->sortData = $sortNum;

        return $this;
    }
}