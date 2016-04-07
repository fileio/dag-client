<?php
namespace Dag\Client;

use Dag\Client\API\RestParameterInterface;
use Dag\Client\Exception\APIFailure;
use Dag\Client\Exception\ParameterInvalid;
use Dag\Client\Exception\APIOptionInvalid;
use Dag\Settings;

/**
 * Class API
 * @package Dag\Client
 */
class API implements APIInterface
{
    use API\Cluster;
    use API\Database;
    use API\Table;
    use API\Job;
    use API\Storage;

    private $client;
    private $access_key_id;
    private $secret_access_key;

    public $analysis_api;
    public $storage_api;
    public $force_path_style;
    public $debug;

    public function __construct($access_key_id, $secret_access_key, array $params = [])
    {
        $this->access_key_id = $access_key_id;
        $this->secret_access_key = $secret_access_key;

        if (array_key_exists('analysis_api', $params)) {
            $this->analysis_api = $params['analysis_api'];
        } else {
            $this->analysis_api = Settings::value('analysis_api');
        }

        if (array_key_exists('storage_api', $params)) {
            $this->storage_api = $params['storage_api'];
        } else {
            $this->storage_api = Settings::value('storage_api');
        }

        if (array_key_exists('force_path_style', $params)) {
            if (!is_bool($params['force_path_style'])) throw new APIOptionInvalid("force_path_style is not boolean:{$params['force_path_style']}");
            $this->force_path_style = $params['force_path_style'];
        } else {
            $this->force_path_style = Settings::value('force_path_style');
        }

        if (array_key_exists('debug', $params)) {
            if (!is_bool($params['debug'])) throw new APIOptionInvalid("force_path_style is not boolean:{$params['debug']}");
            $this->debug = $params['debug'];
        } else {
            $this->debug = Settings::value('debug');
        }

        $this->client = new \GuzzleHttp\Client();
    }

    private $_reader = [
        'access_key_id'
    ];

    public function __get($property)
    {
        if (in_array($property, $this->_reader)) {
            return $this->$property;
        }

        throw new \Exception("Property {$property} is not accessible");
    }

    public function execute($kind, RestParameterInterface $rest_parameter, $block = null)
    {
        $response = $this->handleAPIFailure($rest_parameter, function() use ($kind, $rest_parameter, $block) {
                return $this->restClient($kind, $rest_parameter, $block);
            });

        if ($response) {
            if ($kind === @ANALYSIS) {
                return $response->json();
            } elseif ($kind === @STORAGE) {
                if ($rest_parameter->raw_data) {
                    $body = $response->getBody();
                    $data = '';
                    while (!$body->eof()) {
                        $data .= $body->read(1024);
                    }
                } else {
                    $data = $response->xml();
                    $headers = $data->addChild('headers');
                    foreach ($response->getHeaders() as $key => $value) {
                        $headers->addChild($key, $value[0]);
                    }
                }
                return $data;
            }
        }

        throw new APIFailure();
    }

    public function downloadSignature($expire_at, $bucket, $output_object)
    {
        $http_verb = "GET\n";
        $content_md5 = "\n";
        $content_type = "\n";
        $expire = "{$expire_at}\n";

        $string_to_sign = $http_verb . $content_md5 . $content_type . $expire .
            $this->canonicalizedResource($bucket, $output_object);

        $digest = hash_hmac('sha1', $string_to_sign, $this->secret_access_key, true);
        return trim(base64_encode($digest));
    }

    private function handleAPIFailure(RestParameterInterface $rest_parameter, $block)
    {
        $response = null;

        try {
            $response = $block();

            return $response;

        } catch(\GuzzleHttp\Exception\ConnectException $e) {
            throw $e;

        } catch(\GuzzleHttp\Exception\RequestException $e) {
            $msg = "API Failure {$rest_parameter}";
            $api_failure = new APIFailure($msg);

            if (!$e->hasResponse()) {
                throw $api_failure;
            }

            if ($e->getResponse()->getBody() == null) {
                throw $api_failure;
            }

            $response_body = $e->getResponse()->getBody()->getContents();
            $json_response = json_decode($response_body, true);
            if ($json_response) {
                $api_failure->api_code = @$json_response['code'];
                $api_failure->api_message = @$json_response['message'];
                $api_failure->api_status = @$json_response['status'];
                $api_failure->api_request_id = @$json_response['requestId'];
                $api_failure->api_resource = @$json_response['resource'];
            } else {
                $xml_doc = @simplexml_load_string($response_body);
                if ($xml_doc) {
                    $api_failure->api_code = (string)$xml_doc->Code;
                    $api_failure->api_message = (string)$xml_doc->Message;
                    $api_failure->api_status = (string)$xml_doc->Status;
                    $api_failure->api_request_id = (string)$xml_doc->RequestId;
                    $api_failure->api_resource = (string)$xml_doc->Resource;
                } else {
                    $api_failure->api_code = null;
                    $api_failure->api_message = $response;
                    $api_failure->api_status = $e->getCode();
                    $api_failure->api_request_id = null;
                    $api_failure->api_resource = $rest_parameter->resource;
                }
            }
            throw $api_failure;
        }
    }

    protected function restClient($kind, RestParameterInterface $rest_parameter, $block)
    {
        $url = $rest_parameter->url($this->hostUri($kind));

        $rest_parameter->headers = array_merge($rest_parameter->headers, [
                'Authorization' => $rest_parameter->authentication($this->access_key_id, $this->secret_access_key),
                'Date' => $rest_parameter->calcDate(),
                'Host' => $this->hostName($kind, $rest_parameter->bucket),
                'Accept' => '*/*; q=0.5, application/xml',
                'Accept-Encoding' => 'gzip, deflate',
                'User-Agent' => 'dag-client-php (' . \Dag\Client::VERSION . ')'
            ]);

        $parameters = $rest_parameter->parameters;

        if (!array_key_exists('Content-Type', $rest_parameter->headers)) {
            if ($rest_parameter->content_type) {
                $rest_parameter->headers = array_merge($rest_parameter->headers, [
                        'Content-Type' => $rest_parameter->content_type
                    ]);
            }
        }

        $payload = null;
        if ($parameters || $rest_parameter->blank_body) {
            $payload = json_encode($parameters);
        } elseif (is_object($block) && $block instanceOf \Closure) {
            $payload = $block();
        }

        if (!array_key_exists('Content-Length', $rest_parameter->headers)) {
            $rest_parameter->headers = array_merge($rest_parameter->headers, [
                    'Content-Length' => ($payload && !is_string($payload)) ? fstat($payload)['size'] : strlen($payload)
                ]);
        }

        $method = $rest_parameter->method;
        $default = ['headers' => $rest_parameter->headers, 'debug' => $this->debug];
        switch ($method) {
            case "get":
                return $this->client->get($url, $default);

            case "post":
                return $this->client->post($url, array_merge($default, ['body' => $payload]));

            case "put":
                return $this->client->put($url, array_merge($default, ['body' => $payload]));

            case "delete":
                return $this->client->delete($url, $default);

            case "head":
                return $this->client->head($url, $default);

        }
    }

    private function hostName($kind, $bucket)
    {
        $uri = $this->hostUri($kind);
        $host = $uri['host'];

        if ($kind === @STORAGE) {
            if ($this->validIP($host)) {
                return $host;
            }

            if (!$this->force_path_style && $bucket) {
                $host = "{$bucket}.{$host}";
            }
        }

        if (array_key_exists('port', $uri) && ($uri['port'] != 80 || $uri['port'] != 443)) {
            $host .= ":{$uri['port']}";
        }

        return $host;
    }

    private function validIP($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        } else {
            return false;
        }
    }

    private function hostUri($kind)
    {
        if ($kind === @ANALYSIS) {
            $host_url = $this->analysis_api;
        } elseif ($kind === @STORAGE) {
            $host_url = $this->storage_api;
        } else {
            throw new ParameterInvalid("Illegal kind: {$kind}");
        }

        return parse_url($host_url);
    }

    private function canonicalizedResource($bucket, $object)
    {
        if ($this->force_path_style) {
            $result = $object;
        } else {
            $result = "/{$bucket}{$object}";
        }
        return $result;
    }
}
