<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Model;
use Dag\Client;

/**
 * Class Table
 * @package Dag\Client\Model
 */
class Table extends Model
{
    private $cluster_name;
    private $database_name;
    private $name;
    private $format;
    private $comment;
    private $location;
    private $columns;
    private $created_at;
    private $modified_at;

    public function __construct(APIInterface $api, $cluster_name, $database_name, $params = [])
    {
        parent::__construct($api);

        $this->cluster_name = $cluster_name;
        $this->database_name = $database_name;

        $this->loadTableInfo($params);
    }

    private $_reader = [
        'cluster_name', 'database_name', 'name', 'format', 'comment',
        'location', 'columns', 'created_at', 'modified_at'
    ];

    public function __get($property)
    {
        if (in_array($property, $this->_reader)) {
            return $this->$property;
        }

        throw new \Exception("Property {$property} is not accessible");
    }

    public function update($update_params)
    {
        $schema = @$update_params['schema'] ?: $this->schema();
        $format = @$update_params['format'] ?: $this->format;
        $comment = @$update_params['comment'] ?: $this->comment;

        $params = [
            'table' => $this->name,
            'comment' => $comment,
            'format' => $format,
            'schema' => $schema
        ];

        $this->api->tableCreate($this->cluster_name, $this->database_name, $params);
        $table_info = $this->api->tableInfo($this->cluster_name, $this->database_name, $this->name);
        $this->loadTableInfo($table_info);

        return $this;
    }

    public function delete()
    {
        return $this->api->tableDelete($this->cluster_name, $this->database_name, $this->name);
    }

    public function schema()
    {
        $schema = [];
        if ($this->columns) {
            foreach ($this->columns as $column) {
                array_push($schema, "{$column['name']} {$column['type']}");
            }
        }
        return join(',', $schema);
    }

    private function loadTableInfo($table_info)
    {
        $this->name = @$table_info['tableName'];
        $this->format = @$table_info['format'];
        $this->comment = @$table_info['comment'];
        $this->location = @$table_info['location'];
        $this->columns = @$table_info['columns'];
        if (@$table_info['createTime']) {
            $this->created_at = new \DateTime();
            $this->created_at->setTimestamp($table_info['createTime']);
        }
        if (@$table_info['modifiedTime']) {
            $this->modified_at = new \DateTime();
            $this->modified_at->setTimestamp($table_info['modifiedTime']);
        }
    }
}
