<?php

namespace Zdp\BI\Commands;

use Illuminate\Console\Command;
use Zdp\BI\Services\Sync\Call;

/**
 * 将订单数据同步到BI数据库中
 *
 * @package Zdp\BI\Commands
 */
class SyncCall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bi:sync:call';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步咨询数据到统计系统中';

    /**
     * @var \Symfony\Component\Console\Helper\ProgressBar
     */
    protected $processBar;

    /**
     * Execute the console command.
     *
     * @param Call $service
     */
    public function handle(Call $service)
    {
        $service->syncAllCall();
    }
}
