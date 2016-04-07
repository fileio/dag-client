<?php
namespace Dag\Client;

use Dag\Client\API\RestParameterInterface;

/**
 * Interface APIInterface
 * @package Dag\Client
 */
interface APIInterface
{
    public function execute($kind, RestParameterInterface $rest_parameter);

    // Cluster
    public function clusterInfoList(array $params = []);
    public function clusterInfo($cluster_name);
    public function clusterRestart($cluster_name, array $params = []);
    public function clusterStatistics($cluster_name, array $params = []);
    public function clusterExportLog($cluster_name, array $params = []);

    // Database
    public function databaseList($cluster_name, array $params = []);
    public function databaseCreate($cluster_name, $database_name);
    public function databaseDelete($cluster_name, $database_name);

    // Table
    public function tableInfoList($cluster_name, $database_name, array $params = []);
    public function tableInfo($cluster_name, $database_name, $table_name);
    public function tableCreate($cluster_name, $database_name, array $params = []);
    public function tableSplit($cluster_name, $database_name, $table_name, array $params);
    public function tableDelete($cluster_name, $database_name, $table_namme);

    // Job
    public function queryInfoList(array $params = []);
    public function queryInfo($job_id);
    public function queryLog($job_id);
    public function queryCancel($job_id);
    public function query(array $params);

    // Storage Bucket
    public function buckets();
    public function bucketCreate($bucket);
    public function bucketDelete($bucket);

    // Storage Object
    public function objectCreate($bucket, $object, array $params = [], $block);
    public function objectCreateMultipart($bucket, $object, array $params = [], $block);
    public function objectGet($bucket, $object, $range = null);
    public function objectDelete($bucket, $object);
}
