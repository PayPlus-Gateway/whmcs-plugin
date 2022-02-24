<?php
require_once "init.php";
require_once "PayplusInstance.php";
require_once __DIR__ . '/../../../includes/clientfunctions.php';
define('CURRENT_DEBUG_ACTION','main file');
require_once "./vendor/autoload.php";
require_once "payments_hook.php";
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}