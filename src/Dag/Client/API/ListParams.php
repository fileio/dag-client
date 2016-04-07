<?php
namespace Dag\Client\API;

use Dag\Client\Exception\ParameterInvalid;

/**
 * Class ListParams
 * @package Dag\Client\API
 */
class ListParams
{
    public static function listParams($params = [])
    {
        $list_params = [];

        if (array_key_exists('max', $params)) {
            $max = $params['max'];

            if (!is_numeric($max)) {
                throw new ParameterInvalid('max should be integer');
            } else {
                $max = (int)$max;
            }

            if ($max < 1) {
                throw new ParameterInvalid("max should be grater then 0: {$max}");
            }

            if ($max > 100) {
                throw new ParameterInvalid("max should be less then 100 or equal to 100: {$max}");
            }

            $list_params = array_merge($list_params, ['max' => $max]);
        }

        if (array_key_exists('marker', $params)) {
            $marker = $params['marker'];
            $list_params = array_merge($list_params, ['marker' => $marker]);
        }

        return $list_params;
    }
}