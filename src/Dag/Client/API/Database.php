<?php
namespace Dag\Client\API;

use Dag\Client\Exception\ParameterInvalid;

/**
 * Class Database
 * @package Dag\Client\API
 */
trait Database
{
    public function databaseList($cluster_name, array $params = [])
    {
        $resource = '/v1/' . $cluster_name;
        return $this->execute(@ANALYSIS, new RestParameter('get', $resource, [
                'cano_resource' => 'database',
                'query_params' => ListParams::listParams($params)
            ]));
    }

    public function databaseCreate($cluster_name, $database_name)
    {
        if (empty($cluster_name)) {
            throw new ParameterInvalid('cluster_name is blank');
        }
        if (empty($database_name)) {
            throw new ParameterInvalid('database_name is blank');
        }
        if (strlen($database_name) < 3) {
            throw new ParameterInvalid('database_name is too short');
        }
        if (strlen($database_name) > 63) {
            throw new ParameterInvalid('database_name is too long');
        }
        if (!preg_match('/\A[a-z0-9]+\Z/', $database_name)) {
            throw new ParameterInvalid('database_name is invalid');
        }
        if ($this->in_hive_reserved_words($database_name)) {
            throw new ParameterInvalid('database_name is reserved by hive');
        }
        $resource = '/v1/' . $cluster_name . '/' . $database_name;
        return $this->execute(@ANALYSIS, new RestParameter('put', $resource, [
                'cano_resource' => 'database',
                'content_type' => 'application/json'
            ]));
    }

    public function databaseDelete($cluster_name, $database_name)
    {
        $resource = '/v1/' . $cluster_name . '/' . $database_name;
        return $this->execute(@ANALYSIS, new RestParameter('delete', $resource, [
                'cano_resource' => 'database',
                'content_type' => 'application/json'
            ]));
    }

    private function in_hive_reserved_words($word)
    {
        $reserved_words = [
                'true', 'false', 'all', 'and', 'or', 'not', 'like', 'asc', 'desc', 'order', 'by', 'group', 'where',
                'from', 'as', 'select', 'distinct', 'insert', 'overwrite', 'outer', 'join', 'left', 'right',
                'full', 'on', 'partition', 'partitions', 'table', 'tables', 'tblproperties', 'show', 'msck',
                'directory', 'local', 'locks', 'transform', 'using', 'cluster', 'distribute', 'sort', 'union', 'load',
                'data', 'inpath', 'is', 'null', 'create', 'external', 'alter', 'describe', 'drop', 'reanme', 'to',
                'comment', 'boolean', 'tinyint', 'smallint', 'int', 'bigint', 'float', 'double', 'date',
                'datetime', 'timestamp', 'string', 'binary', 'array', 'map', 'reduce', 'partitioned',
                'clustered', 'sorted', 'into', 'buckets', 'row', 'format', 'delimited', 'fields', 'terminated',
                'collection', 'items', 'keys', 'lines', 'stored', 'sequencefile', 'textfile', 'inputformat',
                'outputformat', 'location', 'tablesample', 'bucket', 'out', 'of', 'cast', 'add', 'replace',
                'columns', 'rlike', 'regexp', 'temporary', 'function', 'explain', 'extended', 'serde', 'with',
                'serdeproperties', 'limit', 'set', 'tblproperties'
            ];
        return in_array($word, $reserved_words);
    }
}
