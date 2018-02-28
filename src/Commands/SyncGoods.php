<?php

namespace Zdp\BI\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Zdp\BI\Services\Sync\Goods;

/**
 * 将订单数据同步到BI数据库中
 *
 * @package Zdp\BI\Commands
 */
class SyncGoods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bi:sync:goods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步商品数据到统计系统中';

    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar
     */
    protected $processBar;

    /**
     * Execute the console command.
     *
     * @param Goods $service
     */
    public function handle(Goods $service)
    {
        $service->syncAllGoods();
    }
}
