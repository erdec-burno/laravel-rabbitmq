<?php

namespace App\Queue\Jobs;

use App\Services\OrderMessageHandler;
use JsonException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob as BaseRabbitMQJob;

class IncomingOrderRabbitMQJob extends BaseRabbitMQJob
{
    /**
     * Fire the job.
     *
     * @throws JsonException
     */
    public function fire(): void
    {
        $payload = $this->payload();

        ($this->instance = $this->resolve(OrderMessageHandler::class))->handle($payload);

        $this->delete();
    }

    /**
     * Get the decoded body of the job.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function payload(): array
    {
        return json_decode($this->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Get the name of the job.
     */
    public function getName(): string
    {
        return 'incoming-order';
    }
}
