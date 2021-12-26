<?php
/**
 * Low level API for integration with the PayPlus payment processing gateway
 * @package Payplus-API
 * @author PayPlus LTD <info@payplus.co.il> https://www.payplus.co.il
 * @since 1.0.0
 */
namespace PayplusGateway\PayplusApi;

class TokenPay extends PaymentPageBase {
    protected static $API_URI = "PaymentPages/generateLink";
    protected static $API_METHOD = 'POST';

    public function Init(array $initData)
    {
        foreach([
            'payment_page_uid',
            'amount',
            'currency_code',
            'token',
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

    protected function validate()
    {
        parent::validate();
        if (!$this->token) {
            $this->errors[] = 'missing-token';
        }
    }

    public function IsSuccess()
    {
        if ($this->actionPerformed === true) {
            if (isset($this->Response) && $this->Response->success == 1)
            return true;
        }
        
        return false;
    }

    protected function createPayload() {
        $payload = parent::createPayload();
        $payload['token'] = $this->token;
        $payload['use_token'] = true;
        return $payload;
    }

    protected function successfulResponse($data) {}
}