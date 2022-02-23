<?php
require_once "payplus_gateway_config.php";
define("PAYLUS_GATEWAY_MODULE_VERSION","1.0.4");

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


require_once PAYPLUS_GATEWAY_NAME_SIMPLE."/init.php";
require_once PAYPLUS_GATEWAY_NAME_SIMPLE."/payments_hook.php";
require_once __DIR__ . '/../../includes/clientfunctions.php';
define('CURRENT_DEBUG_ACTION','main file');
use PayplusGateway\PayplusApi\ChargeMethods;
use PayplusGateway\PayplusApi\PaymentPage;
use PayplusGateway\PayplusApi\PayplusBase;
use PayplusGateway\PayplusApi\RefundByTransactionUID;
use PayplusGateway\PayplusApi\TokenPay;
use PayplusGateway\PayplusApi\Tokens\Remove;
use PayplusGateway\PayplusApi\Tokens\Update;

require_once PAYPLUS_GATEWAY_NAME_SIMPLE."/vendor/autoload.php";
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
    return [
        'version' =>[
            'FriendlyName' => 'Module version',
            'Description' => PAYLUS_GATEWAY_MODULE_VERSION,
            'Value' => PAYLUS_GATEWAY_MODULE_VERSION,
        ],
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'PayPlus Gateway',
        ),
        'devMode' => array(
            'FriendlyName' => 'Dev Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable dev mode',
        ),
        'enable_payments' => array(
            'FriendlyName' => 'Enable payments',
            'Type' => 'yesno',
            'Description' => 'Tick to enable payments',
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
        ),
        'devurl' => array(
            'FriendlyName' => 'Dev API address',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'API address (only applicable for dev mode. !!Leave blank!!)',
        ),
        'move_token' => array(
            'FriendlyName' => 'Move token',
            'Type' => 'yesno',
            'Description' => 'Tick to send the move_token parameter with transactions',
        )
    ];
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
    global $_LANG;
    $translations = getTranslation(substr($_LANG['locale'],0,2));
    PayplusBase::$apiKey = $params['apiKey'];
    PayplusBase::$secretKey = $params['secretKey'];
    PayplusBase::$devMode = ($params['devMode'] == 'on');
    if ($params['devMode'] === 'on' && $params['devurl']) {
        PayplusBase::$DEV_ADDRESS = $params['devurl'];
    }
    $params['gatewayid'] = explode(TOKEN_TERMINAL_SEPARATOR, $params['gatewayid']);
    $params['gatewayid'] = $params['gatewayid'][0];
    $paymentPage = new TokenPay;
    $paymentPage->Init([
        'payment_page_uid' =>  $params['paymentPageUID'],
        'currency_code' => $params['currency'],
        'amount' => $params['amount'],
        'token' => $params['gatewayid']
    ]);

    $numPayments = $_REQUEST['payments'] ?? null;
    if (ADMINAREA && $numPayments && $numPayments > 1) {
        $paymentPage->payments = $numPayments;
    }
    $total = 0;
    $taxCalculator = $params['cart']->getTaxCalculator($params['cart']->client);
    foreach($params['cart']->getInvoiceModel()->lineItems as $item) {
        $itemLine = [
            'price' => $item->amount,
            'name' => $item->description,
            'quantity' => 1
        ];
        
        if (
            WHMCS\Config\Setting::getValue("TaxEnabled") 
            && $item->taxed
            && !$params['cart']->client->taxExempt
            ) {
            $itemLine['price'] = $taxCalculator->setTaxBase($item->amount)->getTotalAfterTaxes();
        }

        $paymentPage->AddItem($itemLine);
        $total+=$itemLine['price'];
    }

    $credit = (float)$params['cart']->getInvoiceModel()->getAttribute('credit');
    if ($credit > 0) {
        $itemLine = [
            'price' => $credit * -1,
            'name' => 'Credit',
            'quantity' => 1
        ];
        $paymentPage->AddItem($itemLine);
        $total-=$credit;
    }
    
    $paramsAmount =  $params['amount'] * 100;
    $totalC =   $total * 100;
    $diff = $paramsAmount - $totalC;
    if (abs($diff) == 1) {
        $paymentPage->AddItem([
            'price'=>$diff / 100,
            'quantity'=>1,
            'name'=> $translations['rounding-difference']
        ]);
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
    if ($params['move_token'] === 'on') {
        $paymentPage->move_token = true;
    }
    $paymentPage->more_info = $params['invoiceid'];
    $paymentPage->charge_method = ChargeMethods::CHARGE;
    $paymentPage->Go();
    if ($paymentPage->IsSuccess()) {
        return [
            'status' => 'success',
            'transid' => $paymentPage->Response->result->number
        ];
    }
    logModuleCall('payplus', CURRENT_DEBUG_ACTION, [
        'error'=>$paymentPage->GetErrors(),
        'payload'=>$paymentPage->GetPayload()        
    ], 'Req user ID...');
    
    return [
        'status' => 'declined',
        'rawdata'=>$paymentPage->GetErrors(),
        'declinereason'=>$paymentPage->GetErrors()
    ];
}

function getTranslation($lang) {
    $translations = [];
    $translations['coupon-discount'] = 'Coupon discount';
    $translations['rounding-difference'] = 'Rounding difference';
    if ($lang == 'he') {
        $translations['coupon-discount'] = 'הנחת קופון';
        $translations['rounding-difference'] = 'הפרש עיגול';
    }
    return $translations;
    
}

function payplus_remoteinput($params)
{
    PayplusBase::$apiKey = $params['apiKey'];
    PayplusBase::$secretKey = $params['secretKey'];
    PayplusBase::$devMode = ($params['devMode'] == 'on');
    if ($params['devMode'] === 'on' && $params['devurl']) {
        PayplusBase::$DEV_ADDRESS = $params['devurl'];
    }
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
    $paymentPage->refURL_success = $params['systemurl'] . 'modules/gateways/'.PAYPLUS_GATEWAY_NAME_SIMPLE.'/return.php';
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