<?php
namespace Dag\Client\API;

use Dag\Client\Exception\APIFailure;
use Dag\Client\Exception\ParameterInvalid;
use Dag\Client\Exception\TableAlreadyExists;

/**
 * Class Table
 * @package Dag\Client\API
 */
trait Table
{
    public function tableInfoList($cluster_name, $database_name, array $params = [])
    {
        $resource = "/v1/{$cluster_name}/{$database_name}";
        return $this->execute(@ANALYSIS, new RestParameter('get', $resource, [
                'cano_resource' => 'table',
                'query_params' => ListParams::listParams($params)
            ]));
    }

    public function tableInfo($cluster_name, $database_name, $table_name)
    {
        $resource = "/v1/{$cluster_name}/{$database_name}/{$table_name}";
        try {
            return $this->execute(@ANALYSIS, new RestParameter('get', $resource, ['cano_resource' => 'table']));
        } catch(APIFailure $e) {
            if ($e->api_code != 'TableNotFound') throw $e;
            return null;
        }
    }

    public function tableCreate($cluster_name, $database_name, array $params = [])
    {
        $table_name = @$params['table'];
        if (!$table_name) {
            throw new ParameterInvalid('table name is blank');
        }
        if (!preg_match("/\A[a-z0-9_]+\Z/", $table_name)) {
            throw new ParameterInvalid("table name is invalid: {$table_name}");
        }
        if (strlen($table_name) > 128) {
            throw new ParameterInvalid("table name is too long: {$table_name}");
        }

        $format = @$params['format'];
        if ($format && !in_array($format, ['csv', 'tsv', 'json', 'json_agent'])) {
            throw new ParameterInvalid("format is invalid: {$format}");
        }

        $comment = @$params['comment'];
        if ($comment && !preg_match("/\A[[:ascii:]]+\Z/", $comment)) {
            throw new ParameterInvalid("comment is not ascii");
        }
        if ($comment && strlen($comment) > 100) {
            throw new ParameterInvalid("comment is too long");
        }

        $resource = "/v1/{$cluster_name}/{$database_name}/{$table_name}";
        $parameters = [];
        if ($format) {
            $parameters = array_merge($parameters, ['format' => $format]);
        }
        $schema = @$params['schema'];
        if ($schema) {
            $parameters = array_merge($parameters, ['schema' => $schema]);
        }
        if ($comment) {
            $parameters = array_merge($parameters, ['comment' => $comment]);
        }

        # Table Check
        $response = $this->tableInfo($cluster_name, $database_name, $table_name);
        $create_api = @$params['create_api'];
        if ($create_api && $response) {
            if ($response['tableName'] == $table_name) {
                throw new TableAlreadyExists('Table already exists');
            }
        }

        return $this->execute(@ANALYSIS, new RestParameter('put', $resource, [
                'cano_resource' => 'table',
                'content_type' => 'application/json',
                'parameters' => $parameters
            ]));
    }

    public function tableSplit($cluster_name, $database_name, $table_name, array $params)
    {
        if (empty($params)) throw new ParameterInvalid('params is blank');

        $input_object_keys = @$params['input_object_keys'];
        if (!is_array($input_object_keys)) {
            throw new ParameterInvalid('input_object_keys is not array');
        }
        if (empty($input_object_keys)) throw new ParameterInvalid('input_object_keys is blank');

        foreach ($input_object_keys as $input_object_key) {
            if (!preg_match('/^dag:\/\//', $input_object_key)) {
                throw new ParameterInvalid("input_object_key should start with 'dag://'");
            }
        }

        $input_format = @$params['input_format'];
        if (empty($input_format)) throw new ParameterInvalid('input_format is blank');
        if (!in_array($input_format, ['csv', 'tsv', 'json'])) {
            throw new ParameterInvalid("input_format is invalid:{$input_format}");
        }

        $parameters = [
            'inputObjectKeys' => $input_object_keys,
            'inputFormat' => $input_format,
            'outputDatabase' => $database_name,
            'outputTable' => $table_name,
            'clusterName' => $cluster_name
        ];

        $label = @$params['label'];
        if (!empty($label)) {
            array_merge($parameters, ['label' => $label]);
        }

        $schema = @$params['schema'];
        if (!empty($schema)) {
            array_merge($parameters, ['schema' => $schema]);
        }

        return $this->execute(@ANALYSIS, new RestParameter('post', '/v1/', [
                'cano_resource' => 'split',
                'content_type' => 'application/json',
                'parameters' => $parameters
            ]));
    }

    public function tableDelete($cluster_name, $database_name, $table_name)
    {
        $resource = "/v1/{$cluster_name}/{$database_name}/{$table_name}";
        $this->execute(@ANALYSIS, new RestParameter('delete', $resource, [
              'cano_resource' => 'table',
              'content_type' => 'application/json'
          ]));
    }
}
