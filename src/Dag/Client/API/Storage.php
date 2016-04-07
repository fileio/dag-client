<?php
namespace Dag\Client\API;

use Dag\Client\Exception\ParameterInvalid;
use Dag\Client\Exception\MissingFileException;

/**
 * Class Storage
 * @package Dag\Client\API
 */
trait Storage
{
    // Storage Bucket
    public function buckets()
    {
        $xml_doc = $this->execute(@STORAGE, new RestParameter('get', '/'));
        return new StorageResult($xml_doc);
    }

    public function bucketCreate($bucket)
    {
        return $this->execute(@STORAGE, new RestParameter('put', '/', [
            'bucket' => $bucket,
            'content_type' => 'application/json'
        ]));
    }

    public function bucketDelete($bucket)
    {
        return $this->execute(@STORAGE, new RestParameter('delete', '/', [
            'bucket' => $bucket,
        ]));
    }

    // Storage Object
    public function objects($bucket, $params)
    {
        $query_params = [];

        if (array_key_exists('prefix', $params)) {
            $query_params = array_merge($query_params, ['prefix' => $params['prefix']]);
        }
        if (array_key_exists('max', $params)) {
            $query_params = array_merge($query_params, ['max-keys' => $params['max']]);
        }
        if (array_key_exists('marker', $params)) {
            $query_params = array_merge($query_params, ['marker' => $params['marker']]);
        }
        if (array_key_exists('delimiter', $params)) {
            $query_params = array_merge($query_params, ['delimiter' => $params['delimiter']]);
        }

        $xml_doc = $this->execute(@STORAGE, new RestParameter('get', '/', [
            'bucket' => $bucket,
            'query_params' => $query_params
        ]));
        return new StorageResult($xml_doc);
    }

    public function objectCreate($bucket, $object, array $params = [], $block)
    {
        $resource = "/{$object}";

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime_type = @$finfo->file($object);
        $content_type = $mime_type ? $mime_type : 'application/octet-stream';
        $params = array_merge($params, ['bucket' => $bucket, 'content_type' => $content_type]);

        return $this->execute(@STORAGE, new RestParameter('put', $resource, $params), $block);
    }

    public function objectCreateMultipart($bucket, $object, array $params = [], $block)
    {
        $mu = new MultipartUpload($bucket, $object, $params, function() {
            return $this;
        });

        # Initiate Multipart Upload
        $upload_id = $mu->initializeMultipartUpload();

        try {
            # Upload Part
            $upload_objects = $mu->uploadPart($upload_id, $block);

            # Complete Multipart Upload
            $mu->completeMultipartUpload($upload_id, $upload_objects);
        } catch (\Exception $e) {
            # Abort Multipart Upload
            $mu->abortMultipartUpload($upload_id);
            throw $e;
        }
    }

    public function objectGet($bucket, $object, $range = null)
    {
        $resource = "/{$object}";
        $headers = [];
        if ($range) {
            $bt = "bytes={$range[0]}-";
            if ($range[1] != -1) $bt .= "{$range[1]}";
            $headers['Range'] = $bt;
        }
        $params = ['bucket' => $bucket, 'raw_data' => true, 'headers' => $headers];

        return $this->execute(@STORAGE, new RestParameter('get', $resource, $params));
    }

    public function objectDelete($bucket, $object)
    {
        $resource = "/{$object}";
        $params = ['bucket' => $bucket, 'content_type' => 'application/json'];

        return $this->execute(@STORAGE, new RestParameter('delete', $resource, $params));
    }

    public function import($database_name, $table_name, $file_paths, array $params = [])
    {
        $_import = new Import($database_name, $table_name, $file_paths, $params, function() {
            return $this;
        });

        # calc label suffix => Fixnum
        $suffix = $_import->calcLabelSuffix();

        # import execute
        $upload_objects = $_import->execute($suffix);

        $objects_size = sizeof($upload_objects);
        error_log("finished upload {$objects_size} objects.\n\n");
        error_log("upload_objects:\n");
        foreach ($upload_objects as $object) {
            error_log("{$object}\n");
        }
    }

    public function objectHead($bucket, $object)
    {
        $resource = "/{$object}";
        $headers = [];
        $params = ['bucket' => $bucket, 'headers' => $headers];
        return $this->execute(@STORAGE, new RestParameter('head', $resource, $params));
    }
}

/**
 * Class Import
 * @package Dag\Client\API
 */
class Import
{
    private $database_name;
    private $table_name;
    private $file_paths;
    private $label;
    private $splitsz;
    private $api;

    public function __construct($database_name, $table_name, $file_paths, array $params = [], $block)
    {
        $this->database_name = $database_name;
        $this->table_name = $table_name;
        $this->file_paths = $file_paths;
        $this->label = array_key_exists('label', $params) ? $params['label'] : 'label';
        $this->splitsz = array_key_exists('splitsz', $params) ? $params['splitsz'] : 100 * pow(1024, 2); # 100M
        $this->api = $block();

        $import_parameter = ImportParameter::instance();
        $import_parameter->database_name = $this->database_name;
        $import_parameter->table_name = $this->table_name;
        $import_parameter->label = $this->label;

        if (preg_match('/^(_|\.)/', $this->label)) {
            throw new ParameterInvalid("label should not start with '_' or '.'");
        }

        error_log("Initialize...\nsplitsz: {$this->splitsz}\n");
    }

    public function calcLabelSuffix()
    {
        $prefix = ImportParameter::instance()->storagePrefix();
        $objects = $this->api->objects($this->database_name, ['prefix' => $prefix])->objects();

        if (empty($objects)) return 0;

        $numbers = [];
        foreach($objects as $object) {
            preg_match("/({$this->label})_(\d+)/", $object, $matches);
            if (!is_null(@$matches[2])) array_push($numbers, $matches[2]);
        }
        rsort($numbers);
        return @$numbers[0] + 1;
    }

    public function execute($suffix)
    {
        $file_paths = is_string($this->file_paths) ? [$this->file_paths] : $this->file_paths;

        $upload_objects = [];
        foreach ($file_paths as $file_path) {
            if (substr($file_path, strlen($file_path) - strlen('.gz')) == '.gz') {
                $file_index =  $this->importGzFile($file_path, $suffix, $upload_objects);
            } elseif ($file_path == '-') {
                $file_index = $this->importStream('php://stdin', $suffix, $upload_objects);
            } else {
                $file_index = $this->importTextFile($file_path, $suffix, $upload_objects);
            }
            $suffix += $file_index;
        }
        return $upload_objects;
    }

    private function importGzFile($file_path, $suffix, &$upload_objects)
    {
        return $this->importStream($file_path, $suffix, $upload_objects, 'gzopen', 'gzread', 'gzgets', 'gzclose');
    }

    private function importTextFile($file_path, $suffix, &$upload_objects)
    {
        return $this->importStream($file_path, $suffix, $upload_objects);
    }

    private function importStream($file_path, $suffix, &$upload_objects, $openf = 'fopen', $readf = 'fread', $getsf = 'fgets', $closef = 'fclose')
    {
        if (!file_exists($file_path)) throw new MissingFileException('File not found');

        $file_index = 0;
        $ifp = $openf($file_path, 'r');
        if ($ifp) {
            try {
                while (true) {
                    $buffer = $readf($ifp, $this->splitsz);
                    if (!$buffer) break;
                    $nline = $getsf($ifp);
                    if ($nline) $buffer .= $nline;

                    $import_parameter = ImportParameter::instance();

                    $buffer_length = strlen($buffer);
                    error_log("> starting upload part {$import_parameter->index}, {$buffer_length}\n");

                    $this->executeStorageDetail($buffer, $suffix + $file_index);

                    error_log("< finished upload part {$import_parameter->index}, {$buffer_length}\n");

                    array_push($upload_objects, ImportParameter::instance()->objectLabel($suffix + $file_index));
                    $file_index++;
                    $buffer = null;
                }
                return $file_index;
            } finally {
                $closef($ifp);
            }
        } else {
            throw new RuntimeException('Unknown file error');
        }
    }

    private function executeStorageDetail($data, $suffix)
    {
        $gz = gzencode($data);
        $resource = ImportParameter::instance()->url($suffix);

        $params = [
            'content_type' => 'application/x-zip',
            'bucket' =>  $this->database_name,
            'import' => true
        ];

        return $this->api->execute(@STORAGE, new RestParameter('put', $resource, $params), function() use($gz) {
            return $gz;
        });
    }
}

class ImportParameter
{
    public $database_name;
    public $table_name;
    public $label;
    public $index;

    private static $instance = null;

    private function __construct()
    {
        $this->index = 1;
    }

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function url($suffix)
    {
        return "/{$this->table_name}/{$this->label}_{$suffix}.gz";
    }

    public function objectLabel($suffix)
    {
        return "/{$this->database_name}/{$this->table_name}/{$this->label}_{$suffix}.gz";
    }

    public function fileLabel($suffix)
    {
        return "{$this->label}_{$suffix}";
    }

    public function storagePrefix()
    {
        return "{$this->table_name}/{$this->label}";
    }
}

/**
 * Class MultipartUpload
 * @package Dag\Client\API
 */
class MultipartUpload
{
    private $bucket;
    private $object;
    private $splitsz;
    private $params;
    private $api;

    public function __construct($bucket, $object, array $params = [], $block)
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime_type = @$finfo->file($object);
        $content_type = $mime_type ? $mime_type : 'application/octet-stream';

        $this->bucket = $bucket;
        $this->object = $object;
        $this->splitsz = array_key_exists('splitsz', $params) ? $params['splitsz'] : 100 * pow(1024, 2); # 100MB
        $this->params = array_merge($params, ['bucket' => $bucket, 'content_type' => $content_type]);
        $this->api = $block();

        if (!array_key_exists('headers', $this->params)) $this->params['headers'] = [];
        $this->params['headers'] = array_merge($this->params['headers'], ['expect' => false]);
    }

    public function initializeMultipartUpload()
    {
        error_log("Initiate multipart upload...\nsplitsz:{$this->splitsz}");
        $resource = "/{$this->object}?uploads";
        $response = $this->api->execute(@STORAGE, new RestParameter('post', $resource, $this->params));
        return $response->UploadId;
    }

    public function uploadPart($upload_id, $block)
    {
        return $this->splitStream($upload_id, $block);
    }

    public function completeMultipartUpload($upload_id, $upload_objects)
    {
        $resource = "/{$this->object}?uploadId={$upload_id}";

        $payload = '<CompleteMultipartUpload>';
        $part = 1;
        foreach ($upload_objects as $etag) {
            $payload .= "<Part><PartNumber>{$part}</PartNumber><ETag>{$etag}</ETag></Part>";
            $part++;
        }
        $payload .= '</CompleteMultipartUpload>';

        $this->api->execute(@STORAGE, new RestParameter('post', $resource, $this->params), function() use($payload) {
            return $payload;
        });

        echo 'complete multipart upload.';
    }

    public function abortMultipartUpload($upload_id)
    {
        $resource = "/{$this->object}?uploadId={$upload_id}";
        return $this->api->execute(@STORAGE, new RestParameter('delete', $resource, $this->params));
    }

    private function splitStream($upload_id, $block)
    {
        $limit = 5 * pow(1024, 2); # 5MB
        if ($this->splitsz < $limit) {
            throw new \Exception("split size is invalid. below lower limit of {$limit} byte");
        }

        $upload_objects = [];
        $ifp = $block();
        $file_index = 1;
        while (true) {
            $buffer = fread($ifp, $this->splitsz);
            if (!$buffer) break;

            echo "> starting upload part {$file_index}, {$this->splitsz}\n";
            $resource = "/{$this->object}?partNumber={$file_index}&uploadId={$upload_id}";
            $response = $this->api->execute(@STORAGE, new RestParameter('put', $resource, $this->params), function() use($buffer) {
                return $buffer;
            });
            echo "< finished upload part {$file_index}, {$this->splitsz}\n";

            array_push($upload_objects, (string)$response->headers->ETag);
            $file_index++;
            $buffer = null;
        }

        return $upload_objects;
    }
}

