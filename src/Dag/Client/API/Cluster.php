<?php
namespace Dag\Client\API;

use Dag\Client\Exception\ParameterInvalid;

/**
 * Class Cluster
 * @package Dag\Client\API
 */
trait Cluster
{
    use \Dag\Client\ClusterValidation;

    public function clusterInfoList(array $params = [])
    {
        $resource = '/v1/';
        $query_params = ListParams::listParams($params);

        if (array_key_exists('status', $params)) {
            if ($params['status']) {
                $this->validClusterInfoListStatus($params['status']);
                $query_params = array_merge($query_params, ['status' => $params['status']]);
            }
        }

        if (array_key_exists('type', $params)) {
            $type = $params['type'];

            if ($type) {
                $query_params = array_merge($query_params, ['type' => $type]);
            }
        }

        if (array_key_exists('cluster_name', $params)) {
            $cluster_name = $params['cluster_name'];

            if ($cluster_name) {
                $query_params = array_merge($query_params, ['clusterName' => $cluster_name]);
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
                'cano_resource' => 'clusterManagement',
                'query_params' => $query_params
            ]));
    }

    public function clusterInfo($cluster_name)
    {
        $resource = "/v1/{$cluster_name}";

        return $this->execute(@ANALYSIS, new RestParameter('get', $resource, [
                'cano_resource' => 'clusterManagement',
            ]));
    }

    public function clusterRestart($cluster_name, array $params = [])
    {
        $resource = "/v1/{$cluster_name}";

        $parameters = [];

        $force = @$params['force'];
        if ($force) {
            if (!is_bool($force)) {
                throw new ParameterInvalid("Parameter force is invalid: {$force}");
            }
            $parameters = array_merge($parameters, ['force' => $force]);
        }

        $type = @$params['type'];
        if ($type) {
            $parameters = array_merge($parameters, ['type' => $type]);
        }

        $debug = @$params['debug'];
        if ($debug) {
            if (!is_bool($debug)) {
                throw new ParameterInvalid("Parameter debug is invalid: {$debug}");
            }
            $parameters = array_merge($parameters, ['debug' => $debug]);
        }

        return $this->execute(@ANALYSIS, new RestParameter('put', $resource, [
                'cano_resource' => 'clusterManagement',
                'content_type' => 'application/json',
                'parameters' => $parameters,
                'blank_body' => true
            ]));
    }

    public function clusterStatistics($cluster_name, array $params = [])
    {
        $resource = "/v1/{$cluster_name}/statistics";

        return $this->execute(@ANALYSIS, new RestParameter('get', $resource, [
                'cano_resource' => 'clusterManagement'
            ]));
    }

    public function clusterExportLog($cluster_name, array $params = [])
    {
        $resource = "/v1/{$cluster_name}/log";

        if (array_key_exists('output_log_path', $params)) {
            $output_log_path = $params['output_log_path'];
        } else {
            throw new ParameterInvalid('output_log_path is blank');
        }

        if (!preg_match('/^dag:\/\//', $output_log_path)) {
            throw new ParameterInvalid("output_log_path should start with 'dag://'");
        }

        if (!preg_match('/\/$/', $output_log_path)) {
            throw new ParameterInvalid("output_log_path should end with '/'");
        }

        $parameters = ['outputLogPath' => $output_log_path];

        if (array_key_exists('compress', $params)) {
            $compress = $params['compress'];

            if (!is_bool($compress)) {
                throw new ParameterInvalid("compress is invalid: {$compress}");
            }

            $parameters = array_merge($parameters, ['compress' => $compress]);
        }

        return $this->execute(@ANALYSIS, new RestParameter('put', $resource, [
                'cano_resource' => 'clusterManagement',
                'content_type' => 'application/json',
                'parameters' => $parameters
            ]));
    }
}
