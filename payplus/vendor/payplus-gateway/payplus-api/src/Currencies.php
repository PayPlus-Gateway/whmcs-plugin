<?php
/**
 * Low level API for integration with the PayPlus payment processing gateway
 * @package Payplus-API
 * @author PayPlus LTD <info@payplus.co.il> https://www.payplus.co.il
 * @since 1.0.0
 */
namespace PayplusGateway\PayplusApi;

abstract class Currencies
{
    const ILS = 'ILS';
    const USD = 'USD';
    const GBP = 'GBP';
    const EUR = 'EUR';
    const JPY = 'JPY';
    const AFN = 'AFN';
}
