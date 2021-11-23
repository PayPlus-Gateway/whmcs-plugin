<?php

require_once "debug.php";
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use PayplusGateway\PayplusApi\Ipn;
use PayplusGateway\PayplusApi\PayplusBase;
require_once "vendor/autoload.php";
$gatewayModuleName = 'payplus';

$gatewayParams = getGatewayVariables($gatewayModuleName);
// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}
$json = file_get_contents('php://input');
$data = json_decode($json);
PayplusBase::$apiKey = $gatewayParams['apiKey'];
PayplusBase::$secretKey = $gatewayParams['secretKey'];
PayplusBase::$devMode = ($gatewayParams['devMode'] == 'on');

$validation = new Ipn;
$validation->Init([
    'transaction_uid'=>$data->transaction->uid
]);
if ($validation->Go()->IsSuccess()) {
    $data = $validation->details;



    die("stop");

    $invoiceId = checkCbInvoiceID( $data->more_info, $gatewayParams['name']);
    $transactionId =  $data->transaction_uid;
    $paymentAmount = $data->amount;
    checkCbTransID($transactionId);
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentAmount,
        $gatewayModuleName
    );   
    exit('success');
}