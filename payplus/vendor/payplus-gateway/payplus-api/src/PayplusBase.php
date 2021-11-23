<?php

namespace PayplusGateway\PayplusApi;

abstract class PayplusBase {
    public $debugResponse = false;
    public $Response;
    public static $apiKey;
    public static $secretKey;
    public static $devMode = false;
    protected $errors = [];
    protected $actionPerformed = false;

    public function __construct()
    {
        if (!self::$apiKey || !self::$secretKey) {
            throw new \Exception('missing-api-credentials');
        }
    }
    public function Go() {
        $this->actionPerformed = true;
        $payload = $this->createPayload();
        $this->validate();
        if (!empty($this->errors)) {
            return $this;
        }
        $this->Response = $this->makeRequest($payload);
        
        if ($this->Response->success == 1) {
            $this->successfulResponse($this->Response);
        }
        return $this;
    }

    public function GetErrors() {
        return $this->errors;
    }

    private function getAccessAddress() {
        $commandAndMethod = $this->GetCommandAndMethod();
        $addr = '';
        if (self::$devMode === true) {
            $addr = 'https://restapidev.payplus.co.il/api/v1.0';
        } else {
            $addr = 'https://restapi.payplus.co.il/api/v1.0';
        }
        return $addr . '/' . trim($commandAndMethod->command,'/');
    }

    protected function makeRequest($payload = null) {
        $commandAndMethod = $this->GetCommandAndMethod();
        $authorization = [
            'api_key'=>self::$apiKey,
            'secret_key'=>self::$secretKey
        ];
        $ch = curl_init($this->getAccessAddress());
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type:application/json',
            'Authorization: '.json_encode($authorization),
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WHMCS-PP-87');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($commandAndMethod->method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        if ($commandAndMethod->method != 'GET' && $payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response);
        $result = new \stdClass;
        if (isset($response->results->status) && $response->results->status == 'success') {
            $result->success = 1;
            $result->result = $response->data;
            return $result;
        }
        if (isset($response->status) && $response->status == 'success') {
            $result->success = 1;
            $result->result = $response->data;
            return $result;
        }
        $result->success = 0;
        if (isset($response->message) && $response->message) {
            $result->result = $response->message;
            $this->errors[] = $response->message;
            return $result;
        }
        if (isset($response->results->status) && $response->results->status == 'error') {
            $result->result = $response->results->description;
            $this->errors[] = $response->results->description;
            return $result;
        }
        $result->result = 'other-error';
        $this->errors[] = $result->result;
        
        return $result;
    }

    protected function initObject($data,$items) {

        $result = [];
        foreach($items as $fld) {
            if (is_object($data)) {
                if (isset($data->{$fld})) {
                    $result[$fld] = $data->{$fld};
                }
            } else {
                if (isset($data[$fld])) {
                    $result[$fld] = $data[$fld];
                }
            }

        }
        return $result;
    }

    abstract protected function validate();
    abstract protected function createPayload();
    abstract public function Init(array $initData);
    abstract protected function successfulResponse($data);
    abstract public function IsSuccess();
    abstract protected function GetCommandAndMethod();
}