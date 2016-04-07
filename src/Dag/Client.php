<?php
namespace Dag;

use Dag\Client\API;

/**
 * Class Client
 * @package Dag
 */
class Client implements ClientInterface
{
    use Client\Cluster;
    use Client\Database;
    use Client\Job;
    use Client\Storage;

    public $api;
    public $access_key_id;

    private $analysis_api;
    private $storage_api;
    private $force_path_style;
    private $debug;

    /**
     * @param $access_key_id
     * @param $secret_access_key
     * @param array $params
     */
    public function __construct($access_key_id, $secret_access_key, array $params = [])
    {
        $this->access_key_id = $access_key_id;
        $this->api = new Client\API($access_key_id, $secret_access_key, $params);
    }

    private $_api_accessor = ['analysis_api', 'storage_api', 'force_path_style', 'debug'];

    public function __get($property)
    {
        if (in_array($property, $this->_api_accessor)) {
            return $this->api->$property;
        }

        throw new \Exception("Property {$property} is not accessible");
    }

    public function __set($property, $value)
    {
        if (in_array($property, $this->_api_accessor)) {
            $this->api->$property = $value;
            return;
        }

        throw new \Exception("Property {$property} is not accessible");
    }
}

