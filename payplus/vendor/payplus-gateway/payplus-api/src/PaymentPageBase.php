<?php

namespace PayplusGateway\PayplusApi;

abstract class PaymentPageBase extends PayplusBase {
    public $payment_page_uid;
    protected $__items = [];
    protected $__shipping;
    protected $__customer;
    protected $__secure3d;

    public $amount;
    public $currency_code;
    public $charge_method;
    public $charge_default;
    public $language_code;
    public $sendEmailApproval;
    public $sendEmailFailure;
    public $expiry_datetime;
    public $more_info;
    public $refURL_success;
    public $refURL_failure;
    public $refURL_cancel;
    public $refURL_callback;
    public $custom_invoice_name;
    public $create_token;
    public $initial_invoice;
    public $invoice_language;
    public $paying_vat;
    public $payments;
    public $payments_selected;
    public $hide_identification_id;
    public $hide_payments_field;
    public $token;

    protected function GetCommandAndMethod()
    {
        return (object)[
            'command'=>'PaymentPages/generateLink',
            'method'=>'POST'
        ];
    }
    
    public function SetSecure3d(array $data) {
        if (!isset($data['activate'])) {
            return false;
        }
        $this->__secure3d['activate'] = $data['activate'];
        foreach([
            'id',
            'phone'
        ] as $fld) {
            if (isset($data[$fld])) {
                $this->__secure3d[$fld] = $data[$fld];
            }
        }
        return true;
    }

    public function SetCustomer(array $data) {
        if (!$data['customer_name'] || !isset($data['email'])) {
            return false;
        }

        $this->__customer = $this->initObject($data,[
            'customer_name',
            'email',
            'customer_uid',
            'full_name',
            'vat_number',
            'customer_type',
            'phone',
            'cell_phone',
            'address',
            'city',
            'country_ISO',
        ]);
        return true;
    }

    public function AddItem(array $data) {
        if (!$data['name'] || !isset($data['quantity']) || !isset($data['price'])) {
            return false;
        }

        $item = $this->initObject($data,[
            'name',
            'quantity',
            'price',
            'product_invoice_extra_details',
            'product_uid',
            'image_url',
            'category_uid',
            'barcode',
            'value',
            'discount_type',
            'discount_value',
            'vat_type'
        ]);
        
        $this->__items[] = $item;
        return true;
    }

    public function SetShipping($name, $price) {
        $this->__shipping = [
            "name" =>$name,
            "price" =>$price
        ];
    }
    
    protected function createPayload()
    {
        $payload = $this->initObject($this,[
            'currency_code',
            'payment_page_uid',
            'amount',
            'charge_method',
            'charge_default',
            'language_code',
            'sendEmailApproval',
            'expiry_datetime',
            'more_info',
            'refURL_success',
            'refURL_failure',
            'refURL_cancel',
            'refURL_callback',
            'custom_invoice_name',
            'create_token',
            'initial_invoice',
            'invoice_language',
            'paying_vat',
            'payments',
            'payments_selected',
            'hide_identification_id',
            'hide_payments_field',
            'token',
            'use_token',
            'credit_terms',
        ]);
        if (!empty($this->__items)) {
            $payload['items'] = $this->__items;
        }
        if ($this->__shipping) {
            $payload['items'][] = [
                'name'=>$this->__shipping['name'],
                'price'=>$this->__shipping['price'],
                'quantity'=>1,
                'shipping'=>true
            ];
        }

        if ($this->__customer) {
            $payload['customer'] = $this->__customer;
        }
        if ($this->__secure3d) {
            $payload['secure3d'] = $this->__secure3d;
        }

        return $payload;
    }

    protected function validate()
    {
        if (!$this->currency_code) {
            $this->errors[] = 'missing-currency-code';
        }
        if (!$this->payment_page_uid) {
            $this->errors[] = 'missing-page-uid';
        }
        if (!isset($this->amount)) {
            $this->errors[] = 'missing-amount';
        }
    }
}