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

function payplus_apply_identity()
{
    PayplusInstance::$DisplayName = "PayPlus Gateway";
    PayplusInstance::$GatewayName = "payplus";
    PayplusInstance::$GatewayNameMeta = "PayPlus Gateway";
}

payplus_apply_identity();

function payplus_MetaData()
{
    payplus_apply_identity();
    return PayplusInstance::MetaData();
}

function payplus_config()
{
    payplus_apply_identity();
    return PayplusInstance::Config();
}
function payplus_nolocalcc() {}

function payplus_storeremote($params)
{
    payplus_apply_identity();
    return PayplusInstance::RemoteStore($params);
}
function payplus_capture($params)
{
    payplus_apply_identity();
    return PayplusInstance::Capture($params);
}

function payplus_remoteinput($params)
{
    payplus_apply_identity();
    return PayplusInstance::RemoteInput($params);
}

function payplus_refund($params)
{
    payplus_apply_identity();
    return PayplusInstance::Refund($params);
}
