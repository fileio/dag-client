<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Model;
use Dag\Client;

/**
 * Class Bucket
 * @package Dag\Client\Model
 */
class Bucket extends Model
{
    public $name;

    public function __construct(APIInterface $api, $bucket_name)
    {
        parent::__construct($api);

        $this->name = $bucket_name;
    }

    public function delete()
    {
        $this->api->bucketDelete($this->name);
    }

    public function objects()
    {
        return new ObjectCollection($this->api, $this->name);
    }

    public function object($object_name)
    {
        return new Object($this->api, $this->name, $object_name);
    }
}
