<?php
namespace Dag\Client;

use Dag\Client\Exception\TableNotFound;

/**
 * Class Table
 * @package Dag\Cilent
 */
trait Table
{
    public function tables()
    {
        return new Model\TableCollection($this->api, $this->cluster_name, $this->db_name);
    }

    public function table($tbl_name)
    {
        $table_info = $this->api->tableInfo($this->cluster_name, $this->db_name, $tbl_name);
        if (!$table_info) {
            throw new TableNotFound('table not found');
        }
        return new Model\Table($this->api, $this->cluster_name, $this->db_name, $table_info);
    }
}
