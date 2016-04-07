<?php
namespace Dag\Client;

use Dag\Client\Exception\DatabaseNotFound;
use Dag\Client\Exception\ParameterInvalid;

/**
 * Class Database
 * @package Dag\Client
 */
trait Database
{
    public function databases()
    {
        $this->validCluster();

        return new Model\DatabaseCollection($this->api, $this->cluster_name);
    }

    public function database($db_name)
    {
        $this->validCluster();

        if (!$db_name) {
            throw new ParameterInvalid("db_name is blank");
        }

        $databases = $this->databases();
        foreach ($databases as $database) {
            if ($database->db_name == $db_name) {
                return $database;
            }
        }

        throw new DatabaseNotFound('Database not found');
    }

    public function tableSplit($db_name, $tbl_name, $params)
    {
        $this->clusterNorm($this->clusterStatus());

        $split_info = $this->api->tableSplit($this->cluster_name, $db_name, $tbl_name, $params);
        $job_id = $split_info['queryId'];
        $query_info = $this->api->queryInfo($job_id);

        return new Model\Job($this->api, $query_info);
    }
}
