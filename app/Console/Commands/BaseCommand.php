<?php

namespace app\Console\Commands;

use Illuminate\Console\Command;

abstract class BaseCommand extends Command
{
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info('Starting: ' . $this->signature);
        $this->executeCommand();
        $executionTime = round(microtime(true) - $startTime, 2);
        $this->info('Finished in ' . $executionTime . ' seconds.');
    }

    /**
     * @return mixed
     */
    abstract protected function executeCommand();
}
