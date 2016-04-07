<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Exception\ParameterInvalid;
use Dag\Client\Model;
use Traversable;

class BucketCollection extends Model implements \IteratorAggregate
{
    public function __construct(APIInterface $api)
    {
        parent::__construct($api);
    }

    public function create($bucket_name)
    {
        $this->api->bucketCreate($bucket_name);
        $this->bucketName($bucket_name);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     */
    public function getIterator()
    {
        $bucket_names = $this->api->buckets()->buckets();
        foreach ($bucket_names as $bucket_name) {
            yield $this->bucketName($bucket_name);
        }
    }

    private function bucketName($bucket_name)
    {
        return new Bucket($this->api, $bucket_name);
    }
}
