<?php

namespace Zdp\BI\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Zdp\BI\Services\Sync\Order;

/**
 * 将订单数据同步到BI数据库中
 *
 * @package Zdp\BI\Commands
 */
class SyncOrder extends Command
{
    const CACHE_KEY = 'last_time_sync_order';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bi:sync:order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步订单数据到分析系统中';

    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar
     */
    protected $processBar;

    /**
     * Execute the console command.
     *
     * @param Order $service
     *
     * @return mixed
     */
    public function handle(Order $service)
    {
        $now = Carbon::now();

        $service->sync($this->getLastSyncTime(), [$this, 'onProcess']);

        $this->setSyncTime($now);
    }

    /**
     * @param \Exception|int|null $signal
     */
    public function onProcess($signal = null)
    {
        if (is_null($signal)) {
            $this->processBar->advance();
        }

        if (is_numeric($signal)) {
            $this->processBar = $this->output->createProgressBar($signal);
        }

        if ($signal instanceof \Exception) {
            $this->error($signal->getMessage() . "\n" .
                         $signal->getTraceAsString());
            $this->processBar->advance();
        }
    }

    protected function getLastSyncTime()
    {
        return \Cache::get(self::CACHE_KEY);
    }

    protected function setSyncTime(Carbon $time = null)
    {
        if (empty($time)) {
            $time = Carbon::now();
        }

        \Cache::forever(self::CACHE_KEY, $time);
    }
}
