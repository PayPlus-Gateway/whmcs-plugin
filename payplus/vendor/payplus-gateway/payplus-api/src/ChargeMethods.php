<?php

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
