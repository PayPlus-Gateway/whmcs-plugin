<?php

namespace PayplusGateway\PayplusApi;

class PaymentPage extends PaymentPageBase {
    public $payment_page_link;
    public function Init(array $initData)
    {
        foreach([
            'payment_page_uid',
            'amount',
            'currency_code',
        ] as $fld) {
            if (!isset($initData[$fld])) {
                $this->errors[] = 'missing-'.$fld;
            } else {
                $this->{$fld} = $initData[$fld];
            }
        }
        if (!empty($this->errors)) {
            return false;
        }
        return true;
    }

    public function IsSuccess()
    {
        if ($this->actionPerformed === false || !$this->payment_page_link) {
            return false;
        }
        return true;
    }


    protected function successfulResponse($data)
    {
        $this->page_request_uid = $data->result->page_request_uid;
        $this->payment_page_link = $data->result->payment_page_link;
    }


    public function __toString()
    {
        if ($this->payment_page_link) {
            return $this->payment_page_link;
        }
        return 'error';
    }
}