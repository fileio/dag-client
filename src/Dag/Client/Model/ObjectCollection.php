<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Exception\ParameterInvalid;
use Dag\Client\Model;
use Traversable;

class ObjectCollection extends Model implements \IteratorAggregate
{
    private $bucket_name;
    private $prefix;
    private $max;
    private $delimiter;

    public function __construct(APIInterface $api, $bucket_name)
    {
        parent::__construct($api);

        $this->bucket_name = $bucket_name;
    }

    public function where($params)
    {
        if (array_key_exists('prefix', $params)) {
            $this->prefix = $params['prefix'];
        }
        if (array_key_exists('max', $params)) {
            $this->max = $params['max'];
        }
        if (array_key_exists('delimiter', $params)) {
            $this->delimiter = $params['delimiter'];
        }
        return $this;
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
        $options = [];

        if ($this->prefix) {
            $options = array_merge($options, ['prefix' => $this->prefix]);
        }
        if ($this->max) {
            $options = array_merge($options, ['max' => $this->max]);
        }
        if ($this->delimiter) {
            $options = array_merge($options, ['delimiter' => $this->delimiter]);
        }

        $truncated = null;
        $marker = null;
        do {
            if ($marker) {
                $options = array_merge($options, ['marker' => $marker]);
            }
            $object_result = $this->api->objects($this->bucket_name, $options);
            $objects = $object_result->fullObjects();

            $truncated = $object_result->is_truncated();
            $nextMarker = $object_result->nextMarker();
            if (empty($nextMarker)) {
                if (!empty($objects)) {
                    $marker = (string)end($objects)->Key;
                }
            } else {
                $marker = $nextMarker;
            }

            foreach ($objects as $object) {
                yield $this->objectOps($object);
            }
        } while($truncated);
    }

    private function objectOps($object_ops)
    {
        return new Object($this->api, $this->bucket_name, $object_ops->Key, $object_ops);
    }
}
