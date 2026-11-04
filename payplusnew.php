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

function payplusnew_apply_identity()
{
    PayplusInstance::$DisplayName = "PayPlus Gateway 2";
    PayplusInstance::$GatewayName = "payplusnew";
    PayplusInstance::$GatewayNameMeta = "PayPlus Gateway 2";
}

payplusnew_apply_identity();

function payplusnew_MetaData()
{
    payplusnew_apply_identity();
    return PayplusInstance::MetaData();
}

function payplusnew_config()
{
    payplusnew_apply_identity();
    return PayplusInstance::Config();
}
function payplusnew_nolocalcc() {}

function payplusnew_storeremote($params)
{
    payplusnew_apply_identity();
    return PayplusInstance::RemoteStore($params);
}
function payplusnew_capture($params)
{
    payplusnew_apply_identity();
    return PayplusInstance::Capture($params);
}

function payplusnew_remoteinput($params)
{
    payplusnew_apply_identity();
    return PayplusInstance::RemoteInput($params);
}

function payplusnew_refund($params)
{
    payplusnew_apply_identity();
    return PayplusInstance::Refund($params);
}
