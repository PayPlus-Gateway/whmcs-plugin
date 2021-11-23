<?php

require_once "init.php";
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/clientfunctions.php';
require_once __DIR__ . '/../../../includes/adminfunctions.php';

require_once "vendor/autoload.php";

$loggedUserID = null;
$userDetails = getclientsdetails();
if (isset($userDetails['model']) && count($userDetails['model']->getUserIds()) > 0) {
    $loggedUserID = $userDetails['model']->getUserIds()[0];
}
define('IS_ADMIN_AREA', $_REQUEST['adminarea'] === '1');
$authenticatedAdmin = \WHMCS\User\Admin::getAuthenticatedUser();

$gatewayModuleName = 'payplus';
define('CURRENT_DEBUG_ACTION','return.php');
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

$tokenData = $_REQUEST['token_uid'] . TOKEN_TERMINAL_SEPARATOR . $_REQUEST['terminal_uid'];
if (
    $requestedUserID !== null
    && ($requestedUserID == $loggedUserID
        || $authenticatedAdmin !== null)
) {
    $exception = null;
    try {
        $createCardStatus = createCardPayMethod(
            $requestedUserID,
            $gatewayModuleName,
            $fourDigits,
            $expiryDate,
            null,
            null,
            null,
            $tokenData
        );
    } catch (\Exception $th) {
        PayplusGateway\ddv($createCardStatus);
    }

    if (!$createCardStatus) {
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
    redirSystemURL(['userid' => $requestedUserID], "/admin/clientssummary.php");
}
if ($invoiceID) {
    redirSystemURL('', "/invoice/" . $invoiceID . "/pay");
}
redirSystemURL('', "/account/paymentmethods");
