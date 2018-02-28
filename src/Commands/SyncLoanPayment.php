<?php

namespace Zdp\BI\Commands;

use Illuminate\Console\Command;

class SyncLoanPayment extends Command
{
    private $service;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan:sync-payment-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步冻品贷支付订单';

    /**
     * Create a new command instance.
     *
     * @param \Zdp\BI\Services\Sync\SyncLoanPayment $service
     */
    public function __construct(\Zdp\BI\Services\Sync\SyncLoanPayment $service)
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
        $this->service->syncLoanPayment();
    }
}
