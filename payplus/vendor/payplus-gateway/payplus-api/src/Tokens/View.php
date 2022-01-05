<?php
/**
 * Low level API for integration with the PayPlus payment processing gateway
 * @package Payplus-API
 * @author PayPlus LTD <info@payplus.co.il> https://www.payplus.co.il
 * @since 1.1.3
 */
namespace PayplusGateway\PayplusApi\Tokens;

use PayplusGateway\PayplusApi\PayplusBase;

class View extends PayplusBase {
    public $uid;

    protected function validate() {
        if (!$this->uid) {
            $this->errors[] = 'missing-uid';
        }
    }

    protected function createPayload() {
        $payload = [
            'terminal_uid'=>$this->uid
        ];

        return $payload;
    }

    public function Init(array $initData) {
        if (!$initData['uid']) {
            return false;
        }
        $this->uid = $initData['uid'];
    }

    protected function successfulResponse($data) {
        $this->details = $data->result;
        $this->success = true;
    }

    public function IsSuccess() {
        return $this->success;
    }

    protected function GetCommandAndMethod() {
        $result = new \stdClass;
        $result->method = 'GET';
        $result->command = 'Token/view/'.$this->uid;

        return $result;
    }

}