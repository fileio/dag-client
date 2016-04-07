<?php
namespace Dag\Client\API;

/**
 * Interface RestParameterInterface
 * @package Dag\Client\API
 */
interface RestParameterInterface
{
    public function url(array $uri, $force_path_style = false);
    public function calcDate();
    public function authentication($access_key_id, $secret_access_key);
}