<?php

namespace PayplusGateway\PayplusApi;

class Ipn extends PayplusBase{
    public $payment_request_uid;
    public $transaction_uid;
    public $details;

    public function IsSuccess()
    {
        if ($this->actionPerformed === false
        || !$this->details
        || $this->details->status_code != '000'
        ) {
            return false;
        }

        return true;
    }

    protected function GetCommandAndMethod()
    {
        return (object)[
            'command'=>'PaymentPages/ipn',
            'method'=>'POST'
        ];
    }

    protected function validate() {
        if (!$this->transaction_uid && !$this->payment_request_uid) {
            $this->errors[] = 'missing-both-transaction_id-andpayment_request_uid';
        }
    }

    protected function createPayload() {
        $payload = [];
        if ($this->payment_request_uid) {
            $payload['payment_request_uid'] = $this->payment_request_uid; 
        }
        if ($this->transaction_uid) {
            $payload['transaction_uid'] = $this->transaction_uid; 
        }

        return $payload;
    }

    public function Init(array $initData) {
        $hasEither = false;
        if ($initData['payment_request_uid']) {
            $this->payment_request_uid = $initData['payment_request_uid'];
            $hasEither = true;
        }
        if ($initData['transaction_uid']) {
            $this->transaction_uid = $initData['transaction_uid'];
            $hasEither = true;
        }

        return $hasEither;
    }

    protected function successfulResponse($data) {
        $this->details = $data->result;
    }
    
}