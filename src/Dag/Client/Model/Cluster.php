<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Exception\StatusInvalid;
use Dag\Client\Model;

/**
 * Class Cluster
 * @package Dag\Client\Model
 */
class Cluster extends Model
{
    use \Dag\Client\ClusterValidation;

    public $cluster_name;
    public $name;
    public $status;
    public $type;
    public $instances;
    public $debug;

    public function __construct(APIInterface $api, $cluster)
    {
        parent::__construct($api);

        $this->cluster_name = @$cluster['name'];
        $this->name = @$cluster['name'];
        $this->status = @$cluster['status'];
        $this->type = @$cluster['type'];
        $this->instances = @$cluster['instances'];
        $this->debug = @$cluster['debug'];
    }

    public function restart(array $params = [])
    {
        if (!$this->validClusterRestartStatus($this->status)) {
            throw new StatusInvalid("Cluster status is Invalid: {$this->status}");
        }

        $force = false;
        if (array_key_exists('force', $params)) {
            $force = $params['force'];
        }

        $default = [
            'force' => $force,
            'type' => $this->type,
            'debug' => $this->debug
        ];

        return $this->api->clusterRestart($this->name, array_merge($default, $params));
    }

    public function exportLog(array $params = [])
    {
        if (!$this->clusterNormOrPtfailed($this->status)) {
            throw new StatusInvalid("Cluster status is invalid: {$this->status}");
        }

        $default = [
            'compress' => false
        ];

        return $this->api->clusterExportLog($this->name, array_merge($default, $params));
    }

    public function statistics()
    {
        $statistics = null;

        if ($this->validClusterStatus($this->status) && $this->clusterNormOrPtfailed($this->status)) {
            $statistics = $this->api->clusterStatistics($this->name);
        }

        return $statistics;
    }
}
