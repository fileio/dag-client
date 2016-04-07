<?php
namespace Dag\Client\Model;

use Dag\Client\Exception\ParameterInvalid;
use Dag\Client\Model;
use Traversable;

class ClusterCollection extends Model implements \IteratorAggregate
{
    use \Dag\Client\ClusterValidation;

    private $status;
    private $type;
    private $cluster_name;
    private $order;

    public function where(array $params)
    {
        $this->validClusterParamKeys($params);

        if (array_key_exists('status', $params)) {
            $this->status = $params['status'];
        }

        if (array_key_exists('type', $params)) {
            $this->type = $params['type'];
        }

        if (array_key_exists('cluster_name', $params)) {
            $this->cluster_name = $params['cluster_name'];
        }

        return $this;
    }

    public function order($o)
    {
        $o = strtolower($o);
        $_order = ["asc", "desc"];

        if (!in_array($o, $_order)) {
            throw new ParameterInvalid("Invalid order condition: {$o}");
        }

        $this->order = $o;

        return $this;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     */
    public function getIterator()
    {
        $truncated = null;
        $marker = null;
        do {
            $cluster_info_list = $this->api->clusterInfoList($this->makeOptions($marker));
            if (is_array($cluster_info_list['clusters'])) {
                foreach ($cluster_info_list['clusters'] as $cluster) {
                    yield new Cluster($this->api, $cluster);
                }
            }
            $truncated = $cluster_info_list['isTruncated'];
            $marker = @$cluster_info_list['nextMarker'];
        } while ($truncated);
    }

    private function makeOptions($marker = null)
    {
        $options = ['max' => 100];

        if ($marker) {
            $options = array_merge($options, ['marker' => $marker]);
        }

        if ($this->order) {
            $options = array_merge($options, ['order' => $this->order]);
        }

        if ($this->status) {
            if (is_array($this->status)) {
                $this->status = implode(",", $this->status);
            }
            $options = array_merge($options, ['status' => $this->status]);
        }

        if ($this->type) {
            $options = array_merge($options, ['type' => $this->type]);
        }

        if ($this->cluster_name) {
            $options = array_merge($options, ['cluster_name' => $this->cluster_name]);
        }

        return $options;
    }

}
