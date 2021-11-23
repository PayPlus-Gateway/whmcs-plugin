<?php

namespace PayplusGateway;

function d(...$args) {
	debug_raw('print_r',false,$args);
}

/**
 * Print debug variables, die
 */
function dd(...$args) {
	debug_raw('print_r',true,$args);
}

/**
 * var_dump debug variables, NOT die
 */
function dv(...$args) {
	debug_raw('var_dump',false,$args);
}

/**
 * var_dump debug variables, die
 */
function ddv(...$args) {
	debug_raw('var_dump',true,$args);
}

function debug_raw($func,$die,$args) {
	global $set;
    echo '<pre style="    
        direction: ltr;
        display: block;
        border: 3px solid #afafaf;
        background: #002552;
        border-radius: 4px;
        color: #ffffff;
        font-size: 17px;
        width: fit-content;
        margin: 0;
    ">';
    
    foreach($args as $h=> $arg) {
        $typeText = gettype($arg);
        if (is_array($arg)) {
            $typeText .= '('.count($arg).')';
        } elseif (is_object($arg)) {
            $typeText .= ': ' . get_class($arg);
        } else {
            $typeText .= '('.strlen($arg).')';
        } 
        echo '<div style="text-align:center;">'.$typeText.'</div><div style="
            padding: 10px;
            '.($h  == count($args) - 1 ? '':'border-bottom:2px solid #eee;').'
        ">';
        $func($arg);
        echo '</div>';
    }
    if (empty($args)) {
        echo '<div style="text-align:center;">-- No Value --</div>';
        echo '<div style="padding: 10px;text-align:center;color: #a5a5a5 !important;font-size: 14px;font-style: italic;">';
        echo 'No Value';
        echo '</div>';			
    }
    echo '</pre>';
    if ($die) {
        die;
    }
	
}