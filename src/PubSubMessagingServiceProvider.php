<?php

namespace Ratheeps\PubSubMessaging;

use Illuminate\Support\ServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Queue\QueueManager;
use Ratheeps\PubSubMessaging\Queue\Connectors\PubSubMessagingSqsConnector;
use Ratheeps\PubSubMessaging\Queue\PubSubMessagingJobMap;

class PubSubMessagingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     * @return void
     */
    public function register()
    {
        $this->publishConfig();
    }

    /**
     * Bootstrap services.
     * @return void
     */
    public function boot()
    {
        $config = $this->app->make(Repository::class);
        $manager = $this->app->make('queue');
        $manager->addConnector('pub-sub-messaging-sqs', function () use ($config) {
            $map = new PubSubMessagingJobMap($config->get('pub-sub-messaging.map'));
            return new PubSubMessagingSqsConnector($map);
        });
    }

    /**
     * publish the configuration file
     */
    private function publishConfig()
    {
        $this->publishes([
            __DIR__.'/../config/pub-sub-messaging.php' => config_path('pub-sub-messaging.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../config/pub-sub-messaging.php', 'pub-sub-messaging');
    }
}
