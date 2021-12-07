<?php

namespace Ratheeps\PubSubMessaging\Queue\Connectors;

use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Support\Arr;
use Ratheeps\PubSubMessaging\Queue\PubSubMessagingJobMap;
use Ratheeps\PubSubMessaging\Queue\PubSubMessagingSqsQueue;

class PubSubMessagingSqsConnector extends SqsConnector implements ConnectorInterface
{
    /**
     * @var PubSubMessagingJobMap
     */
    private $pubSubMessagingJobMap;

    /**
     * @param PubSubMessagingJobMap $pubSubMessagingJobMap
     */
    public function __construct(PubSubMessagingJobMap $pubSubMessagingJobMap)
    {
        $this->pubSubMessagingJobMap = $pubSubMessagingJobMap;
    }

    /**
     * Establish a queue connection.
     *
     * @param array $config
     * @return PubSubMessagingSqsQueue
     */
    public function connect(array $config)
    {
        $config = $this->getDefaultConfiguration($config);

        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return new PubSubMessagingSqsQueue(
            new SqsClient($config),
            $config['queue'],
            $config['disk_options'],
            $config['prefix'] ?? '',
            $config['suffix'] ?? '',
            $config['after_commit'] ?? null,
            $this->pubSubMessagingJobMap
        );
    }
}
