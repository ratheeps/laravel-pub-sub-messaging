<?php

namespace Ratheeps\PubSubMessaging\Traits;

use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Container\Container;
use Ratheeps\PubSubMessaging\Queue\PubSubMessagingJobMap;

trait PubSubMessagingSqsBaseJob
{
    use ResolvesPointers;

    /**
     * The Amazon SQS client instance.
     *
     * @var SqsClient
     */
    protected $sqs;

    /**
     * The Amazon SQS job instance.
     *
     * @var array
     */
    protected $job;

    /**
     * Holds the raw body to prevent fetching the file from
     * the disk multiple times.
     *
     * @var string
     */
    protected $cachedRawBody;

    /**
     * The disk options for the job.
     *
     * @var array
     */
    protected $diskOptions;

    /**
     * @var PubSubMessagingJobMap
     */
    private $pubSubMessagingJobMap;

    /**
     * @param Container $container
     * @param SqsClient $sqs
     * @param array $job
     * @param $connectionName
     * @param $queue
     * @param array $diskOptions
     * @param PubSubMessagingJobMap $pubSubMessagingJobMap
     */
    public function __construct(
        Container $container,
        SqsClient $sqs,
        array     $job,
        $connectionName,
        $queue,
        array     $diskOptions,
        PubSubMessagingJobMap $pubSubMessagingJobMap
    )
    {
        $this->sqs = $sqs;
        $this->job = $job;
        $this->queue = $queue;
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->diskOptions = $diskOptions;
        $this->pubSubMessagingJobMap = $pubSubMessagingJobMap;
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        if (Arr::get($this->diskOptions, 'cleanup') && $pointer = $this->resolvePointer()) {
            $this->resolveDisk()->delete($pointer);
        }
    }

    /**
     * Get the raw body string for the job.
     *
     * @return false|mixed|string
     * @throws FileNotFoundException
     * @throws BindingResolutionException
     * @throws \Exception
     */
    public function getRawBody()
    {
        if ($this->cachedRawBody) {
            return $this->processMappedJobsForTopic($this->cachedRawBody);
        }

        if ($S3Payload = $this->resolveS3Payload()) {
            $body = $this->cachedRawBody = $this->retrievePayload($S3Payload);
            return $this->processMappedJobsForTopic($body);
        }
        $body = parent::getRawBody();
         return $this->processMappedJobsForTopic($body);
    }

    /**
     * @param $rawBody
     * @return false|mixed|string
     * @throws \Exception
     */
    protected function processMappedJobsForTopic($rawBody)
    {
        $realBody = json_decode($rawBody, true);
        if (!isset($realBody['TopicArn'])){
            return $rawBody;
        }

        $class = $this->pubSubMessagingJobMap->fromTopic($realBody['TopicArn']);
        $message = json_decode(Arr::get($realBody, 'Message'), true);

        return json_encode([
            "uuid" => (string) Str::uuid(),
            "job" => "Illuminate\Queue\CallQueuedHandler@call",
            "data" => [
                "commandName" => $class,
                "command" => serialize(new $class($message))
            ]
        ]);
    }
}
