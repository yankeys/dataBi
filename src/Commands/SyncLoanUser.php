<?php

namespace Zdp\BI\Commands;

use Illuminate\Console\Command;

class SyncLoanUser extends Command
{
    private $service;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan:sync-user-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步冻品贷每天白名单中用户数据';

    /**
     * Create a new command instance.
     *
     * @param \Zdp\BI\Services\Sync\SyncLoanUser $service
     */
    public function __construct(\Zdp\BI\Services\Sync\SyncLoanUser $service)
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
        $this->service->syncLoanUser();
    }
}
