<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace tests;

use netFantom\RobokassaApi\Options\ResultOptions;
use PHPUnit\Framework\TestCase as Unit;

/**
 * @group robokassa
 * @group robokassaApi
 */
class ResultOptionsTest extends Unit
{
    public function testFormatOkAnswer(): void
    {
        $resultOptions = new ResultOptions(10, 999, '');
        $this->assertEquals('OK999', $resultOptions->formatOkAnswer());

        $resultOptions = new ResultOptions(10, null, '');
        $this->assertEquals('OK', $resultOptions->formatOkAnswer());
    }
}
