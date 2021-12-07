<?php


namespace Ratheeps\PubSubMessaging\Publisher;

use Exception;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ratheeps\PubSubMessaging\SNS\Client;
use Illuminate\Contracts\Auth\Authenticatable;
use Ratheeps\PubSubMessaging\Traits\ResolvesPointers;

/**
 * Class SNSPublisher
 * @package Ratheeps\PubSubMessaging\Publisher
 */
class SNSPublisher
{
    use ResolvesPointers;

    /**
     * The max length of a SQS message before it must be stored as a pointer.
     *
     * @var int
     */
    const MAX_SQS_LENGTH = 250000;

    const IF_NEEDED = 'IF_NEEDED';
    const ALWAYS = 'ALWAYS';
    const NEVER = 'NEVER';

    /** @var AuthManager */
    protected $auth;

    /** @var Collection */
    protected $properties;

    /** @var Model */
    protected $performedOn;

    /** @var string */
    protected $topicArn;

    /** @var string */
    protected $event;

    /** @var Authenticatable|null */
    protected $authUser;

    /** @var Client */
    protected $SNSClient;

   /** @var array */
    protected $diskOptions;

    /**
     * SNSPublisher constructor.
     * @param AuthManager $auth
     * @param Repository $config
     * @param Client $SNSClient
     */
    public function __construct(AuthManager $auth, Repository $config, Client $SNSClient)
    {
        $this->auth = $auth;
        $this->topicArn = $config->get('pub-sub-messaging.default_topic');
        $this->SNSClient = $SNSClient;

        $authDriver = $config->get('pub-sub-messaging.default_auth_driver') ?? $auth->getDefaultDriver();
        if (Str::startsWith(app()->version(), '5.1')) {
            $this->authUser = $auth->driver($authDriver)->user();
        } else {
            $this->authUser = $auth->guard($authDriver)->user();
        }
    }

    /**
     * @param Model $model
     * @return $this
     */
    public function performedOn(Model $model): SNSPublisher
    {
        $this->performedOn = $model;
        return $this;
    }

    /**
     * @param array $properties
     * @return $this
     */
    public function withProperties(array $properties = []): SNSPublisher
    {
        if (!count($properties)) return $this;
        $this->properties = $properties;
        return $this;
    }

    /**
     * @param string|null $topic
     * @return $this
     */
    public function withTopic(string $topic = null)
    {
        if (!$topic) return $this;
        $this->topicArn = $topic;
        return $this;
    }

    /**
     * @param string $event
     * @return $this
     */
    public function withEvent(string $event)
    {
        if (!$event) return $this;
        $this->event = $event;
        return $this;
    }

    /**
     * @param string $topicArn
     * @param array $properties
     * @throws Exception
     */
    public function publish(string $topicArn = '', array $properties = [])
    {
        $this->withTopic($topicArn);
        $this->withProperties($properties);
        if (!$this->topicArn) {
            throw new Exception('Message does not have Topic ARN');
        }

        $this->SNSClient->publish($this->topicArn, $this->generateMessage());
    }

    /**
     * @return false|string
     * @throws BindingResolutionException
     */
    private function generateMessage()
    {
        $messageArray = ['data' => $this->properties];
        if ($this->event) $messageArray['model_event'] = $this->event;
        if ($this->performedOn) $messageArray['model'] = class_basename($this->performedOn);
        if ($this->authUser) {
            $messageArray['user'] = ['id' => $this->authUser->id];
        }


        $sendToS3 = config('pub-sub-messaging.sns.disk_options.store_payload', 'IF_NEEDED');

        $payloadString = json_encode($messageArray);
        $payloadLength = strlen($payloadString);

        switch ($sendToS3) {
            case self::ALWAYS:
                $useS3 = true;
                break;
            case self::IF_NEEDED:
                $useS3 = ($payloadLength >= self::MAX_SQS_LENGTH);
                break;
            default:
                $useS3 = false;
                break;
        }

        if ($useS3){
            return $this->storePayload($payloadString);
        }

        return $payloadString;
    }
}
