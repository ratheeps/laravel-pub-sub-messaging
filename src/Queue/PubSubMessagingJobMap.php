<?php


namespace Ratheeps\PubSubMessaging\Queue;


use Exception;

class PubSubMessagingJobMap
{
    /**
     * @var array
     */
    private $map;

    /**
     * JobMap constructor.
     * @param array $map
     */
    public function __construct(array $map)
    {
        $this->map = $map;
    }

    /**
     * @param string $topic
     * @return string
     * @throws Exception
     */
    public function fromTopic(string $topic): string
    {
        $job = array_search($topic, $this->map);
        if (!$job) {
            throw new Exception("Topic $topic is not mapped to any Job");
        }
        return $job;
    }
}
