<?php

namespace Ratheeps\PubSubMessaging\Queue\Jobs;

use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Contracts\Queue\Job as JobContract;
use Ratheeps\PubSubMessaging\Traits\PubSubMessagingSqsBaseJob;

class PubSubMessagingSqsJob extends SqsJob implements JobContract
{
    use PubSubMessagingSqsBaseJob;
}
