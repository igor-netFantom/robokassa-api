<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Options;

/**
 * Тип и сумма платежа или чека для формирования второго чека.
 */
class Payment
{
    public const TYPE_ADVANCE_PAYMENT = 2;
    /**
     * @var int Тип платежа. Должен принимать значение:
     * «2» – предварительная оплата (зачет аванса и (или) предыдущих платежей).
     */
    public readonly int $type;
    public readonly string $sum;

    /**
     * @param float|string $sum Сумма платежа. Десятичное положительное число:
     * целая часть не более 8 знаков, дробная часть не более 2 знаков.
     */
    public function __construct(
        float|string $sum,
    ) {
        $this->type = self::TYPE_ADVANCE_PAYMENT;
        $this->sum = number_format(num: (float)$sum, decimals: 2, thousands_separator: '');
    }
}
