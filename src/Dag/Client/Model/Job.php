<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Model;
use Dag\Client\Exception\JobTypeInvalid;
use Dag\Client\Exception\ClusterRebooted;
use Dag\Client\Exception\StatusInvalid;

/**
 * Class Job
 * @package Dag\Client\Model
 */
class Job extends Model
{
    private $id;
    private $status;
    private $process_engine;
    private $dsl;
    private $cluster_name;
    private $cluster_rebooted;
    private $start_at;
    private $access_key_id;
    private $query;
    private $output_format;
    private $output_resource_path;
    private $type;
    private $label;
    private $stage;
    private $progress;
    private $job_id;
    private $schema;
    private $input_object_keys;
    private $input_format;
    private $output_database;
    private $output_table;

    public function __construct(APIInterface $api, $job_info)
    {
        parent::__construct($api);

        $this->updateParameters($job_info);
    }

    private $_reader = [
        'id', 'status', 'process_engine', 'dsl', 'cluster_name', 'cluster_rebooted', 'start_at', 'access_key_id',
        'query', 'output_format', 'output_resource_path', 'type', 'label', 'stage', 'progress', 'job_id',
        'schema', 'input_object_keys', 'input_format', 'output_database', 'output_table'
    ];

    public function __get($property)
    {
        if (in_array($property, $this->_reader)) {
            return $this->$property;
        }

        throw new \Exception("Property {$property} is not accessible");
    }

    public function is_finished()
    {
        return $this->status == 'finished' ? true: false;
    }

    public function is_running()
    {
        return $this->status == 'running' ? true: false;
    }

    public function is_split()
    {
        return $this->type == 'split' ? true: false;
    }

    public function is_hive()
    {
        return $this->type == 'select' ? true: false;
    }

    public function is_cluster_rebooted()
    {
        return !!$this->cluster_rebooted;
    }

    public function reload()
    {
        $job_info = $this->api->queryInfo($this->id);
        $this->updateParameters($job_info);
    }

    public function cancel()
    {
        $this->validCancelCondition();
        $this->api->queryCancel($this->id);
    }

    public function validCancelCondition()
    {
        if (!$this->is_running()) {
            throw new StatusInvalid('job status is not running');
        }
    }

    public function downloadUrls($time_limit = 30)
    {
        if (!$this->is_finished()) {
            throw new StatusInvalid('job status is not finished"');
        }
        $expire_at = strtotime("+{$time_limit} minutes");
        $object_uri = parse_url($this->output_resource_path);
        $bucket = $object_uri['host'];
        $object_path = substr($object_uri['path'], 1, strlen($object_uri['path']) - 2);
        if ($object_path[strlen($object_path) - 1] != "/") $object_path .= "/";
        $bucket_objects = $this->api->objects($bucket, ['prefix' => $object_path])->objects();
        $urls = [];
        $path = null;
        foreach ($bucket_objects as $object) {
            if ($this->api->force_path_style) {
                $path = "/{$bucket}/{$object}";
            } else {
                $path = "/{$object}";
            }

            $parameters = [
                "Expires" => $expire_at,
                "IIJGIOAccessKeyId" => $this->api->access_key_id,
                "Signature" => $this->api->downloadSignature($expire_at, $bucket, $path)
            ];

            $uri = parse_url($this->api->storage_api);
            if ($this->api->force_path_style) {
                $url = "https://{$uri['host']}";
            } else {
                $url = "https://{$bucket}.{$uri['host']}";
            }
            if (array_key_exists('port', $uri) && !($uri['port'] == 80 || $uri['port'] == 443)) {
                $url .= ":{$uri['port']}";
            }

            $url_params = [];
            foreach ($parameters as $key => $value) {
                $url_encoded_value = urlencode($value);
                array_push($url_params, "{$key}={$url_encoded_value}");
            }
            $url_params = join('&', $url_params);
            array_push($urls, "{$url}{$path}?{$url_params}");
        }
        return $urls;
    }

    public function log()
    {
        $this->validLogCondition();
        $log_info = $this->api->queryLog($this->id);
        return $log_info ? @$log_info['log'] : '';
    }

    public function validLogCondition()
    {
        if ($this->is_split()) {
            throw new JobTypeInvalid('job type is not select');
        }
        if ($this->is_cluster_rebooted()) {
            throw new ClusterRebooted('cluster is rebooted"');
        }
    }

    private function updateParameters($job_info)
    {
        $this->id = @$job_info['id'];
        $this->status = @$job_info['status'];
        $this->process_engine = @$job_info['processEngine'];
        $this->dsl = @$job_info['dsl'];
        $this->cluster_name = @$job_info['clusterName'];
        $this->cluster_rebooted = @$job_info['clusterRebooted'];
        $this->start_at = @$job_info['startTime'];
        $this->access_key_id = @$job_info['accessKeyId'];
        $this->query = @$job_info['query'];
        $this->output_format = @$job_info['outputFormat'];
        $this->output_resource_path = @$job_info['outputResourcePath'];
        $this->type = @$job_info['type'];
        $this->label = @$job_info['label'];
        $this->stage = @$job_info['stage'];
        $this->progress = @$job_info['progress'];
        $this->job_id = @$job_info['jobId'];
        $this->schema = @$job_info['schema'];
        $this->input_object_keys = @$job_info['inputObjectKeys'];
        $this->input_format = @$job_info['inputFormat'];
        $this->output_database = @$job_info['outputDatabase'];
        $this->output_table = @$job_info['outputTable'];
    }
}
