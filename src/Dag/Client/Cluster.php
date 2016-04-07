<?php
namespace Dag\Client;

use Dag\Client\Exception\ClusterNotOpen;

/**
 * Class Cluster
 * @package Dag\Client
 */
trait Cluster
{
    use ClusterValidation;

    public $cluster_name;

    public function open($cluster_name)
    {
        if (!$cluster_name) {
            throw new ClusterNotOpen('Cluster not opened');
        }

        $this->cluster_name = $cluster_name;

        return $this;
    }

    public function clusters()
    {
        return new Model\ClusterCollection($this->api);
    }

    public function cluster()
    {
        if (!$this->cluster_name) {
            throw new ClusterNotOpen('Cluster not opened');
        }

        $this->clusterStatus();

        return new Model\Cluster($this->api, $this->cluster_info);
    }
}
