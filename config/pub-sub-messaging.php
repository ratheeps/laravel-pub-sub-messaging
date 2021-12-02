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
