<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace tests;

use netFantom\RobokassaApi\Results\InvoicePayResult;
use PHPUnit\Framework\TestCase as Unit;

/**
 * @group robokassa
 * @group robokassaApi
 */
class InvoicePayResultTest extends Unit
{
    public function testFormatOkAnswer(): void
    {
        $invoicePayResult = new InvoicePayResult(10, 999, '');
        $this->assertEquals('OK999', $invoicePayResult->formatOkAnswer());

        $invoicePayResult = new InvoicePayResult(10, null, '');
        $this->assertEquals('OK', $invoicePayResult->formatOkAnswer());
    }
}
