<?php

/**
 * WHMCS Sample Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * This sample file demonstrates how a payment gateway module for WHMCS should
 * be structured and all supported functionality it can contain.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "gatewaymodule" and therefore all functions
 * begin "payplus_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */


require_once "payplus/init.php";
require_once __DIR__ . '/../../includes/clientfunctions.php';
define('CURRENT_DEBUG_ACTION','main file');
use PayplusGateway\PayplusApi\ChargeMethods;
use PayplusGateway\PayplusApi\PaymentPage;
use PayplusGateway\PayplusApi\PayplusBase;
use PayplusGateway\PayplusApi\RefundByTransactionUID;
use PayplusGateway\PayplusApi\Tokens\Remove;
use PayplusGateway\PayplusApi\Tokens\Update;

require_once "payplus/vendor/autoload.php";
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */

function payplus_MetaData()
{
    return array(
        'DisplayName' => 'PayPlus',
        'APIVersion' => '1.1'
    );
}

function payplus_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'PayPlus Gateway',
        ),
        'devMode' => array(
            'FriendlyName' => 'Dev Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable dev mode',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter API Key here',
        ),
        'secretKey' => array(
            'FriendlyName' => 'Secret Key',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter secret key here',
        ),
        'paymentPageUID' => array(
            'FriendlyName' => 'Payment page UID',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter payment page UID here',
        ),
        'terminalUID' => array(
            'FriendlyName' => 'Terminal UID',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter terminal UID here',
        ),
        'vat_id_field_name' => array(
            'FriendlyName' => 'Vat ID field name',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter custom field name for vat ID if applicable',
        )
    );
}
function payplus_nolocalcc()
{
}

function payplus_storeremote($params)
{
    PayplusBase::$apiKey = $params['apiKey'];
    PayplusBase::$secretKey = $params['secretKey'];
    PayplusBase::$devMode = ($params['devMode'] == 'on');

    switch ($params['action']) {
        case REMOTE_STORE_ACTION_DELETE:
            $removeToken = new Remove;
            $removeToken->Init([
                'uid' => $params['remoteStorageToken']
            ]);
            $removeToken->Go();
            return [
                'status' => 'success'
            ];
            break;

        case REMOTE_STORE_ACTION_UPDATE:
            $tokenData = explode(TOKEN_TERMINAL_SEPARATOR, $params['gatewayid']);
            $tokenUID = $tokenData[0];
            $updateToken = new Update;
            $payment = $params['payMethod']->payment;

            $updateToken->Init([
                'uid' => $tokenUID,
                'terminal_uid' => $params['terminalUID'],
                'credit_card_number' => $params['cardlastfour'],
                'card_date_mmyy' => $params['cardexp'],
            ]);
            $exp = \WHMCS\Carbon::createFromDate($params['cardExpiryYear'], $params['cardExpiryMonth'], 1);
            if ($updateToken->Go()->IsSuccess()) {
                $payment->setExpiryDate($exp);
                $payment->save();
                return [
                    'status' => 'success',
                    'gatewayid'=>$tokenUID
                ];
            }
            break;
    }

    return [
        'status' => 'failed'
    ];
}
function payplus_capture($params)
{
    PayplusBase::$apiKey = $params['apiKey'];
    PayplusBase::$secretKey = $params['secretKey'];
    PayplusBase::$devMode = ($params['devMode'] == 'on');
    $params['gatewayid'] = explode(TOKEN_TERMINAL_SEPARATOR, $params['gatewayid']);
    $params['gatewayid'] = $params['gatewayid'][0];

    $paymentPage = new TokenPay;
    $paymentPage->Init([
        'payment_page_uid' =>  $params['paymentPageUID'],
        'currency_code' => $params['currency'],
        'amount' => $params['amount'],
        'token' => $params['gatewayid']
    ]);

    
    $orderData = $params['cart']->getInvoiceModel()->order()->getResults();
    $total = 0;
    foreach ($params['cart']->getInvoiceModel()->getBillingValues() as $item) {
        if (!isset($item['lineItemAmount'])) {
            continue;
        }
        $paymentPage->AddItem([
            'price' => $item['lineItemAmount'],
            'name' => $item['description'],
            'quantity' => 1
        ]);
        $total+= $item['lineItemAmount'];
    }
    
    if ($orderData && $orderData->promovalue) {
        $totalDiscount = $total - $params['amount'];
        if ($totalDiscount > 0) {
            $paymentPage->AddItem([
                'price' => $totalDiscount *=-1,
                'name' => $orderData->promocode ?? 'Discount',
                'quantity' => 1
            ]);
        }

    }

    $customer = [
        'customer_name' => ($params['clientdetails']['companyname']) ? $params['clientdetails']['companyname']:$params['clientdetails']['fullname'],
        'email' => $params['clientdetails']['email'],
        'vat_number' => $params['clientdetails']['tax_id'],
        'phone' => $params['clientdetails']['phonenumber'],
        'country' => $params['clientdetails']['countrycode'],
        'city' => $params['clientdetails']['city'],
        'address' => $params['clientdetails']['address1'],
    ];
    if ($params['vat_id_field_name']) {
        $customer['vat_number'] = $params['clientdetails'][$params['vat_id_field_name']];
    }
    
    $paymentPage->SetCustomer($customer);
    $paymentPage->charge_method = ChargeMethods::CHARGE;
    $paymentPage->Go();
    if ($paymentPage->IsSuccess()) {
        logModuleCall('payplus', CURRENT_DEBUG_ACTION, [
            'error'=>$paymentPage->Response,
            'payload'=>$paymentPage->GetPayload()        
        ], 'Req user ID...');
        return [
            'status' => 'success',
            'transid' => $paymentPage->Response->result->transaction_uid
        ];
    }

    logModuleCall('payplus', CURRENT_DEBUG_ACTION, [
        'error'=>$paymentPage->GetErrors(),
        'payload'=>$paymentPage->GetPayload()        
    ], 'Req user ID...');

    return [
        'status' => 'declined'
    ];
}

function payplus_remoteinput($params)
{
    PayplusBase::$apiKey = $params['apiKey'];
    PayplusBase::$secretKey = $params['secretKey'];
    PayplusBase::$devMode = ($params['devMode'] == 'on');
    $clientDetails = $params['clientdetails'];
    $paymentPage = new PaymentPage;
    $currencyCode = 'ILS';
    if ($params['clientdetails']['model'] && method_exists($params['clientdetails']['model'],'getCurrencyCodeAttribute')) {
        $currencyCode = $params['clientdetails']['model']->getCurrencyCodeAttribute();
    }
    $paymentPage->Init([
        'payment_page_uid' =>  $params['paymentPageUID'],
        'currency_code' => $currencyCode,
        'amount' => 0
    ]);
    $customer = [
        'customer_name' => ($params['clientdetails']['companyname']) ? $params['clientdetails']['companyname']:$params['clientdetails']['fullname'],
        'email' => $params['clientdetails']['email'],
        'vat_number' => $params['clientdetails']['tax_id'],
        'phone' => $params['clientdetails']['phonenumber'],
        'country_iso' => $params['clientdetails']['countrycode'],
        'city' => $params['clientdetails']['city'],
        'address' => $params['clientdetails']['address1'],
    ];
    if ($params['vat_id_field_name']) {
        $customer['vat_number'] = $params['clientdetails'][$params['vat_id_field_name']];
    }
    $paymentPage->SetCustomer($customer);
    $userID = openssl_encrypt($clientDetails['userid'], ENCRYPTION_ALGORITHM, PASSPHRASE);
    $paymentPage->more_info = base64_encode($userID);
    $get = [];
    $paymentPage->refURL_success = $params['systemurl'] . 'modules/gateways/payplus/return.php';
    if ($params['invoiceid']) {
        $get['invoiceid'] = $params['invoiceid'];
    }
    if (ADMINAREA === true) {
        $get['adminarea'] = 1;
    }
    if (!empty($get)) {
        $paymentPage->refURL_success .= '?' . http_build_query($get);
    }
    $paymentPage->charge_method = ChargeMethods::TOKEN;
    $paymentPage->create_token = true;

    if ($paymentPage->Go()->IsSuccess()) {
        return '
        <script>
            var noAutoSubmit = true;
            jQuery(()=>{
                jQuery("[name=ccframe]").attr("src","' . $paymentPage->payment_page_link . '")
            })
        </script>';
    } else {
        return $paymentPage->GetErrors();
    }
}

function payplus_refund($params)
{
    PayplusBase::$apiKey = $params['apiKey'];
    PayplusBase::$secretKey = $params['secretKey'];
    PayplusBase::$devMode = ($params['devMode'] == 'on');

    $transactionIdToRefund = $params['transid'];
    $refundAmount = $params['amount'];
    $refund = new RefundByTransactionUID;
    $refund->Init([
        'transaction_uid' => $transactionIdToRefund,
        'amount' => $refundAmount,

    ]);
    $result  = [];
    if ($refund->Go()->IsSuccess()) {
        $result['status'] = 'success';
        $result['rawdata'] = '';
        $result['transid'] = $refund->details->uid;
    } else {
        $result['status'] = 'error';
        $result['rawdata'] = '';
        $result['transid'] = 0;
    }
    return $result;
}

// function payplus_cancelSubscription($params){}