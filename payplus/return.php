<?php
define('CURRENT_DEBUG_ACTION','return.php');
/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
require_once "init.php";
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/clientfunctions.php';
require_once __DIR__ . '/../../../includes/adminfunctions.php';
require_once "vendor/autoload.php";
logTransaction('payplusnew', $_REQUEST, CURRENT_DEBUG_ACTION);
if (!$_REQUEST['g'] || !array_key_exists($_REQUEST['g'],$gatewayHashes)) {
    $gatewayModuleName = 'payplus';
} else {
    $gatewayModuleName = $gatewayHashes[$_REQUEST['g']];
}


$loggedUserID = null;
try {
    $userDetails = getclientsdetails();
} catch (\Exception $th) {
    $userDetails = null;
}
if ($userDetails && $userDetails['userid']) {
    $loggedUserID = $userDetails['userid'];
}
define('IS_ADMIN_AREA', $_REQUEST['adminarea'] === '1');
$authenticatedAdmin = \WHMCS\User\Admin::getAuthenticatedUser();

$cardName = null;
if ($_REQUEST['issuer_id'] && array_key_exists($_REQUEST['issuer_id'],$cardNames)) {
    $cardName = $cardNames[$_REQUEST['issuer_id']];
}


$gatewayParams = getGatewayVariables($gatewayModuleName);
$debugData = $_REQUEST;
$debugData['IS_ADMIN_AREA'] = IS_ADMIN_AREA;
// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$requestedUserID = null;
if ($_REQUEST['more_info']) {
    $requestedUserID = openssl_decrypt(base64_decode($_REQUEST['more_info']), ENCRYPTION_ALGORITHM, PASSPHRASE);
}
$fourDigits = $_REQUEST['four_digits'];

$expiryDate = $_REQUEST['expiry_month'] . $_REQUEST['expiry_year'];

$invoiceID = $_REQUEST['invoiceid'];

$debugData['extra']['invoiceID'] = $invoiceID;
if (!$_REQUEST['token_uid'] || !$_REQUEST['terminal_uid']) {
    $debugData['extra']['requestedUserID'] = $requestedUserID;
    logModuleCall($gatewayModuleName, CURRENT_DEBUG_ACTION, $debugData,'Missing either token_uid or terminal_uid');
    die;
}
$tokenData = $_REQUEST['token_uid'];
if (
    $requestedUserID !== null
    && ($requestedUserID == $loggedUserID
        || $authenticatedAdmin !== null)
) {
    $exception = null;

    $x = new ReflectionFunction('createCardPayMethod');

    try {
        $createCardStatus = createCardPayMethod(
            $requestedUserID,
            $gatewayModuleName,
            $fourDigits,
            $expiryDate,
            $cardName,
            null,
            null,
            $tokenData
        );

    } catch (\Exception $th) {

        PayplusGateway\ddv($createCardStatus);
    }
    if ($createCardStatus) {

        $debugData['extra']['requestedUserID'] = $requestedUserID;
        $debugData['extra']['loggedUserID'] = $loggedUserID;
        $debugData['extra']['authenticatedAdmin'] = $authenticatedAdmin;
        $debugData['extra']['fourDigits'] = $fourDigits;
        $debugData['extra']['expiryDate'] = $expiryDate;
        $debugData['extra']['tokenData'] = $tokenData;
        $debugData['extra']['createCardStatus'] = $createCardStatus;
        $debugData['extra']['exception'] = $exception;
        logModuleCall($gatewayModuleName, CURRENT_DEBUG_ACTION, $debugData, "Wouldn't create cc token");
    }
} else {

    $debugData['extra']['requestedUserID'] = $requestedUserID;
    $debugData['extra']['loggedUserID'] = $loggedUserID;
    $debugData['extra']['authenticatedAdmin'] = $authenticatedAdmin;
    logModuleCall($gatewayModuleName, CURRENT_DEBUG_ACTION, $debugData, 'Req user ID...');
}


if (IS_ADMIN_AREA === true) {

    $adminPrefix = ($customadminpath) ? $customadminpath:'/admin';
    redirSystemURL(['userid' => $requestedUserID], $adminPrefix."/clientssummary.php");
}
if ($invoiceID) {
   $post  =$_REQUEST;

 //payment invoice

   if(!empty($post['status'])&& !empty($post['status_code']) && !empty( $post['type'])
        && $post['status']=="approved" && $post['status_code']=="000" &&  $post['type']=="Charge"){
        $date =new DateTime();
        $date = $date->format('Y-m-d');
        $command = 'UpdateInvoice';
        $postData = array(
            'invoiceid' => $invoiceID,
            'status' => 'Paid',
            'datepaid' =>$date);
        $orderId =PayplusInstance::getOrderId($invoiceID);
        $updateInvoice = localAPI($command, $postData);
        PayplusInstance::updateTblHosting($invoiceID,'Active');
        $command = 'AcceptOrder';
        $postData = array(
               'orderid' => $orderId
        );
        $results = localAPI($command, $postData);
    }
    redirSystemURL('', "/invoice/" . $invoiceID . "/pay");

}
redirSystemURL('', "/account/paymentmethods");



