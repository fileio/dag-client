<?php
namespace Dag\Client\API;

/**
 * Class RestParameter
 * @package Dag\Client\API
 */
class RestParameter implements RestParameterInterface
{
    private $method;
    private $resource;
    private $cano_resource;
    private $query_params;
    private $parameters;
    private $bucket;
    private $content_type;
    private $headers;
    private $raw_data;
    private $blank_body;

    public function __construct($method, $resource, array $params = []) {
        $this->method = $method;
        $this->resource = $resource;
        $this->cano_resource = @$params['cano_resource'];
        $this->query_params = @$params['query_params'];
        $this->parameters = @$params['parameters'];
        $this->bucket = @$params['bucket'];
        $this->content_type = @$params['content_type'];
        $this->raw_data = @$params['raw_data'];
        $this->blank_body = @$params['blank_body'];

        if (array_key_exists('headers', $params)) {
            $this->headers = $params['headers'];
        } else {
            $this->headers = [];
        }
    }

    private $_reader = [
        'method', 'resource', 'cano_resource', 'query_params', 'parameters', 
        'bucket', 'content_type', 'headers', 'raw_data', 'blank_body'
    ];

    public function __get($property)
    {
        if (in_array($property, $this->_reader)) {
            return $this->$property;
        }

        throw new \Exception("Property {$property} is not accessible");
    }

    private $_writer = ['headers'];

    public function __set($property, $value)
    {
        if (in_array($property, $this->_writer)) {
            return $this->$property = $value;
        }

        throw new \Exception("Property {$property} is not accessible");
    }

    public function url(array $uri, $force_path_style = false)
    {
        $url = $uri['host'];
        if (array_key_exists('port', $uri)) {
            if ($uri['port'] != 80 || $uri['port'] != 443) {
                $url .= ":{$uri['port']}";
            }
        }

        if ($this->bucket) {
            if ($force_path_style) {
                if (substr($url, -1) != "/") {
                    $url .= "/";
                }

            } else {
                $url = join('.', [$this->bucket, $url]);
                if (substr($url, -1) != "/") {
                    $url .= "/";
                }
            }
        }

        if (!$this->bucket || $this->resource != "/") {
            $url = join('/', [rtrim($url, '/'), ltrim($this->resource, '/')]);
        }

        $url_array = array_filter(explode('/', $url));
        if (end($url_array) == $this->bucket) {
            $url .= '/';
        }

        if ($this->cano_resource || $this->query_params) {
            $url .= '?';
        }

        if ($this->cano_resource) {
            $url .= $this->cano_resource;
        }

        if ($this->cano_resource && $this->query_params) {
            $url .= '&';
        }

        if ($this->query_params) {
            $url .= http_build_query($this->query_params, null, '&');
        }

        return $uri['scheme'] . '://' . $url;
    }

    private function httpVerb()
    {
        return strtoupper($this->method);
    }

    private function signatureContentType()
    {
        $result = "";

        if ($this->content_type) {
            $result .= $this->content_type;
        }

        $result .= "\n";

        return $result;
    }

    public function authentication($access_key_id, $secret_access_key)
    {
        return 'IIJGIO' . ' ' . $access_key_id . ':' . $this->signature($secret_access_key);
    }

    private function signature($secret_access_key)
    {
        $http_verb = $this->httpVerb() . "\n";
        $content_md5 = "\n";
        $content_type = $this->signatureContentType();
        $date = $this->calcDate() . "\n";

        $canonicalized_iijgio_headers = "";

        $string_to_sign = $http_verb . $content_md5 . $content_type . $date .
            $canonicalized_iijgio_headers . $this->canonicalied_resource();

        $digest = hash_hmac('sha1', $string_to_sign, $secret_access_key, true);

        return trim(base64_encode($digest));
    }

    private function canonicalied_resource()
    {
        $result = '';

        if ($this->bucket) {
            $result = '/';
            $result .= "{$this->bucket}/";
        }

        if (!$this->bucket || $this->resource != '/') {
            $result = join('/', [rtrim($result, '/'), ltrim($this->resource, '/')]);
        }

        if ($this->cano_resource) {
            $result .= "?{$this->cano_resource}";
        }

        return $result;
    }

    public function calcDate()
    {
        return gmdate('D, d M Y H:i:s \G\M\T', time());
    }

    public function __toString()
    {
        $_params = [
            "method: {$this->method}",
            "resource: {$this->resource}",
            "cano_resource: {$this->cano_resource}",
            "query_params: " . serialize($this->query_params),
            "bucket: {$this->bucket}",
            "parameters: " . serialize($this->parameters),
            "headers: " . serialize($this->headers)
        ];

        return join(', ', $_params);
    }
}
