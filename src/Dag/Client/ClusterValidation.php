<?php
namespace Dag\Client;

use Dag\Client\Exception\ClusterNotOpen;
use Dag\Client\Exception\StatusInvalid;
use Dag\Client\Exception\ParameterInvalid;

trait ClusterValidation
{
    private $cluster_status;
    private $cluster_info;

    public function validCluster()
    {
        if (!$this->cluster_name) {
            throw new ClusterNotOpen('Cluster not opened');
        }

        if (!$this->validClusterStatus()) {
            throw new StatusInvalid("Cluster is not valid status: {$this->clusterStatus()}");
        }
    }

    public function validClusterStatus($status = null)
    {
        $_statuses = ['init', 'reserved', 'stopped', 'restarting', 'norm', 'failed', 'ptfailed'];

        if ($status) {
            $this->cluster_status = $status;
        }

        if (in_array($this->clusterStatus(), $_statuses)) {
            return true;
        }

        return false;
    }

    public function validClusterInfoListStatus($statuses)
    {
        $_statuses = ['init', 'reserved', 'stopped', 'starting', 'restarting', 'norm', 'failed', 'ptfailed', 'error'];

        $statuses = array_map(function($s){return trim($s);}, explode(',', $statuses));
        foreach($statuses as $status) {
            if (!in_array($status, $_statuses)) {
                throw new ParameterInvalid("status is invalid: {$status}");
            }
        }
    }

    public function clusterNorm($status)
    {
        if($status == 'norm') {
            return true;
        }

        return false;
    }

    public function clusterNormOrPtfailed($status)
    {
        $_statuses = ['norm', 'ptfailed'];

        if (in_array($status, $_statuses)) {
            return true;
        }

        return false;
    }

    public function validClusterRestartStatus($status)
    {
        $_statuses = ['norm', 'failed', 'ptfailed'];

        if (in_array($status, $_statuses)) {
            return true;
        }

        return false;
    }

    public function clusterStatus()
    {
        if ($this->cluster_status) {
            return $this->cluster_status;
        }

        if ($this->cluster_info) {
            return $this->cluster_info['status'];
        }

        $this->cluster_info = $this->api->clusterInfo($this->cluster_name);
        $this->cluster_status = $this->cluster_info['status'];

        return $this->cluster_status;
    }

    public function validClusterParamKeys(array $params)
    {
        $valid_where_keys = ['status', 'type', 'cluster_name'];

        foreach ($params as $key => $value) {
            if (!in_array($key, $valid_where_keys)) {
                throw new ParameterInvalid("Invalid where condition: {$key}");
            }
        }
    }
}
