<?php

require_once "debug.php";

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