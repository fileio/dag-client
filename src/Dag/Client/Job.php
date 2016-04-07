<?php
namespace Dag\Client;

/**
 * Class Job
 * @package Dag\Client
 */
trait Job
{
    public function jobs($params = [])
    {
        return new Model\JobCollection($this->api);
    }

    public function job($job_id)
    {
        $job_info = $this->api->queryInfo($job_id);
        return new Model\Job($this->api, $job_info);
    }

    public function jobCancel($job_id)
    {
        $job = $this->job($job_id);
        $job->validCancelCondition();
        $this->api->queryCancel($job_id);
    }

    public function query(array $params)
    {
        $this->validCluster();

        if (!array_key_exists('output_format', $params)) {
            $params = array_merge($params, ['output_format' => 'csv']);
        }
        $params = array_merge($params, ['cluster_name' => $this->cluster_name]);
        $select_info = $this->api->query($params);

        $job_id = $select_info['queryId'];
        return $this->job($job_id);
    }
}
