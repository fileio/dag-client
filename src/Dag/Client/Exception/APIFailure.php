<?php
namespace Dag\Client\Exception;

/**
 * Class APIFailure
 * @package Dag\Client\Exception
 */
class APIFailure extends \Exception
{
    public $api_code;
    public $api_message;
    public $api_status;
    public $api_request_id;
    public $api_resource;
}
