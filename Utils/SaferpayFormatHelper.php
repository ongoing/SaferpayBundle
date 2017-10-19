<?php

namespace Ongoing\Payment\SaferpayBundle\Utils;

/**
 * SaferpayFormatHelper
 *
 * @package Valiton\Payment\SaferpayBundle\Utils
 * @author Sven Cludius<sven.cludius@valiton.com>
 */
class SaferpayFormatHelper
{
    /**
     * @param $amount
     * @param int $base
     * @return float
     */
    public static function formatAmount($amount, $base = 100)
    {
        if ($amount <= 0) {
            return 0;
        }
        if ($base <= 0) {
            return 0;
        }
        return $amount * $base;
    }
}