<?php
namespace Dag\Client;

/**
 * Class Storage
 * @package Dag\Client
 */
trait Storage
{
    public function buckets()
    {
        return new Model\BucketCollection($this->api);
    }

    public function bucket($bucket_name)
    {
        return new Model\Bucket($this->api, $bucket_name);
    }

    public function import($database_name, $table_name, $file_paths, array $options = [])
    {
        $this->api->import($database_name, $table_name, $file_paths, $options);
    }
}
