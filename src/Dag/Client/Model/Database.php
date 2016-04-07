<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Model;
use Dag\Client;

/**
 * Class Database
 * @package Dag
 */
class Database extends Model
{
    public $cluster_name;

    public function __construct(APIInterface $api, $cluster_name, $db_name)
    {
        parent::__construct($api);

        $this->cluster_name = $cluster_name;
        $this->db_name = $db_name;
    }

    public function delete()
    {
        $this->api->databaseDelete($this->cluster_name, $this->db_name);
    }

    use Client\Table;
}
