<?php
namespace Dag\Client\Model;

use Dag\Client\Exception\ParameterInvalid;
use Dag\Client\Model;
use Traversable;

class JobCollection extends Model implements \IteratorAggregate
{
    use \Dag\Client\JobValidation;

    private $status;
    private $type;
    private $cluster_name;
    private $limit;
    private $max;
    private $order;
    private $label;
    private $cluster_rebooted;

    public function where(array $params)
    {
        $this->validJobParamKeys($params);

        if (array_key_exists('status', $params)) {
            $this->status = $params['status'];
        }

        if (array_key_exists('type', $params)) {
            $this->type = $params['type'];
        }

        if (array_key_exists('cluster_name', $params)) {
            $this->cluster_name = $params['cluster_name'];
        }

        if (array_key_exists('label', $params)) {
            $this->label = $params['label'];
        }

        if (array_key_exists('cluster_rebooted', $params)) {
            $this->cluster_rebooted = $params['cluster_rebooted'];
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

    public function limit($number = 100)
    {
        $this->limit = intval($number);
        $max = 100;

        if ($number > $max) {
            $this->max = array();
            $times = $number / $max;
            for ($i = 1; $i <= $times; $i++) {
                array_push($this->max, $max);
            }
            $rem = $number % $max;
            if ($rem != 0) {
                array_push($this->max, $rem);
            }
        }

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
        if ($this->limit) {
            $count = 0;
            $i = 0;
            $total = $this->max ? array_sum($this->max) : $this->limit;
        }

        $truncated = null;
        $marker = null;
        do {
            if ($this->max) $this->limit = $this->max[$i];

            $job_info_list = $this->api->queryInfoList($this->makeOptions($marker));
            if (is_array($job_info_list['queries'])) {
                foreach ($job_info_list['queries'] as $job) {
                    yield new Job($this->api, $job);
                }
            }
            $truncated = $job_info_list['isTruncated'];
            $marker = @$job_info_list['nextMarker'];

            if ($this->limit) {
                $i++;
                $count += $this->limit;
                if ($total <= $count) break;
            }
        } while ($truncated);
    }

    private function makeOptions($marker = null)
    {
        $options = ['max' => 100];

        if ($marker) {
            $options = array_merge($options, ['marker' => $marker]);
        }

        if ($this->limit) {
            $options = array_merge($options, ['max' => $this->limit]);
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

        if ($this->label) {
            $options = array_merge($options, ['label_prefix' => $this->label]);
        }

        if (!is_null($this->cluster_rebooted)) {
            $options = array_merge($options, ['cluster_rebooted' => $this->cluster_rebooted]);
        }

        return $options;
    }

}
