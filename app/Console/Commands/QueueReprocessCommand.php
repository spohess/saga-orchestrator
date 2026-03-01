<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Supports\Queue\QueueJob;
use App\Supports\Queue\QueueMessage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\Queue;

class QueueReprocessCommand extends Command
{
    protected $signature = 'queue:reprocess {--queue= : The DLQ queue name to reprocess}';

    protected $description = 'Reprocess all jobs from a Dead Letter Queue back to the original queue';

    public function handle(Queue $queue): int
    {
        $dlqName = $this->option('queue');

        if (! $dlqName) {
            $this->error('The --queue option is required.');

            return self::FAILURE;
        }

        if (! str_ends_with($dlqName, '_dlq')) {
            $this->error('The queue name must end with "_dlq". Reprocessing non-DLQ queues is not allowed.');

            return self::FAILURE;
        }

        $reprocessed = 0;

        while ($job = $queue->pop($dlqName)) {
            $queueJob = unserialize($job->payload()['data']['command']);

            if (! $queueJob instanceof QueueJob) {
                $this->warn("Skipping non-QueueJob payload on [{$dlqName}].");
                $job->delete();

                continue;
            }

            /** @var QueueMessage $message */
            $message = $queueJob->message;
            $resetMessage = $message->withReset();

            dispatch(new QueueJob($resetMessage))->onQueue($resetMessage->getQueue());

            $job->delete();
            $reprocessed++;
        }

        if ($reprocessed === 0) {
            $this->info("No jobs found on [{$dlqName}].");

            return self::SUCCESS;
        }

        $this->info("Reprocessed {$reprocessed} job(s) from [{$dlqName}] back to their original queue.");

        return self::SUCCESS;
    }
}
