<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Exception\ParameterInvalid;
use Dag\Client\Model;
use Traversable;

class DatabaseCollection extends Model implements \IteratorAggregate
{
    use \Dag\Client\ClusterValidation;

    public $cluster_name;

    public function __construct(APIInterface $api, $cluster_name)
    {
        parent::__construct($api);

        $this->cluster_name = $cluster_name;
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
        if (empty($this->databases)) {
            $this->find_from_api();
        }

        foreach ($this->databases as $db_name) {
            #echo "$this->cluster_name" . " : " . "$db_name\n";
            yield new Database($this->api, $this->cluster_name, $db_name);
        }
    }

    public function create($db_name)
    {
        $this->validCluster();

        $this->api->databaseCreate($this->cluster_name, $db_name);
        return new Database($this->api, $this->cluster_name, $db_name);
    }

    private function find_from_api()
    {
        $dbs = [];
        $tmp = [];
        $params = ['max' => 100];
        do {
            if (array_key_exists('nextMarker', $tmp)) {
                $params = array_merge($params, array('marker'. $tmp['nextMarker']));
            }
            $tmp = $this->api->databaseList($this->cluster_name, $params);
            $dbs += $tmp['databases'];
        } while ($tmp['isTruncated']);

        $this->databases = $dbs;
        return $this->databases;
    }
}
