<?php
namespace Dag\Client\API;

use Dag\Client\Exception\ParameterInvalid;

/**
 * Class Job
 * @package Dag\Client\API
 */
trait Job
{
    use \Dag\Client\JobValidation;

    public function queryInfoList(array $params = [])
    {
        $resource = '/v1/';
        $query_params = ListParams::listParams($params);

        if (array_key_exists('status', $params)) {
            if ($params['status']) {
                $this->validQueryInfoListStatus($params['status']);
                $query_params = array_merge($query_params, ['status' => $params['status']]);
            }
        }

        if (array_key_exists('type', $params)) {
            $type = $params['type'];

            if ($type) {
                if (!in_array($type, ['select', 'split'])) {
                    throw new ParameterInvalid("type is invalid: {$type}");
                }
                $query_params = array_merge($query_params, ['type' => $type]);
            }
        }

        if (array_key_exists('cluster_name', $params)) {
            $cluster_name = $params['cluster_name'];

            if ($cluster_name) {
                $query_params = array_merge($query_params, ['clusterName' => $cluster_name]);
            }
        }

        if (array_key_exists('label_prefix', $params)) {
            $label_prefix = $params['label_prefix'];

            if ($label_prefix) {
                $query_params = array_merge($query_params, ['labelPrefix' => $label_prefix]);
            }
        }

        if (array_key_exists('cluster_rebooted', $params)) {
            $cluster_rebooted = $params['cluster_rebooted'];

            if (!is_null($cluster_rebooted)) {
                if (!is_bool($cluster_rebooted)) {
                    throw new ParameterInvalid("cluster_rebooted is invalid: {$cluster_rebooted}");
                }
                $query_params = array_merge($query_params, ['clusterRebooted' => $cluster_rebooted ? 'true' : 'false']);
            }
        }

        if (array_key_exists('order', $params)) {
            $order = $params['order'];

            if ($order) {
                if (!in_array($order, ['asc', 'desc'])) {
                    throw new ParameterInvalid("order is invalid: {$order}");
                }
                $query_params = array_merge($query_params, ['order' => $order]);
            }
        }

        return $this->execute(@ANALYSIS, new RestParameter('get', $resource, [
                'cano_resource' => 'query',
                'query_params' => $query_params
            ]));
    }

    public function queryInfo($job_id)
    {
        $resource = "/v1/{$job_id}";
        return $this->execute(@ANALYSIS, new RestParameter('get', $resource, ['cano_resource' => 'query']));
    }

    public function queryLog($job_id)
    {
        $resource = "/v1/{$job_id}/log";
        $log = $this->execute(@ANALYSIS, new RestParameter('get', $resource, ['cano_resource' => 'query']));

        $output = null;
        if ($log) {
            $log_fp = fopen('php://memory', 'r+');
            $output_fp = fopen('php://memory', 'r+');
            fwrite($log_fp, @$log['log']);
            rewind($log_fp);

            while (true) {
                $buffer = fgets($log_fp);
                if (!$buffer) break;
                if (!strstr($buffer, 'CLIService')) fwrite($output_fp, $buffer);

                $buffer = null;
            }
            rewind($output_fp);
            $output = ['log' => stream_get_contents($output_fp)];
        }
        return $output;
    }

    public function queryCancel($job_id)
    {
        $resource = "/v1/{$job_id}";
        return $this->execute(@ANALYSIS, new RestParameter('delete', $resource, [
                'cano_resource' => 'query',
                'content_type' => 'application/json'
            ]));
    }

    public function query(array $params)
    {
        if (!array_key_exists('query', $params) || empty($params['query'])) throw new ParameterInvalid('query is blank');

        if (!preg_match('/^SELECT/i', $params['query'])) throw new ParameterInvalid('query should start with SELECT');
        if (preg_match('/OVERWRITE/i', $params['query'])) throw new ParameterInvalid('query should not include OVERWRITE');

        if (!array_key_exists('output_format', $params) || !($params['output_format'] == 'csv' || $params['output_format'] == 'tsv')) {
            throw new ParameterInvalid('ouput_format should be csv or tsv');
        }

        if (!array_key_exists('output_resource_path', $params)) throw new ParameterInvalid('output_resource_path is blank');

        if (!preg_match('/^dag:\/\//', $params['output_resource_path'])) {
            throw new ParameterInvalid("output_resource_path should start with 'dag://'");
        }

        if (!array_key_exists('cluster_name', $params) || empty($params['cluster_name'])) throw new ParameterInvalid('cluster_name is blank');

        $params = [
            'outputFormat' => $params['output_format'],
            'outputResourcePath' => $params['output_resource_path'],
            'query' => $params['query'],
            'clusterName' => $params['cluster_name'],
            'label' => @$params['label'],
        ];

        $resource = "/v1/";
        return $this->execute(@ANALYSIS, new RestParameter('post', $resource, [
                'cano_resource' => 'select',
                'content_type' => 'application/json',
                'parameters' => $params
            ]));
    }
}
