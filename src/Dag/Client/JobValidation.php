<?php
namespace Dag\Client;

use Dag\Client\Exception\ParameterInvalid;

trait JobValidation
{
    public function validQueryInfoListStatus($statuses)
    {
        $_statuses = ['running', 'finished', 'canceled', 'error'];

        $statuses = array_map(function($s){return trim($s);}, explode(',', $statuses));
        foreach ($statuses as $status) {
            if (!in_array($status, $_statuses)) {
                throw new ParameterInvalid("status is invalid: {$status}");
            }
        }
    }

    public function validJobParamKeys(array $params)
    {
        $valid_where_keys = ['status', 'type', 'cluster_name', 'label', 'cluster_rebooted'];

        foreach ($params as $key => $value) {
            if (!in_array($key, $valid_where_keys)) {
                throw new ParameterInvalid("Invalid where condition: {$key}");
            }
        }
    }
}
