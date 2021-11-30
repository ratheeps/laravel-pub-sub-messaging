<?php


namespace Ratheeps\PubSubMessaging\Queue\Jobs;

use Aws\Sqs\SqsClient;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\SqsJob as ParentSqsJob;
use Illuminate\Support\Arr;
use Ratheeps\PubSubMessaging\Queue\JobMap;

class SqsJob extends ParentSqsJob
{
    /** @var JobMap  */
    private $map;

    /**
     * SnsJob constructor.
     * @param Container $container
     * @param SqsClient $sqs
     * @param array $job
     * @param string $connectionName
     * @param string $queue
     * @param JobMap $map
     */
    public function __construct(Container $container, SqsClient $sqs, array $job, string $connectionName, string $queue, JobMap $map)
    {
        parent::__construct($container, $sqs, $job, $connectionName, $queue);
        $this->map = $map;
    }

    /**
     * @return false|string
     * @throws Exception
     */
    public function getRawBody()
    {
        $realBody = json_decode(Arr::get($this->job, 'Body'), true);

        if (!isset($realBody['TopicArn'])){
            return $this->job['Body'];
        }

        $class = $this->map->fromTopic($realBody['TopicArn']);
        $message = json_decode(Arr::get($realBody, 'Message'), true);

        return json_encode([
            "job" => "Illuminate\Queue\CallQueuedHandler@call",
            "data" => [
                "commandName" => $class,
                "command" => serialize(new $class($message))
            ]
        ]);
    }
}

