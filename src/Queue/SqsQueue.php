<?php


namespace Ratheeps\PubSubMessaging\Queue;

use Illuminate\Contracts\Queue\Job;
use Aws\Sqs\SqsClient;
use Illuminate\Queue\SqsQueue as ParentSqsQueue;
use Ratheeps\PubSubMessaging\Extended\S3Pointer;
use Ratheeps\PubSubMessaging\Queue\Jobs\SqsJob;

class SqsQueue extends ParentSqsQueue
{
    /**
     * @var JobMap
     */
    private $map;

    /**
     * SnsQueue constructor.
     * @param SqsClient $sqs
     * @param string $default
     * @param JobMap $map
     */
    public function __construct(SqsClient $sqs, string $default, JobMap $map)
    {
        parent::__construct($sqs, $default);
        $this->map = $map;
    }

    /**
     * @param null $queue
     * @return Job|SqsJob|null
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);
        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue,
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        // Detect if this is an S3 pointer message.
        if (S3Pointer::isS3Pointer($result)) {
            $args = $result->get(1);
            // Get the S3 document with the message and return it.
            return $this->getS3Client()->getObject([
                'Bucket' => $args['s3BucketName'],
                'Key'    => $args['s3Key']
            ]);
        }

        if (!is_null($response['Messages']) && count($response['Messages']) > 0) {
            return new SqsJob(
                $this->container,
                $this->sqs,
                $response['Messages'][0],
                $this->connectionName,
                $queue,
                $this->map
            );
        }
    }
}

