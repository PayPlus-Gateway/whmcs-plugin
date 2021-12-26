<?php
/**
 * Low level API for integration with the PayPlus payment processing gateway
 * @package Payplus-API
 * @author PayPlus LTD <info@payplus.co.il> https://www.payplus.co.il
 * @since 1.0.0
 */
namespace PayplusGateway\PayplusApi\Tokens;

use PayplusGateway\PayplusApi\PayplusBase;

class Remove extends PayplusBase{
    public $uid;
    public $success = false;
    protected function GetCommandAndMethod()
    {
        return (object)[
            'command'=>'Token/Remove/' . $this->uid,
            'method'=>'POST'
        ];
    }
    protected function validate() {
        if (!$this->uid) {
            $this->errors[] = 'missing-token-uid';
        }
    }

    protected function createPayload() {
        return null;
    }

    public function Init(array $initData) {
        if (!$initData['uid']) {
            return false;
        }
        $this->uid = $initData['uid'];
        return true;
    }

    protected function successfulResponse($data) {
        $this->success = true;
    }

    public function IsSuccess() {
        return $this->success;
    }

}