<?php


namespace Ratheeps\PubSubMessaging\Queue;

use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\SqsQueue as ParentSqsQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\HigherOrderTapProxy;
use Illuminate\Support\Str;
use Ratheeps\PubSubMessaging\Queue\Jobs\PubSubMessagingSqsJob;
use Ratheeps\PubSubMessaging\Traits\ResolvesPointers;

class PubSubMessagingSqsQueue extends ParentSqsQueue
{
    use ResolvesPointers;

    /**
     * The max length of a SQS message before it must be stored as a pointer.
     *
     * @var int
     */
    public const MAX_SQS_LENGTH = 250000;

    /**
     * The Amazon SQS instance.
     *
     * @var SqsClient
     */
    protected $sqs;

    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $default;

    /**
     * The disk options to save large payloads.
     *
     * @var array
     */
    protected $diskOptions;

    /**
     * The queue URL prefix.
     *
     * @var string
     */
    protected $prefix;

    /**
     * The queue name suffix.
     *
     * @var string
     */
    private $suffix;
    /**
     * @var PubSubMessagingJobMap|null
     */
    private $pubSubMessagingJobMap;

    /**
     * @param SqsClient $sqs
     * @param string $default
     * @param array $diskOptions
     * @param string $prefix
     * @param string $suffix
     * @param bool $dispatchAfterCommit
     * @param PubSubMessagingJobMap|null $pubSubMessagingJobMap
     */
    public function __construct(
        SqsClient             $sqs,
        string                $default,
        array                 $diskOptions,
        string                $prefix = '',
        string                $suffix = '',
        bool                  $dispatchAfterCommit = false,
        ?PubSubMessagingJobMap $pubSubMessagingJobMap = null
    )
    {
        $this->sqs = $sqs;
        $this->default = $default;
        $this->diskOptions = $diskOptions;
        $this->prefix = $prefix;
        $this->suffix = $suffix;
        $this->dispatchAfterCommit = $dispatchAfterCommit;
        $this->pubSubMessagingJobMap = $pubSubMessagingJobMap;
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param null $queue
     * @param array $options
     * @param int $delay
     * @return mixed|null
     * @throws BindingResolutionException
     */
    public function pushRaw($payload, $queue = null, array $options = [], $delay = 0)
    {
        $payloadLength = strlen($payload);

        if ($payloadLength >= self::MAX_SQS_LENGTH || Arr::get($this->diskOptions, 'always_store')) {
            $uuid = (string) Str::uuid();
            $filepath = Arr::get($this->diskOptions, 'prefix', '') . "/{$uuid}.json";
            $this->resolveDisk()->put($filepath, $payload);

            return $this->sqs->sendMessage([
                'QueueUrl' => $this->getQueue($queue),
                'MessageBody' => json_encode(['pointer' => $filepath]),
                'DelaySeconds' => $this->secondsUntil($delay),
            ])->get('MessageId');
        }

        return parent::pushRaw($payload, $queue, $options);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string $job
     * @param mixed $data
     * @param string|null $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?: $this->default, $data),
            $queue,
            $delay,
            function ($payload, $queue) use ($delay) {
                return $this->pushRaw($payload, $queue, [], $delay);
            }
        );
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     *
     * @return Job|null
     */
    public function pop($queue = null)
    {
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue = $this->getQueue($queue),
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (!is_null($response['Messages']) && count($response['Messages']) > 0) {
            return new PubSubMessagingSqsJob(
                $this->container,
                $this->sqs,
                $response['Messages'][0],
                $this->connectionName,
                $queue,
                $this->diskOptions,
                $this->pubSubMessagingJobMap
            );
        }
    }

    /**
     * Delete all the jobs from the queue.
     *
     * @param string $queue
     * @return HigherOrderTapProxy|int|mixed
     * @throws BindingResolutionException
     */
    public function clear($queue)
    {
        return tap(parent::clear($queue), function () {
            if (Arr::get($this->diskOptions, 'cleanup') && Arr::get($this->diskOptions, 'prefix')) {
                $this->resolveDisk()->deleteDirectory(Arr::get($this->diskOptions, 'prefix'));
            }
        });
    }
}

