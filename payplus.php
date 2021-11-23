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

use PayplusGateway\PayplusApi\ChargeMethods;
use PayplusGateway\PayplusApi\PaymentPage;
use PayplusGateway\PayplusApi\PayplusBase;
use PayplusGateway\PayplusApi\RefundByTransactionUID;
use PayplusGateway\PayplusApi\TokenPay;
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
            $updateToken = new Update;
            $tokenData = explode(TOKEN_TERMINAL_SEPARATOR, $params['gatewayid']);
            $tokenUID = $tokenData[0];
            $payment = $params['payMethod']->payment;
            $currencyExpiry = $payment->getExpiryDate()->format("my");
            $terminalUID = $tokenData[1];
            if ($currencyExpiry == $params['cardexp']) {
                return [
                    'status' => 'success'
                ];
            }
            $updateToken->Init([
                'uid' => $tokenUID,
                'terminal_uid' => $terminalUID,
                'credit_card_number' => $params['cardlastfour'],
                'card_date_mmyy' => $params['cardexp'],
            ]);
            $exp = \WHMCS\Carbon::createFromDate($params['cardExpiryYear'], $params['cardExpiryMonth'], 1);
            if ($updateToken->Go()->IsSuccess()) {
                $payment->setExpiryDate($exp);
                $payment->save();
                return [
                    'status' => 'success'
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

    foreach ($params['cart']->getItems() as $item) {
        $paymentPage->AddItem([
            'price' => $item->getAmount()->getValue(),
            'name' => $item->getName(),
            'quantity' => $item->getQuantity()
        ]);
    }
    $customer = [
        'customer_name' => $params['clientdetails']['fullname'],
        'email' => $params['clientdetails']['email'],
    ];
    $paymentPage->SetCustomer($customer);
    $paymentPage->charge_method = ChargeMethods::CHARGE;
    if ($paymentPage->Go()->IsSuccess()) {
        return [
            'status' => 'success',
            'transid' => $paymentPage->Response->result->transaction_uid
        ];
    }

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
        'customer_name' => $params['clientdetails']['fullname'],
        'email' => $params['clientdetails']['email'],
    ];
    $paymentPage->SetCustomer($customer);
    $userID = openssl_encrypt($clientDetails['id'], ENCRYPTION_ALGORITHM, PASSPHRASE);
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

    $paymentPage->hide_identification_id = true;

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