<?php
/**
 * Low level API for integration with the PayPlus payment processing gateway
 * @package Payplus-API
 * @author PayPlus LTD <info@payplus.co.il> https://www.payplus.co.il
 * @since 1.0.0
 */
namespace PayplusGateway\PayplusApi;

abstract class ChargeMethods
{
    const CHECK = 0;
    const CHARGE = 1;
    const APPROVAL = 2;
    const RECURRING_PAYMENTS = 3;
    const REFUND = 4;
    const TOKEN = 5;
}
