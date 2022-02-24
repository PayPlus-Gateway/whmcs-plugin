<?php

require_once "debug.php";
define("PAYLUS_GATEWAY_MODULE_VERSION","1.0.4");
define('PASSPHRASE','OanN&&TAp4U9vt@1*0c%OvjyI');
define('ENCRYPTION_ALGORITHM','AES-256-CBC');
define('REMOTE_STORE_ACTION_DELETE','delete');
define('REMOTE_STORE_ACTION_UPDATE','update');
define('REMOTE_STORE_ACTION_CREATE','create');
define('TOKEN_TERMINAL_SEPARATOR','---');

$cardNames = [
    1 =>'mastercard',
    2 =>'visa',
    3 =>'diners',
    4 =>'amex',
    5 =>'isracard',
    6 =>'jbc',
    7 =>'discover',
    8 =>'maestro'
];

$gatewayHashes = [
    md5('payplus')=> 'payplus',
    md5('payplusnew')=> 'payplusnew'
];

add_hook('ClientAreaFooterOutput', 1, function($vars) {
    $captchaData = WHMCS\Session::getAndDelete("credit-card-error");
    if ($captchaData) {
        $html = "<script>";
        $html .= "jQuery('.gateway-errors').html(`$captchaData`);";
        $html .= "jQuery('.gateway-errors').removeClass(`w-hidden`);";
        $html .= "</script>";
        return $html;
    }
});