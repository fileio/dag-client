<?php
namespace Dag\Client\API;

/**
 * Class StorageResult
 * @package Dag\Client\API
 */
class StorageResult
{
    use BucketResult, ObjectResult;

    private $xml_doc;

    public function __construct($xml_doc)
    {
        $this->xml_doc = $xml_doc;
    }
}

/**
 * Class BucketResult
 * @package Dag\Client\API
 */
trait BucketResult
{
    public function buckets()
    {
        $bucket_names = [];
        foreach ($this->xml_doc->Buckets->Bucket as $bucket) {
            array_push($bucket_names, $bucket->Name);
        }
        return $bucket_names;
    }

    public function ownerId()
    {
        return (string)$this->xml_doc->Owner->ID;
    }

    public function displayName()
    {
        return (string)$this->xml_doc->Owner->DisplayName;
    }
}

/**
 * Class ObjectResult
 * @package Dag\Client\API
 */
trait ObjectResult
{
    public function objects()
    {
        $object_names = [];
        foreach ($this->xml_doc->Contents as $object) {
            array_push($object_names, (string)$object->Key);
        }
        return $object_names;
    }

    public function fullObjects()
    {
        $full_objects = [];
        foreach ($this->xml_doc->Contents as $object) {
            array_push($full_objects, $object);
        }
        return $full_objects;
    }

    public function is_truncated()
    {
        return (string)$this->xml_doc->IsTruncated == 'true' ? true : false;
    }

    public function marker()
    {
        return (string)$this->xml_doc->Marker;
    }

    public function nextMarker()
    {
        return (string)$this->xml_doc->NextMarker;
    }

    public function max()
    {
        return (int)$this->xml_doc->Max;
    }
}

