<?php

use PayplusGateway\PayplusApi\ChargeMethods;
use PayplusGateway\PayplusApi\Ipn;
use PayplusGateway\PayplusApi\PaymentPage;
use PayplusGateway\PayplusApi\PayplusBase;
use PayplusGateway\PayplusApi\RefundByTransactionUID;
use PayplusGateway\PayplusApi\TokenPay;
use PayplusGateway\PayplusApi\Tokens\Remove;
use PayplusGateway\PayplusApi\Tokens\Update;
use WHMCS\Database\Capsule;

class PayplusInstance
{
    public static $DisplayName;
    public static $GatewayName;
    public static $GatewayNameMeta;
    public static function MetaData()
    {
        return array(
            'DisplayName' => self::$GatewayNameMeta,
            'APIVersion' => '1.1'
        );
    }

    public static function Config()
    {
        return [
            'version' => [
                'FriendlyName' => 'Module version',
                'Description' => PAYLUS_GATEWAY_MODULE_VERSION,
                'Value' => PAYLUS_GATEWAY_MODULE_VERSION,
            ],
            'FriendlyName' => array(
                'Type' => 'System',
                'Value' => self::$DisplayName,
            ),
            'devMode' => array(
                'FriendlyName' => 'Dev Mode',
                'Type' => 'yesno',
                'Description' => 'Tick to enable dev mode',
            ),
            'enable_payments' => array(
                'FriendlyName' => 'Enable payments',
                'Type' => 'yesno',
                'Description' => 'Tick to show an installment selector. Does not auto-submit the payment form.',
            ),
            'number_payment' => array(
                'FriendlyName' => 'Number of payments',
                'Type' => 'text',
                'Default' => '3',
                'Size' => '50',
                'Description' => 'Number of payments',
            ),
            'apiKey' => array(
                'FriendlyName' => 'API Key',
                'Type' => 'text',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Enter API Key here',
            ),
            'secretKey' => array(
                'FriendlyName' => 'Secret Key',
                'Type' => 'text',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Enter secret key here',
            ),
            'paymentPageUID' => array(
                'FriendlyName' => 'Payment page UID',
                'Type' => 'text',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Enter payment page UID here',
            ),
            'terminalUID' => array(
                'FriendlyName' => 'Terminal UID',
                'Type' => 'text',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Enter terminal UID here',
            ),
            'vat_id_field_name' => array(
                'FriendlyName' => 'Vat ID field name',
                'Type' => 'text',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Enter custom field name for vat ID if applicable',
            ),
            'devurl' => array(
                'FriendlyName' => 'Dev API address',
                'Type' => 'text',
                'Size' => '50',
                'Default' => '',
                'Description' => 'API address (only applicable for dev mode. !!Leave blank!!)',
            ),
            'move_token' => array(
                'FriendlyName' => 'Move token',
                'Type' => 'yesno',
                'Description' => 'Tick to send the move_token parameter with transactions',
            ),
            'charge_token' => array(
                'FriendlyName' => 'Charge Token',
                'Type' => 'yesno',
                'Description' => 'Kept for compatibility. Invoice payments are always charged. Token-only is used when adding a card with no invoice.',
            ),
            'skip-checkout' => array(
                'FriendlyName' => 'Enable auto submit payment',
                'Type' => 'yesno',
                'Description' => 'Auto-submit once when a saved card is selected. Will not retry after a decline.',
            ),
            'waiting-message' => array(
                'FriendlyName' => 'Auto submit payment waiting message',
                'Type' => 'text',
                'Default' => 'Please wait, your transaction is being processed...',
                'Description' => '',
            )
        ];
    }

    public static function initApi(array $params)
    {
        PayplusBase::$apiKey = $params['apiKey'];
        PayplusBase::$secretKey = $params['secretKey'];
        PayplusBase::$devMode = ($params['devMode'] == 'on');
        if ($params['devMode'] === 'on' && !empty($params['devurl'])) {
            PayplusBase::$DEV_ADDRESS = $params['devurl'];
        }
    }

    public static function getPassphrase()
    {
        if (!empty($GLOBALS['cc_encryption_hash'])) {
            return $GLOBALS['cc_encryption_hash'];
        }
        return PASSPHRASE;
    }

    public static function encodeMoreInfo($userId, $invoiceId = null)
    {
        $payload = json_encode([
            'u' => $userId !== null ? (string)$userId : '',
            'i' => $invoiceId !== null && $invoiceId !== '' ? (string)$invoiceId : '',
        ]);
        return base64_encode(openssl_encrypt($payload, ENCRYPTION_ALGORITHM, self::getPassphrase()));
    }

    public static function decodeMoreInfo($moreInfo)
    {
        $result = ['userid' => null, 'invoiceid' => null];
        if ($moreInfo === null || $moreInfo === '') {
            return $result;
        }
        $moreInfo = (string)$moreInfo;
        if (ctype_digit($moreInfo)) {
            $result['invoiceid'] = (int)$moreInfo;
            return $result;
        }

        $decoded = base64_decode($moreInfo, true);
        if ($decoded === false) {
            return $result;
        }

        $plain = openssl_decrypt($decoded, ENCRYPTION_ALGORITHM, self::getPassphrase());
        if ($plain === false) {
            $plain = openssl_decrypt($decoded, ENCRYPTION_ALGORITHM, PASSPHRASE);
        }
        if ($plain === false || $plain === '') {
            return $result;
        }

        $json = json_decode($plain, true);
        if (is_array($json)) {
            if (!empty($json['u']) && ctype_digit((string)$json['u'])) {
                $result['userid'] = (int)$json['u'];
            }
            if (!empty($json['i']) && ctype_digit((string)$json['i'])) {
                $result['invoiceid'] = (int)$json['i'];
            }
            return $result;
        }

        if (ctype_digit($plain)) {
            $result['userid'] = (int)$plain;
        }
        return $result;
    }

    public static function parseGatewayInput()
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json) && $json) {
            if (isset($json['transaction']) && is_array($json['transaction'])) {
                $json = array_merge($json, $json['transaction']);
                if (empty($json['transaction_uid']) && !empty($json['uid'])) {
                    $json['transaction_uid'] = $json['uid'];
                }
            }
            return array_merge($_REQUEST, $json);
        }
        return $_REQUEST;
    }

    public static function verifyTransaction(array $params, $transactionUid = null, $paymentRequestUid = null)
    {
        if (empty($transactionUid) && empty($paymentRequestUid)) {
            return null;
        }
        self::initApi($params);
        $ipn = new Ipn;
        if (!$ipn->Init([
            'transaction_uid' => $transactionUid,
            'payment_request_uid' => $paymentRequestUid,
        ])) {
            return null;
        }
        $ipn->Go();
        if (!$ipn->IsSuccess()) {
            return null;
        }
        return $ipn;
    }

    public static function isApprovedTransaction($transaction)
    {
        if (!$transaction) {
            return false;
        }
        $statusCode = isset($transaction->status_code) ? (string)$transaction->status_code : '';
        $status = isset($transaction->status) ? strtolower((string)$transaction->status) : '';
        return $statusCode === '000' || $status === 'approved';
    }

    public static function isChargeTransaction($transaction)
    {
        if (!$transaction) {
            return false;
        }
        $type = isset($transaction->type) ? (string)$transaction->type : '';
        return strcasecmp($type, 'Charge') === 0;
    }

    public static function transactionExists($transactionId)
    {
        if ($transactionId === null || $transactionId === '') {
            return false;
        }
        try {
            return Capsule::table('tblaccounts')->where('transid', $transactionId)->exists();
        } catch (\Throwable $e) {
            $result = select_query('tblaccounts', 'id', ['transid' => $transactionId]);
            return (bool)mysql_fetch_array($result);
        }
    }

    public static function extractCardData($ipn, array $input = [])
    {
        $details = $ipn && $ipn->details ? $ipn->details : null;
        $card = null;
        if ($details) {
            if (isset($details->data->card_information)) {
                $card = $details->data->card_information;
            } elseif (isset($details->card_information)) {
                $card = $details->card_information;
            }
        }

        $token = $card?->token_uid
            ?? $card?->token
            ?? $details?->token_uid
            ?? ($input['token_uid'] ?? null);
        $fourDigits = $card?->four_digits ?? ($input['four_digits'] ?? null);
        $expiryMonth = $card?->expiry_month ?? ($input['expiry_month'] ?? '');
        $expiryYear = $card?->expiry_year ?? ($input['expiry_year'] ?? '');
        $issuerId = $card?->issuer_id ?? ($input['issuer_id'] ?? null);

        return [
            'token_uid' => $token,
            'four_digits' => $fourDigits,
            'expiry' => $expiryMonth . $expiryYear,
            'issuer_id' => $issuerId,
            'terminal_uid' => $details?->data?->terminal_uid
                ?? $details?->terminal_uid
                ?? ($input['terminal_uid'] ?? null),
        ];
    }

    public static function amountsMatch($ipnAmount, array $invoice)
    {
        if ($ipnAmount === null || $ipnAmount === '') {
            return true;
        }
        $paid = (float)$ipnAmount;
        $candidates = [];
        if (isset($invoice['balance']) && $invoice['balance'] !== '') {
            $candidates[] = (float)$invoice['balance'];
        }
        if (isset($invoice['total']) && $invoice['total'] !== '') {
            $candidates[] = (float)$invoice['total'];
        }
        if (isset($invoice['subtotal']) && $invoice['subtotal'] !== '') {
            $candidates[] = (float)$invoice['subtotal'];
        }
        foreach ($candidates as $expected) {
            if (abs($paid - $expected) <= 0.05) {
                return true;
            }
        }
        return empty($candidates);
    }

    public static function applyVerifiedPayment($gatewayModuleName, array $gatewayParams, $transaction, $invoiceId)
    {
        if (!$invoiceId || !self::isApprovedTransaction($transaction) || !self::isChargeTransaction($transaction)) {
            return false;
        }

        $transactionId = $transaction->uid
            ?? $transaction->transaction_uid
            ?? null;
        if (!$transactionId) {
            return false;
        }

        $invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
        if (empty($invoice['result']) || $invoice['result'] !== 'success') {
            logTransaction(self::$GatewayName, ['invoiceid' => $invoiceId], 'Invalid Invoice');
            return false;
        }

        $amount = $transaction->amount ?? null;
        if (!self::amountsMatch($amount, $invoice)) {
            logTransaction(self::$GatewayName, [
                'invoiceid' => $invoiceId,
                'ipn_amount' => $amount,
                'invoice_total' => $invoice['total'] ?? null,
                'invoice_balance' => $invoice['balance'] ?? null,
            ], 'Amount Mismatch');
            return false;
        }

        if (self::transactionExists($transactionId) || ($invoice['status'] ?? '') === 'Paid') {
            logTransaction(self::$GatewayName, [
                'invoiceid' => $invoiceId,
                'transaction_uid' => $transactionId,
            ], 'Duplicate');
            return true;
        }

        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $amount,
            0,
            $gatewayModuleName
        );
        logTransaction(self::$GatewayName, json_decode(json_encode($transaction), true), 'Success');
        return true;
    }

    public static function saveVerifiedCard($gatewayModuleName, $userId, array $card)
    {
        global $cardNames;
        if (!$userId || empty($card['token_uid'])) {
            return false;
        }
        if (!function_exists('createCardPayMethod')) {
            return false;
        }
        try {
            if (class_exists('\WHMCS\User\Client')) {
                $client = \WHMCS\User\Client::find($userId);
                if ($client) {
                    foreach ($client->payMethods as $payMethod) {
                        $payment = $payMethod->payment ?? null;
                        if (
                            $payment
                            && method_exists($payment, 'getRemoteToken')
                            && $payment->getRemoteToken() === $card['token_uid']
                        ) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $cardName = null;
        if (!empty($card['issuer_id']) && isset($cardNames[$card['issuer_id']])) {
            $cardName = $cardNames[$card['issuer_id']];
        }

        try {
            $createCardStatus = createCardPayMethod(
                $userId,
                $gatewayModuleName,
                $card['four_digits'] ?? '',
                $card['expiry'] ?? '',
                $cardName,
                null,
                null,
                $card['token_uid']
            );
            if ($createCardStatus) {
                logModuleCall($gatewayModuleName, 'saveVerifiedCard', [
                    'userid' => $userId,
                    'fourDigits' => $card['four_digits'] ?? null,
                    'tokenData' => $card['token_uid'],
                ], 'Created cc token');
                return true;
            }
            logModuleCall($gatewayModuleName, 'saveVerifiedCard', [
                'userid' => $userId,
                'tokenData' => $card['token_uid'],
            ], "Wouldn't create cc token");
        } catch (\Exception $e) {
            logModuleCall($gatewayModuleName, 'saveVerifiedCard', [
                'userid' => $userId,
                'error' => $e->getMessage(),
            ], "Wouldn't create cc token");
        }
        return false;
    }

    public static function processNotification($gatewayModuleName, array $gatewayParams, array $input)
    {
        $transactionUid = $input['transaction_uid'] ?? null;
        $paymentRequestUid = $input['payment_request_uid'] ?? $input['page_request_uid'] ?? null;

        $ipn = self::verifyTransaction($gatewayParams, $transactionUid, $paymentRequestUid);
        if (!$ipn) {
            return self::processUnverifiedReturn($gatewayModuleName, $input);
        }

        $transaction = $ipn->getTransaction();
        $moreInfo = $transaction->more_info
            ?? ($ipn->details->more_info ?? null)
            ?? ($input['more_info'] ?? '');
        $meta = self::decodeMoreInfo($moreInfo);
        $urlInvoiceId = $input['invoiceid'] ?? null;
        $invoiceId = $meta['invoiceid'] ?: $urlInvoiceId;

        if ($meta['invoiceid'] && $urlInvoiceId && (int)$urlInvoiceId !== (int)$meta['invoiceid']) {
            logTransaction(self::$GatewayName, [
                'url_invoiceid' => $urlInvoiceId,
                'more_info_invoiceid' => $meta['invoiceid'],
            ], 'Invoice Mismatch');
            $invoiceId = $meta['invoiceid'];
        }

        if ($invoiceId && empty($meta['invoiceid'])) {
            $invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
            $belongsToUser = $meta['userid']
                && !empty($invoice['userid'])
                && (int)$invoice['userid'] === (int)$meta['userid'];
            if (
                empty($invoice['result'])
                || $invoice['result'] !== 'success'
                || !$belongsToUser
                || !self::amountsMatch($transaction->amount ?? null, $invoice)
            ) {
                $invoiceId = null;
            }
        }

        $paid = false;
        if ($invoiceId) {
            $paid = self::applyVerifiedPayment($gatewayModuleName, $gatewayParams, $transaction, $invoiceId);
        }

        $userId = $meta['userid'];
        if (!$userId && $invoiceId) {
            $invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
            if (!empty($invoice['userid'])) {
                $userId = (int)$invoice['userid'];
            }
        }

        $cardSaved = false;
        if (self::isApprovedTransaction($transaction)) {
            $card = self::extractCardData($ipn, $input);
            $cardSaved = self::saveVerifiedCard($gatewayModuleName, $userId, $card);
        }

        return [
            'success' => $paid || $cardSaved || self::isApprovedTransaction($transaction),
            'invoiceid' => $invoiceId,
            'userid' => $userId,
        ];
    }

    public static function processUnverifiedReturn($gatewayModuleName, array $input)
    {
        $meta = self::decodeMoreInfo($input['more_info'] ?? '');
        $userId = $meta['userid'];
        $type = strtolower((string)($input['type'] ?? ''));
        $approved = self::isApprovedTransaction((object)$input);
        $cardSaved = false;

        if ($type === 'token' && $approved && $userId) {
            $card = self::extractCardData(null, $input);
            $cardSaved = self::saveVerifiedCard($gatewayModuleName, $userId, $card);
            logTransaction(self::$GatewayName, [
                'userid' => $userId,
                'saved' => $cardSaved,
                'page_request_uid' => $input['page_request_uid'] ?? null,
            ], $cardSaved ? 'Token Saved' : 'Token Not Saved');
        } else {
            logTransaction(self::$GatewayName, $input, 'Unverified');
        }

        return [
            'success' => $cardSaved,
            'invoiceid' => null,
            'userid' => $userId,
        ];
    }

    public static function addInvoiceItemsToPaymentPage($paymentPage, array $params)
    {
        global $_LANG;
        $translations = self::getTranslation(substr($_LANG['locale'] ?? 'en', 0, 2));
        $items = [];
        $credit = 0;
        $taxCalculator = null;
        $client = null;

        try {
            if (!empty($params['cart']) && is_object($params['cart']) && method_exists($params['cart'], 'getInvoiceModel')) {
                $invoiceModel = $params['cart']->getInvoiceModel();
                $client = $params['cart']->client ?? null;
                if ($client && method_exists($params['cart'], 'getTaxCalculator')) {
                    $taxCalculator = $params['cart']->getTaxCalculator($client);
                }
                if ($invoiceModel && !empty($invoiceModel->lineItems)) {
                    foreach ($invoiceModel->lineItems as $item) {
                        $items[] = [
                            'price' => $item->amount,
                            'name' => $item->description,
                            'taxed' => !empty($item->taxed),
                        ];
                    }
                }
                if ($invoiceModel && method_exists($invoiceModel, 'getAttribute')) {
                    $credit = (float)$invoiceModel->getAttribute('credit');
                }
            }
        } catch (\Throwable $e) {
            $items = [];
        }

        if (!$items && !empty($params['invoiceid'])) {
            try {
                if (class_exists('\WHMCS\Billing\Invoice')) {
                    $invoiceModel = \WHMCS\Billing\Invoice::find($params['invoiceid']);
                    if ($invoiceModel) {
                        $lineItems = $invoiceModel->items ?? $invoiceModel->lineItems ?? [];
                        foreach ($lineItems as $item) {
                            $items[] = [
                                'price' => $item->amount,
                                'name' => $item->description,
                                'taxed' => !empty($item->taxed),
                            ];
                        }
                        $credit = (float)($invoiceModel->credit ?? 0);
                    }
                }
            } catch (\Throwable $e) {
                $items = [];
            }
        }

        if (!$items && !empty($params['invoiceid'])) {
            $invoice = localAPI('GetInvoice', ['invoiceid' => $params['invoiceid']]);
            if (!empty($invoice['items']['item']) && is_array($invoice['items']['item'])) {
                foreach ($invoice['items']['item'] as $item) {
                    $items[] = [
                        'price' => $item['amount'],
                        'name' => $item['description'] ?? 'Item',
                        'taxed' => !empty($item['taxed']),
                    ];
                }
            }
            if (isset($invoice['credit'])) {
                $credit = (float)$invoice['credit'];
            }
        }

        $total = 0;
        foreach ($items as $item) {
            $itemLine = [
                'price' => $item['price'],
                'name' => $item['name'],
                'quantity' => 1,
            ];
            if (
                $taxCalculator
                && $client
                && \WHMCS\Config\Setting::getValue('TaxEnabled')
                && !empty($item['taxed'])
                && empty($client->taxExempt)
            ) {
                $itemLine['price'] = $taxCalculator->setTaxBase($item['price'])->getTotalAfterTaxes();
            }
            $paymentPage->AddItem($itemLine);
            $total += $itemLine['price'];
        }

        if ($credit > 0) {
            $itemLine = [
                'price' => $credit * -1,
                'name' => 'Credit',
                'quantity' => 1,
            ];
            $paymentPage->AddItem($itemLine);
            $total -= $credit;
        }

        if (!empty($params['amount'])) {
            $paramsAmount = $params['amount'] * 100;
            $totalC = $total * 100;
            $diff = $paramsAmount - $totalC;
            if (abs($diff) == 1) {
                $paymentPage->AddItem([
                    'price' => $diff / 100,
                    'quantity' => 1,
                    'name' => $translations['rounding-difference'],
                ]);
            }
        }
    }

    public  static function updateTblOrders($invoiceId, $status = 'Active')
    {
        return;
    }
    public  static function updateTblHosting($invoiceId, $status)
    {
        return;
    }
    public static  function  getOrderId($invoiceId)
    {
        $table = "tblinvoiceitems";
        $fields = "relid";
        $where = array("invoiceid" => $invoiceId);
        $result = select_query($table, $fields, $where);

        $orderId = false;
        while ($row = mysql_fetch_array($result)) {

            $table = "tblhosting";
            $fields = "orderid";
            $where = array("id" => $row['relid']);
            $resultCycle = select_query($table, $fields, $where);
            $resultCycle = mysql_fetch_array($resultCycle);
            if (!empty($resultCycle)) {

                return $resultCycle['orderid'];
            }
        }
        return $orderId;
    }
    public static  function  getRecurring($invoiceId)
    {

        $table = "tblinvoiceitems";
        $fields = "relid";
        $where = array("invoiceid" => $invoiceId);
        $result = select_query($table, $fields, $where);

        $recurring = false;
        while ($row = mysql_fetch_array($result)) {

            $table = "tblhosting";
            $fields = "id,billingcycle,orderid";
            $where = array("id" => $row['relid']);
            $resultCycle = select_query($table, $fields, $where);
            $resultCycle = mysql_fetch_array($resultCycle);

            if (!empty($resultCycle['billingcycle']) && $resultCycle['billingcycle'] !== "One Time") {
                switch ($resultCycle['billingcycle']) {

                    case 'Monthly':
                        $externalRecurringRange = 1;
                        break;
                    case 'Quarterly':
                        $externalRecurringRange = 3;
                        break;
                    case 'Semi-Annually':
                        $externalRecurringRange = 6;
                        break;
                    case 'Annually':
                        $externalRecurringRange = 12;
                        break;
                    case 'Biennially':
                        $externalRecurringRange = 24;
                        break;
                    case 'Triennially':
                        $externalRecurringRange = 36;
                        break;
                }

                $recurring = array(
                    "external_recurring_id" => $resultCycle['id'],
                    "external_recurring_charge_id" => $invoiceId,
                    "external_recurring_type" => 2,
                    "external_recurring_range" => $externalRecurringRange

                );
                return $recurring;
            }
        }
        return $recurring;
    }

    public static function Capture($params)
    {
        global $_LANG;
        $translations = self::getTranslation(substr($_LANG['locale'], 0, 2));
        self::initApi($params);
        $params['gatewayid'] = explode(TOKEN_TERMINAL_SEPARATOR, $params['gatewayid']);
        $params['gatewayid'] = $params['gatewayid'][0];

        $paymentPage = new TokenPay;
        $paymentPage->Init([
            'payment_page_uid' =>  $params['paymentPageUID'],
            'currency_code' => $params['currency'],
            'amount' => $params['amount'],
            'token' => $params['gatewayid']
        ]);

        $numPayments = $_REQUEST['payments'] ?? null;
        if (defined('ADMINAREA') && ADMINAREA && $numPayments && $numPayments > 1) {
            $paymentPage->payments = $numPayments;
        }
        $recurring = self::getRecurring($params['invoiceid']);
        if ($recurring) {
            $paymentPage->set_external_recurring_payment($recurring);
        }
        self::addInvoiceItemsToPaymentPage($paymentPage, $params);

        $customer = [
            'customer_name' => ($params['clientdetails']['companyname']) ? $params['clientdetails']['companyname'] : $params['clientdetails']['fullname'],
            'email' => $params['clientdetails']['email'],
            'phone' => $params['clientdetails']['phonenumber'],
            'country' => $params['clientdetails']['countrycode'],
            'city' => $params['clientdetails']['city'],
            'address' => $params['clientdetails']['address1'],
        ];

        if ($params['vat_id_field_name']) {
            $vatNumber = $params['clientdetails'][$params['vat_id_field_name']];
            if (!empty($vatNumber)) {
                $customer['vat_number'] = PayplusBase::getNumberId($vatNumber);
            }
        }

        $paymentPage->SetCustomer($customer);
        if ($params['move_token'] === 'on') {
            $paymentPage->move_token = true;
        }
        $paymentPage->more_info = self::encodeMoreInfo(
            $params['clientdetails']['userid'] ?? null,
            $params['invoiceid']
        );
        $paymentPage->charge_method = ChargeMethods::CHARGE;
        $payments = $_COOKIE['payments-payplus'];

        if (!empty($payments)) {
            $paymentPage->payments_selected = $payments;
            $paymentPage->payments = $payments;
            $sumFirst = $params['amount'] / $payments;
            $paymentPage->payments_first_amount = $sumFirst;
            unset($_COOKIE['payments-payplus']);
            setcookie('payments-payplus', '', -1, '/');
        }
        $paymentPage->Go();

        if ($paymentPage->IsSuccess()) {
            $result = $paymentPage->Response->result ?? null;
            $transId = $result->transaction_uid
                ?? ($result->transaction->uid ?? null)
                ?? ($result->number ?? null);
            logTransaction(self::$GatewayName, $paymentPage->Response, 'Success');
            return [
                'status' => 'success',
                'transid' => $transId
            ];
        }

        logTransaction(self::$GatewayName, $paymentPage->Response->result ?? $paymentPage->Response, 'declined');
        logModuleCall('payplus', CURRENT_DEBUG_ACTION, [
            'error' => $paymentPage->GetErrors(),
            'payload' => $paymentPage->GetPayload()
        ], 'Req user ID...');
        $errors = $paymentPage->GetErrors();
        $html = '';
        if (in_array('vat-id-not-valid', $errors)) {
            $html .= '<div>' . $translations['invalid_tax_id'] . '</div>';
        }

        WHMCS\Session::set("credit-card-error", $html);
        return [
            'status' => 'declined',
            'rawdata' => $paymentPage->GetErrors(),
            'declinereason' => $paymentPage->GetErrors()
        ];
    }

    public static function RemoteInput($params)
    {


        global $_LANG;
        $translations = self::getTranslation(substr($_LANG['locale'], 0, 2));
        self::initApi($params);
        $clientDetails = $params['clientdetails'];
        $paymentPage = new PaymentPage;
        $currencyCode = 'ILS';
        $hasInvoiceCharge = !empty($params['invoiceid']) && !empty($params['amount']) && (float)$params['amount'] > 0;

        if ($params['clientdetails']['model'] && method_exists($params['clientdetails']['model'], 'getCurrencyCodeAttribute')) {
            $currencyCode = $params['clientdetails']['model']->getCurrencyCodeAttribute();
        }
        $paymentPage->Init([
            'payment_page_uid' =>  $params['paymentPageUID'],
            'currency_code' => $currencyCode,
            'amount' => $hasInvoiceCharge ? $params['amount'] : 0,
        ]);

        $customer = [
            'customer_name' => ($params['clientdetails']['companyname']) ? $params['clientdetails']['companyname'] : $params['clientdetails']['fullname'],
            'email' => $params['clientdetails']['email'],
            'phone' => $params['clientdetails']['phonenumber'],
            'country_iso' => $params['clientdetails']['countrycode'],
            'city' => $params['clientdetails']['city'],
            'address' => $params['clientdetails']['address1'],
        ];

        if ($params['vat_id_field_name']) {
            $vatNumber = $params['clientdetails'][$params['vat_id_field_name']];
            if (!empty($vatNumber)) {
                $customer['vat_number'] = PayplusBase::getNumberId($vatNumber);
            }
        }
        $paymentPage->SetCustomer($customer);
        $paymentPage->more_info = self::encodeMoreInfo(
            $clientDetails['userid'] ?? null,
            $params['invoiceid'] ?? null
        );
        $get = [];
        $systemUrl = rtrim($params['systemurl'], '/') . '/';
        $paymentPage->refURL_success = $systemUrl . 'modules/gateways/payplus/return.php';
        $paymentPage->refURL_callback = $systemUrl . 'modules/gateways/callback/' . self::$GatewayName . '.php';
        $get['g'] = md5(self::$GatewayName);
        if (!empty($params['invoiceid'])) {
            $get['invoiceid'] = $params['invoiceid'];
            $failUrl = $systemUrl . 'viewinvoice.php?id=' . urlencode($params['invoiceid']) . '&paymentfailed=1';
        } else {
            $failUrl = $systemUrl . 'clientarea.php?action=creditcard';
        }
        $paymentPage->refURL_failure = $failUrl;
        $paymentPage->refURL_cancel = $failUrl;
        $paymentPage->charge_method = $hasInvoiceCharge ? ChargeMethods::CHARGE : ChargeMethods::TOKEN;

        if ($hasInvoiceCharge) {
            $recurring = self::getRecurring($params['invoiceid']);
            if ($recurring) {
                $paymentPage->set_external_recurring_payment($recurring);
            }
            self::addInvoiceItemsToPaymentPage($paymentPage, $params);
        }

        if (defined('ADMINAREA') && ADMINAREA === true) {
            $get['adminarea'] = 1;
        }
        if (!empty($get)) {
            $paymentPage->refURL_success .= '?' . http_build_query($get);
        }

        $paymentPage->create_token = true;


        if ($paymentPage->Go()->IsSuccess()) {
            return '
            <script>
                var noAutoSubmit = true;
                jQuery(()=>{
                    jQuery("[name=ccframe]").attr("src","' . $paymentPage->payment_page_link . '")
                })
            </script>';
        } else {
            $errors = $paymentPage->GetErrors();
            logTransaction(self::$GatewayName, $paymentPage->Response, 'declined');

            $html = '<div style="color:red;">' . $translations['Operation encountered the following error/s'];
            $html .= '<ul>';
            if (in_array('vat-id-not-valid', $errors)) {
                $html .= '<li>' . $translations['invalid_tax_id'] . '</li>';
            }
            $html .= '<li>' . $paymentPage->Response->result . '</li>';
            $html .= '</ul>';
            $html .= '</div>';

            return $html;
        }
    }
    public static function Refund($params)
    {
        self::initApi($params);

        $transactionIdToRefund = $params['transid'];
        $refundAmount = $params['amount'];
        $refund = new RefundByTransactionUID;
        $refund->Init([
            'transaction_uid' => $transactionIdToRefund,
            'amount' => $refundAmount,

        ]);
        $result  = [];
        if ($refund->Go()->IsSuccess()) {
            logTransaction(self::$GatewayName,  $refund->details, 'Success');
            $result['status'] = 'success';
            $result['rawdata'] = '';
            $result['transid'] = $refund->details->uid;
        } else {
            logTransaction(self::$GatewayName,  $refund->details, 'Declined');
            $result['status'] = 'error';
            $result['rawdata'] = '';
            $result['transid'] = 0;
        }
        return $result;
    }
    public static function RemoteStore($params)
    {



        self::initApi($params);

        switch ($params['action']) {
            case REMOTE_STORE_ACTION_CREATE:
                return [
                    'status' => 'success',
                    'gatewayid' => $params['remoteStorageToken'] ?? $params['gatewayid'] ?? '',
                ];
            case REMOTE_STORE_ACTION_DELETE:
                $removeToken = new Remove;
                $removeToken->Init([
                    'uid' => $params['remoteStorageToken']
                ]);
                $removeToken->Go();
                return [
                    'status' => 'success'
                ];
                break;

            case REMOTE_STORE_ACTION_UPDATE:
                $tokenData = explode(TOKEN_TERMINAL_SEPARATOR, $params['gatewayid']);
                $tokenUID = $tokenData[0];
                $updateToken = new Update;
                $payment = $params['payMethod']->payment;

                $updateToken->Init([
                    'uid' => $tokenUID,
                    'terminal_uid' => $params['terminalUID'],
                    'credit_card_number' => $params['cardlastfour'],
                    'card_date_mmyy' => $params['cardexp'],
                ]);
                $exp = \WHMCS\Carbon::createFromDate($params['cardExpiryYear'], $params['cardExpiryMonth'], 1);
                if ($updateToken->Go()->IsSuccess()) {
                    logTransaction(self::$GatewayName,  $updateToken->Response, 'Success');
                    $payment->setExpiryDate($exp);
                    $payment->save();
                    return [
                        'status' => 'success',
                        'gatewayid' => $tokenUID
                    ];
                } else {
                    logTransaction(self::$GatewayName,  $updateToken->Response, 'Declined');
                }
                break;
        }

        return [
            'status' => 'failed'
        ];
    }

    public static function getTranslation($lang)
    {
        $translations = [];
        $translations['coupon-discount'] = 'Coupon discount';
        $translations['rounding-difference'] = 'Rounding difference';
        $translations['Operation encountered the following error/s'] = 'Operation encountered the following error/s';
        $translations['invalid_tax_id'] = 'Invalid vat number/ID';
        if ($lang == 'he') {
            $translations['coupon-discount'] = 'הנחת קופון';
            $translations['rounding-difference'] = 'הפרש עיגול';
            $translations['Operation encountered the following error/s'] = 'שגיאה';
            $translations['invalid_tax_id'] = 'ת"ז/ח"פ שגויים';
        }
        return $translations;
    }
}
