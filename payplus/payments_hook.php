<?php

use WHMCS\Database\Capsule;

function add_payments_payplus_gateway($vars){
	if($vars['filename'] != 'invoices') {
		return;
	}

	$payments = [];
    $max_payments = 36;
	if(empty($max_payments)){
        return;
	}
	foreach(range(1, $max_payments) as $p){
		$payments[] = '<option value="'.$p.'">'.$p.'</option>';
	}
	$payments = join("", $payments);
	$payments_html = '<div class="container">';
	$payments_html .= '<button type="button" class="btn btn-info" data-toggle="collapse" data-target="#smartpayplus-payments">Payments <span class="caret"></span></button>';
	$payments_html .= '<div id="smartpayplus-payments" class="collapse">';
	$payments_html .= '<div class="clearfix">&nbsp;</div>';
	$payments_html .= '<label for="payments">Payments:</label>';
	$payments_html .= '<select class="form-control select-inline" name="payments">'.$payments.'</select>';

	$payments_html .= '</div></div>';
	$payments_html .= '<div class="clearfix">&nbsp;</div>';

	$server =  "http".($_SERVER['HTTPS'] ?  's://' : '://').$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
	$JS = <<<JS

function show_payments_options() {
    $('#modalAjaxBody .form-group').each(function() {
        if ($(this).is(':visible')){
			if( !$( "button[data-target='#smartpayplus-payments']" ).is(":visible")){
				var capture_btn = $('#modalAjaxBody > form');
					capture_btn.prepend('<div class="container"><button type="button" class="btn btn-info" data-toggle="collapse" data-target="#smartpayplus-payments">Payments <span class="caret"></span></button><div id="smartpayplus-payments" class="collapse"><div class="clearfix">&nbsp;</div><label for="payments">Payments:</label><select class="form-control select-inline" name="payments"><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option><option value="13">13</option><option value="14">14</option><option value="15">15</option><option value="16">16</option><option value="17">17</option><option value="18">18</option><option value="19">19</option><option value="20">20</option><option value="21">21</option><option value="22">22</option><option value="23">23</option><option value="24">24</option><option value="25">25</option><option value="26">26</option><option value="27">27</option><option value="28">28</option><option value="29">29</option><option value="30">30</option><option value="31">31</option><option value="32">32</option><option value="33">33</option><option value="34">34</option><option value="35">35</option><option value="36">36</option></select><div class="clearfix">&nbsp;</div></div></div><div class="clearfix">&nbsp;</div>');
			}
		}
    });
}


$('#btnShowAttemptCaptureDialog').click(function() {
   window.setInterval(show_payments_options, 100);
});


function insert_payments_feature(){
	//Remove auto check of payment confirmation email
	$('[name="sendconfirmation"]').prop('checked', false);
	var capture_btn = $('input[onclick="attemptpayment()"]');
	capture_btn.before('{$payments_html}');
	capture_btn.attr('onclick', 'attemptpaymentWithPayments()');
}

$('[name="paymentmethod"]').on('change', function(){
	if($(this).val() === 'payplus'){
		insert_payments_feature();
	}
});
if($('[name="paymentmethod"]').val() === 'payplus'){
	function attemptpaymentWithPayments() {
		if (confirm("Are you sure you want to attempt payment for this invoice?")) {
			var token = $('#addPayment input[name="token"]').val();
			var url = "{$server}?action=edit&id={$_GET['id']}&token=" + token + '&sub=attemptpayment';
			if($('select[name="payments"]').is(':visible')){
				var payments = $('select[name="payments"]').val() !== undefined ? $('select[name="payments"]').val() : 1;
				var credit =  ($('input[name="credit"]').prop('checked') && $('input[name="credit"]').val() !== undefined) ? $('input[name="credit"]').val() : 0;
				var paymentmethod_action = $('select[name="paymentmethod_action"]').val()
				url += '&payments='+ payments + '&credit=' + credit + '&paymentmethod_action=' + paymentmethod_action;
			}
			window.location=url;
	}}
	insert_payments_feature()
}

JS;

$footer_return = '';
	$footer_return = '<script type="text/javascript">'.$JS.'</script>';
	return $footer_return;
}

$enabled = Capsule::table('tblpaymentgateways')->where('gateway', 'payplus')->where('setting', 'visible')->pluck('value');
if(is_array($enabled)){
	$enabled = $enabled[0];
}
$gatewayModuleName = 'payplus';
$valid = true;
try {
	$gatewayParams = getGatewayVariables('payplus');
} catch (\Throwable $th) {
	//throw $th;
}

if($gatewayParams && $gatewayParams['enable_payments'] === 'on'){
	add_hook('AdminAreaFooterOutput', 0, 'add_payments_payplus_gateway');
	add_hook('AdminAreaClientSummaryPage', 10, 'smartpayplus_hook_AdminAreaClientSummaryPage');
}