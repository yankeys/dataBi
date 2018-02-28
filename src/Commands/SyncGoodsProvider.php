<?php

namespace Zdp\BI\Commands;

use Zdp\BI\Services\Sync\GoodsProvider;
use Zdp\BI\Services\Sync\OrderProvider;
use Illuminate\Console\Command;

class SyncGoodsProvider extends Command
{
    private $service;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sp:sync-order-goods-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步服务商每天的订单商品';

    /**
     * Create a new command instance.
     *
     * @param GoodsProvider $service
     */
    public function __construct(GoodsProvider $service)
    {
        $this->service = $service;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->service->syncGoods();
    }
}
