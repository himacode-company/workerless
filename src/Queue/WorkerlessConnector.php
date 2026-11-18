<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Queue;

use HimaCode\Workerless\Support\Installer;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\Connectors\ConnectorInterface;

/**
 * Builds the Workerless queue from the `queue.connections.workerless` config.
 */
class WorkerlessConnector implements ConnectorInterface
{
    public function __construct(
        protected ConnectionResolverInterface $connections,
        protected Installer $installer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): Queue
    {
        // Tables must exist before the first job is stored (a file check after the first time).
        $this->installer->ensure();

        return new WorkerlessQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'] ?? 'jobs',
            $config['queue'] ?? 'default',
            $config['retry_after'] ?? 90,
            $config['after_commit'] ?? false,
        );
    }
}
