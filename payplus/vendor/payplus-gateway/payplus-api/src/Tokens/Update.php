<?php

namespace PayplusGateway\PayplusApi\Tokens;

use PayplusGateway\PayplusApi\PayplusBase;

class Update extends PayplusBase {
    public $uid;
    public $terminal_uid;
    public $credit_card_number;
    public $card_date_mmyy;
    public $name;
    private $success = false;
    protected function validate() {
        if (!$this->uid) {
            $this->errors[] = 'missing-uid';
        }
        if (!$this->terminal_uid) {
            $this->errors[] = 'missing-terminal_uid';
        }
        if (!$this->credit_card_number) {
            $this->errors[] = 'missing-credit_card_number';
        }
        if (!$this->card_date_mmyy) {
            $this->errors[] = 'missing-card_date_mmyy';
        }
    }

    protected function createPayload() {
        $payload = [
            'terminal_uid'=>$this->terminal_uid,
            'credit_card_number'=>$this->credit_card_number,
            'card_date_mmyy'=>$this->card_date_mmyy,
        ];

        return $payload;
    }

    public function Init(array $initData) {
        if (!$initData['uid'] || !$initData['credit_card_number'] || !$initData['card_date_mmyy'] || !$initData['terminal_uid']) {
            return false;
        }
        $this->uid = $initData['uid'];
        $this->terminal_uid = $initData['terminal_uid'];
        $this->credit_card_number = $initData['credit_card_number'];
        $this->card_date_mmyy = $initData['card_date_mmyy'];
        $this->name = $initData['name'];
        return true;
    }

    protected function successfulResponse($data) {
        $this->success = true;
    }

    public function IsSuccess() {
        return $this->success;
    }

    protected function GetCommandAndMethod() {
        $result = new \stdClass;
        $result->method = 'POST';
        $result->command = 'Token/Update/'.$this->uid;

        return $result;
    }
}