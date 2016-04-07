<?php
namespace Dag;

/**
 * Interface ClientInterface
 * @package Dag
 */
interface ClientInterface
{
    const VERSION = "0.0.0";

    public function open($cluster_name);
    public function clusters();
    public function cluster();
    public function databases();
    public function database($db_name);
    public function tableSplit($db_name, $tbl_name, $params);
    public function jobs();
    public function job($job_id);
    public function jobCancel($job_id);
    public function query(array $params);
    public function buckets();
    public function bucket($bucket_name);
    public function import($database_name, $table_name, $file_paths, array $options = []);
}
