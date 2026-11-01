<?php
define('CURRENT_DEBUG_ACTION', 'return.php');
require_once "init.php";
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../../../includes/clientfunctions.php';
require_once __DIR__ . '/../../../includes/adminfunctions.php';
require_once "vendor/autoload.php";
require_once "PayplusInstance.php";

if (!$_REQUEST['g'] || !array_key_exists($_REQUEST['g'], $gatewayHashes)) {
    $gatewayModuleName = 'payplus';
} else {
    $gatewayModuleName = $gatewayHashes[$_REQUEST['g']];
}

PayplusInstance::$GatewayName = $gatewayModuleName;
$gatewayParams = getGatewayVariables($gatewayModuleName);
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$input = PayplusInstance::parseGatewayInput();
logTransaction($gatewayModuleName, $input, CURRENT_DEBUG_ACTION);

$result = PayplusInstance::processNotification($gatewayModuleName, $gatewayParams, $input);
$invoiceID = $result['invoiceid'] ?? ($input['invoiceid'] ?? null);
$requestedUserID = $result['userid'] ?? null;

define('IS_ADMIN_AREA', ($_REQUEST['adminarea'] ?? '') === '1');

if (IS_ADMIN_AREA === true) {
    $adminPrefix = ($customadminpath) ? $customadminpath : '/admin';
    redirSystemURL(['userid' => $requestedUserID], $adminPrefix . "/clientssummary.php");
}

if ($invoiceID) {
    redirSystemURL('', "viewinvoice.php?id=" . $invoiceID);
}
redirSystemURL('', "clientarea.php?action=creditcard");
