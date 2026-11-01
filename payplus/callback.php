<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../../../includes/clientfunctions.php';
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/PayplusInstance.php';

if (empty($gatewayModuleName)) {
    $gatewayModuleName = 'payplus';
}

PayplusInstance::$GatewayName = $gatewayModuleName;
$gatewayParams = getGatewayVariables($gatewayModuleName);
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$input = PayplusInstance::parseGatewayInput();
logTransaction($gatewayModuleName, $input, 'Callback');

$result = PayplusInstance::processNotification($gatewayModuleName, $gatewayParams, $input);

header('Content-Type: text/plain; charset=utf-8');
echo !empty($result['success']) ? 'OK' : 'FAIL';
