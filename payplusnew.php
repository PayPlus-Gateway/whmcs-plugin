<?php
require_once "payplus/init.php";
require_once "payplus/PayplusInstance.php";
require_once __DIR__ . '/../../includes/clientfunctions.php';
define('CURRENT_DEBUG_ACTION','main file');
if (file_exists(__DIR__.'/payplus/autoload.php')) {
    require_once __DIR__.'/payplus/autoload.php';
} else {
    require_once "payplus/vendor/autoload.php";
}

require_once "payplus/payments_hook.php";
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
PayplusInstance::$DisplayName = "New PayPlus Gateway";
PayplusInstance::$GatewayName = "payplusnew";
PayplusInstance::$GatewayNameMeta = "New PayPlus";

function payplusnew_MetaData()
{
    return PayplusInstance::MetaData();
}

function payplusnew_config()
{
    return PayplusInstance::Config();
}
function payplusnew_nolocalcc() {}

function payplusnew_storeremote($params)
{
    return PayplusInstance::RemoteStore($params);
}
function payplusnew_capture($params)
{
    return PayplusInstance::Capture($params);
}

function payplusnew_remoteinput($params)
{
    return PayplusInstance::RemoteInput($params);
}

function payplusnew_refund($params)
{
    return PayplusInstance::Refund($params);
}