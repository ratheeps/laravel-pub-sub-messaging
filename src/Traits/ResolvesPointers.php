<?php

namespace Ratheeps\PubSubMessaging\Traits;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Filesystem\FilesystemAdapter;

trait ResolvesPointers
{
    /**
     * @return null
     */
    protected function resolveS3Payload()
    {
        if (isset(json_decode($this->job['Body'])->Message)){
            $message = json_decode($this->job['Body'])->Message;
            return json_decode($message)->S3Payload ?? null;
        }
        return null;
    }

    /**
     * Resolves the configured queue disk that stores large payloads.
     *
     * @return FilesystemAdapter
     * @throws BindingResolutionException
     */
    protected function resolveDisk(): FilesystemAdapter
    {
        if (!isset($this->container)) {
            $this->container = app();
        }
        if (!isset($this->diskOptions)) {
            $this->diskOptions = config('pub-sub-messaging.sns.disk_options');
        }
        return $this->container->make('filesystem')->disk(Arr::get($this->diskOptions, 'disk'));
    }

    protected function storePayload($payloadString)
    {
        if (empty($this->diskOptions)) {
            $this->diskOptions = config('pub-sub-messaging.sns.disk_options');
        }

        $disk = Arr::get($this->diskOptions, 'disk');
        $bucket = config("filesystems.disks.{$disk}.bucket");
        $uuid = (string)Str::uuid();
        $filepath = "{$uuid}.json";
        $prefix = Arr::get($this->diskOptions, 'prefix', null);
        if ($prefix) {
            $filepath = "{$prefix}/$filepath";
        }
        $this->resolveDisk()->put($filepath, $payloadString);

        $url = $this->resolveDisk()->url($filepath);

        $payload = [
            'S3Payload' => [
                'Id' => $uuid,
                'Bucket' => $bucket,
                'Key' => $filepath,
                'Location' => $url
            ]
        ];
        return json_encode($payload);
    }

    /**
     * @param $s3Payload
     * @return string
     */
    protected function retrievePayload($s3Payload): string
    {
        $disk = Arr::get($this->diskOptions, 'disk');
        $key = $s3Payload->Key ?? null;
        $bucket = $s3Payload->Bucket ?? null;
        if (!empty($bucket)){
            config(["filesystems.disks.{$disk}.bucket" => $bucket]);
        }
        $payload = $this->resolveDisk()->get($key);
        $jobBody = json_decode($this->job['Body']);
        if (isset($jobBody->Message)){
            $jobBody->Message = $payload;
            return json_encode($jobBody);
        }
        return $jobBody;
    }
}
