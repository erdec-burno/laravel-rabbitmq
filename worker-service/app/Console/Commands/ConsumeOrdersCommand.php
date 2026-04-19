<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConsumeOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:consume-orders
                            {--queue= : Queue name override}
                            {--once : Process only one message}
                            {--stop-when-empty : Stop when the queue becomes empty}
                            {--max-jobs=0 : Maximum number of jobs to process}
                            {--max-time=0 : Maximum runtime in seconds}
                            {--memory=128 : Memory limit in megabytes}
                            {--sleep=3 : Seconds to sleep when queue is empty}
                            {--timeout=60 : Timeout for a single job}
                            {--tries=3 : Number of attempts before failing a job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume order messages from the RabbitMQ orders queue.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        return $this->call('rabbitmq:consume', [
            'connection' => 'rabbitmq',
            '--queue' => $this->option('queue') ?: config('queue.connections.rabbitmq.queue'),
            '--once' => (bool) $this->option('once'),
            '--stop-when-empty' => (bool) $this->option('stop-when-empty'),
            '--max-jobs' => (int) $this->option('max-jobs'),
            '--max-time' => (int) $this->option('max-time'),
            '--memory' => (int) $this->option('memory'),
            '--sleep' => (int) $this->option('sleep'),
            '--timeout' => (int) $this->option('timeout'),
            '--tries' => (int) $this->option('tries'),
        ]);
    }
}
