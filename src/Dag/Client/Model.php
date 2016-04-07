<?php
namespace Dag\Client;

use Dag\Client\Exception\ParameterInvalid;

/**
 * Class Model
 * @package Dag\Client
 */
class Model
{
    public $api;

    public function __construct(APIInterface $api)
    {
        $this->api = $api;
    }
}
