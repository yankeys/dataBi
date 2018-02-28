<?php

namespace Zdp\BI\Commands;

use Zdp\BI\Services\Sync\CustomerProvider;
use Illuminate\Console\Command;

class SyncCustomerProvider extends Command
{
    private $service;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sp:sync-increment-customers-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步每天新增的服务商的客户数量';

    /**
     * Create a new command instance.
     *
     * @param CustomerProvider $service
     */
    public function __construct(CustomerProvider $service)
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
        $this->service->syncCustomer();
    }
}
