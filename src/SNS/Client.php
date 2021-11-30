<?php


namespace Ratheeps\PubSubMessaging\SNS;

use Aws\Credentials\Credentials;
use Aws\Sns\SnsClient;
use Aws\Result;

class Client
{
    /** @var SnsClient */
    private $client;

    /**
     * Client constructor.
     */
    public function __construct()
    {
        $this->client = SnsClient::factory([
            'version' => '2010-03-31',
            'region' => config('pub-sub-messaging.sns.region'),
            'credentials' => new Credentials(
                config('pub-sub-messaging.sns.key'),
                config('pub-sub-messaging.sns.secret')
            )
        ]);
    }

    /**
     * @param string $topicArn
     * @param string $message
     * @return Result
     */
    public function publish(string $topicArn, string $message)
    {
        return $this->client->publish([
            "Message" => $message,
            "TopicArn" => $topicArn,
        ]);
    }
}
