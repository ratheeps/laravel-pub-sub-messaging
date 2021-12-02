# Laravel Pub/Sub Messages with AWS

Simple extension to the [Illuminate/Queue](https://github.com/illuminate/queue) queue system used in [Laravel](https://laravel.com) and [Lumen](https://lumen.laravel.com/).

Using this connector allows [SQS](https://aws.amazon.com/sqs/) messages originating from a [SNS](https://aws.amazon.com/sns/) subscription to be worked on with Illuminate\Queue\Jobs\SqsJob.

This is especially useful in a miroservice architecture where multiple services subscribe to a common topic with their queues and  publish an event to SNS.

## Amazon SQS & SNS Extended Client Library

The **Amazon SQS Extended Client Library for PHP** enables you to manage Amazon SQS message payloads with Amazon S3. This is especially useful for storing and retrieving messages with a message payload size greater than the current SQS limit of 256 KB, up to a maximum of 2 GB. Specifically, you can use this library to:

* Specify whether message payloads are always stored in Amazon S3 or only when a message's size exceeds a max size (defaults to 256 KB).
* Send a message that references a single message object stored in an Amazon S3 bucket.
* Get the corresponding message object from an Amazon S3 bucket.

```diff
- Note: This package under development not ready for production -
```

## Requirements

-   Laravel (tested with version >=7.0)
-   or Lumen (tested with version >=7.0)

## Installation


1. First create a disk that will hold all of your large SQS payloads.

> We highly recommend you use a _private_ bucket when storing SQS payloads.  Payloads can contain sensitive information and should never be shared publicly.

2. Run `composer require ratheeps/laravel-pub-sub-messaging` to install the package.

3. Then, add the following queue settings to your `queue.php` file.

```php
<?php
return [
    'connections' => [
     'pub-sub-messaging-sqs' => [
            'driver' => 'pub-sub-messaging-sqs',
            'key' => env('PUB_SUB_MESSAGING_AWS_ACCESS_ID'),
            'secret' => env('PUB_SUB_MESSAGING_AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('PUB_SUB_MESSAGING_SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('PUB_SUB_MESSAGING_SQS_QUEUE', 'default'),
            'suffix' => env('PUB_SUB_MESSAGING_SQS_SUFFIX'),
            'region' => env('PUB_SUB_MESSAGING_AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],
    ],
];
```

4 Then, add the following disk settings to your `filesystems.php` file.

```php
<?php
return [
    'disks' => [
        'pub_sub_messaging_s3' => [
            'driver' => 's3',
            'key' => env('PUB_SUB_MESSAGING_AWS_ACCESS_ID'),
            'secret' => env('PUB_SUB_MESSAGING_AWS_SECRET_ACCESS_KEY'),
            'region' => env('PUB_SUB_MESSAGING_AWS_DEFAULT_REGION'),
            'bucket' => env('PUB_SUB_MESSAGING_AWS_BUCKET'),
            'url' => env('PUB_SUB_MESSAGING_AWS_URL'),
            'endpoint' => env('PUB_SUB_MESSAGING_AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],
    ],
];
```

5. You can optionally publish the config file with 
`php artisan vendor:publish --provider="Ratheeps\PubSubMessaging\PubSubMessagingServiceProvider" --tag="config"` this command
Then, modify the following pub sub settings to your `pub-sub-messaging.php` file.

```php
<?php

return [
    'default_topic' => env('PUB_SUB_MESSAGING_DEFAULT_TOPIC'),
    'default_auth_driver' => null,
    'map' => [
//        \App\Jobs\TestSQSJob::class => 'arn:aws:sns:ap-southeast-1:931616835216:modelEvent',
    ],
    'published_attributes' => [
        'id',
        'created_at',
        'updated_at'
    ],
    'sns' => [
        'key' => env('PUB_SUB_MESSAGING_AWS_ACCESS_KEY'),
        'secret' => env('PUB_SUB_MESSAGING_AWS_SECRET_ACCESS_KEY'),
        'region' => env('PUB_SUB_MESSAGING_AWS_DEFAULT_REGION', 'us-east-1'),
        'disk_options' => [
            /**
             * Indicates when to send messages to S3.
             * Allowed values are: ALWAYS, NEVER, IF_NEEDED.
             *
             * @var null|string
             */
            'store_payload' => 'IF_NEEDED',
            'disk' => env('PUB_SUB_MESSAGING_DISK', 'pub_sub_messaging_s3'),
            'prefix' => ''
        ]
    ]
];

```

6. You'll need to configure .env file
```dotenv
PUB_SUB_MESSAGING_AWS_ACCESS_ID=
PUB_SUB_MESSAGING_AWS_SECRET_ACCESS_KEY=
PUB_SUB_MESSAGING_AWS_DEFAULT_REGION=ap-south-1

PUB_SUB_MESSAGING_DEFAULT_TOPIC=arn:aws:sns:ap-south-1:568584421686:testToipc
PUB_SUB_MESSAGING_DISK=sqs-extended-lib-test

QUEUE_CONNECTION=pub-sub-messaging-sqs
PUB_SUB_MESSAGING_SQS_QUEUE=test-sqs

```

8. Boot up your queues and profit without having to worry about SQS's 256KB limit :)

## Customization

### Job class example

```php
<?php
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class TestSQSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $passedInData;

    /**
     * Create a new job instance.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        // $data is array containing the msg content from SQS
        $this->passedInData = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info(json_encode($this->passedInData));
        // Check laravel.log, it should now contain msg string.
    }
}

```

### Published event
```php
<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use Ratheeps\PubSubMessaging\Traits\SNSProducer;

class Post extends Model
{
    use SNSProducer;

    /**
     * @var array
     * Optional (default value is [] )
     * Witch are the attributes should only from SNS message
     */
    static $publishedAttributes = [
        'id',
        'created_at',
        'updated_at'
    ];

    /**
     * @var array
     * Optional  (default value is [created','updated','deleted','restored'] )
     * Witch events should send to SNS
     */
    static $publishEvents = ['created', 'updated'];

    /**
     * @var string
     * Optional (default value is load from config )
     * Publish SNS topic
     */
    static $publishTopic = 'SampleTopic';
    /**
     * Or
     * static $publishTopic = [
     *  'created' => 'SampleTopic'
     * ];
     */
}
```
## Diagram
This diagram will be describing how your microservices are communicating with help of this package
![Diagrams](https://raw.githubusercontent.com/ratheeps/laravel-pub-sub-messaging/master/diagrams.png)

## References
* **Sign up for AWS** -- Before you begin, you need an AWS account. For more information about creating an AWS account and retrieving your AWS credentials, see [AWS Account and Credentials](http://docs.aws.amazon.com/aws-sdk-php/v3/guide/guide/credentials.html?highlight=credentials) in the AWS SDK for PHP Developer Guide.
* **Sign up for Amazon SQS** -- Go to the Amazon [SQS console](https://console.aws.amazon.com/sqs/home?region=us-east-1) to sign up for the service.
* **Minimum requirements** -- To use the sample application, you'll need PHP 7.0+ and [Composer](https://getcomposer.org/). For more information about the requirements, see the [Getting Started](http://docs.aws.amazon.com/aws-sdk-php/v3/guide/getting-started/) section of the Amazon SQS Developer Guide.
* **Further information** - Read the [API documentation](http://aws.amazon.com/documentation/sqs/) and the [SQS & S3 recommendations](http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/s3-messages.html).
* **SNS Large payload** - Read the [AWS Java SDK documentation](https://docs.aws.amazon.com/sns/latest/dg/large-message-payloads.html)
* **SQS Large payload** - Read the [AWS Java SDK documentation](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-s3-messages.html)
* **Pub/Sub Messaging** - Read the [Documentation](https://aws.amazon.com/pub-sub-messaging/)

## Feedback
* Give us feedback [here](https://github.com/ratheeps/laravel-pub-sub-messaging/issues).
* If you'd like to contribute a new feature or bug fix, we'd love to see Github pull requests from you.