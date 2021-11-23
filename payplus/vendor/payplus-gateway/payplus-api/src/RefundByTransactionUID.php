<?php

namespace PayplusGateway\PayplusApi;

class RefundByTransactionUID extends PayplusBase{
    public $transaction_uid;
    public $amount;
    public $more_info;
    public $cvv;
    public $details;
    protected function GetCommandAndMethod()
    {
        return (object)[
            'command'=>'Transactions/RefundByTransactionUID',
            'method'=>'POST'
        ];
    }

    protected function validate() {
        if (!$this->transaction_uid) {
            $this->errors[] = 'missing-transaction-uid';
        }
        if ($this->amount === null) {
            $this->errors[] = 'missing-amount';
        }
    }

    protected function createPayload() {
        $payload = [];
        $payload = $this->initObject($this,[
            'transaction_uid',
            'amount',
            'more_info',
            'cvv'
        ]);
        return $payload;
    }

    public function Init(array $initData) {
        $this->transaction_uid = $initData['transaction_uid'];
        $this->amount = $initData['amount'];
        if (!$this->transaction_uid || $this->amount === null) {
            return false;
        }
        return true;
    }

    protected function successfulResponse($data) {
        $this->details = $data->result->transaction;
    }

    public function IsSuccess() {
        if ($this->details->status_code === '000') {
            return true;
        }
        return false;
    }

}