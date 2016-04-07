<?php
namespace Dag\Client\Model;

use Dag\Client\APIInterface;
use Dag\Client\Exception\ParameterInvalid;
use Dag\Client\Model;
use Traversable;

class TableCollection extends Model implements \IteratorAggregate
{
    use \Dag\Client\ClusterValidation;

    public $cluster_name;
    public $database_name;

    public function __construct(APIInterface $api, $cluster_name, $database_name)
    {
        parent::__construct($api);

        $this->cluster_name = $cluster_name;
        $this->database_name = $database_name;
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
        $marker = null;
        $truncated = false;
        do {
            $params = $this->makeOptions($marker);
            $table_info_list = $this->api->tableInfoList($this->cluster_name, $this->database_name, $params);
            foreach ($table_info_list['tables'] as $table_info) {
                yield new Table($this->api, $this->cluster_name, $this->database_name, $table_info);
            }
            $truncated = $table_info_list['isTruncated'];
            $marker = @$table_info_list['nextMarker'];
        } while ($truncated);
    }

    #
    # == parameters ==
    # * <tt>table</tt> - table name
    # * <tt>format</tt> - 'csv' or 'tsv' or 'json' or 'json_agent'
    # * <tt>schema/tt> - schema
    # * <tt>comment</tt> - comment
    public function create($table = '', $format = null, $schema = null, $comment = null)
    {
        $params = [
            'table' => $table,
            'schema' => $schema,
            'create_api' => true
        ];
        if($format) $params = array_merge($params, ['format' => $format]);
        if($comment) $params = array_merge($params, ['comment' => $comment]);

        $this->api->tableCreate($this->cluster_name, $this->database_name, $params);
        $table_info = $this->api->tableInfo($this->cluster_name, $this->database_name, $table);
        return new Table($this->api, $this->cluster_name, $this->database_name, $table_info);
    }

    private function makeOptions($marker)
    {
        $options = ['max' => 100];
        if ($marker) {
            array_merge($options, ['marker' => $marker]);
        }
        return $options;
    }
}
