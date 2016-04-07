<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Model;
use Dag\Client;

/**
 * Class Object
 * @package Dag
 */
class Object extends Model
{
    public $bucket_name;
    public $name;
    public $opts;

    public function __construct(APIInterface $api, $bucket_name, $object_name, $opts = [])
    {
        parent::__construct($api);

        $this->bucket_name = $bucket_name;
        $this->name = $object_name;
        $this->opts = $opts;
    }

    public function writeStream($resource, array $params = [])
    {
        if (!is_resource($resource)) {
            throw new Client\Exception\MissingFileException();
        }
        $data = $resource;
        return $this->upload($params, $data);
    }

    public function write($data, array $params = [])
    {
        if(file_exists($data)) {
            $data = fopen($data, 'r');
        } else {
            $fp = fopen('php://memory', 'r+');
            fwrite($fp, $data);
            rewind($fp);
            $data = $fp;
        }
        return $this->upload($params, $data);
    }

    public function read($range = null)
    {
        return $this->api->objectGet($this->bucket_name, $this->name, $range);
    }

    public function head()
    {
        $response = $this->api->objectHead($this->bucket_name, $this->name);
        $json = json_encode($response);
        $array = json_decode($json, TRUE);
        return $array['headers'];
    }

    public function delete()
    {
        return $this->api->objectDelete($this->bucket_name, $this->name);
    }

    private function upload($params, $data)
    {
        try {
            if (array_key_exists('multipart', $params)) {
                $this->api->objectCreateMultipart($this->bucket_name, $this->name, $params, function() use ($data) {
                    return $data;
                });
            } else {
                $this->api->objectCreate($this->bucket_name, $this->name, $params, function() use ($data) {
                    return $data;
                });
            }
        } finally {
            if(is_resource($data)) fclose($data);
        }

        return true;
    }
}
