<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Params\Receipt;

/**
 * Обязательное поле. Это поле устанавливает налоговую ставку в ККТ.
 * Определяется для каждого вида товара по отдельности, но за все единицы конкретного товара вместе.
 */
enum Tax: string
{
    /**
     * – Без НДС.
     */
    case none = 'none';
    /**
     * – НДС по ставке 0%
     */
    case vat0 = 'vat0';
    /**
     * – НДС чека по ставке 10%
     */
    case vat10 = 'vat10';
    /**
     * – НДС чека по расчетной ставке 10/110
     */
    case vat110 = 'vat110';
    /**
     * – НДС чека по ставке 20%
     */
    case vat20 = 'vat20';
    /**
     * – НДС чека по расчетной ставке 20/120
     */
    case vat120 = 'vat120';
    /**
     * – НДС по ставке 5%
     */
    case vat5 = 'vat5';
    /**
     * – НДС по ставке 7%
     */
    case vat7 = 'vat7';
    /**
     * – НДС чека по расчетной ставке 5/105
     */
    case vat105 = 'vat105';
    /**
     * – НДС чека по расчетной ставке 7/107
     */
    case vat107 = 'vat107';

    public function getTaxSumFromItemSum(string $sum): string
    {
        return number_format(num: (float)$sum * $this->getTaxMultiplier(), decimals: 2, thousands_separator: '');
    }

    private function getTaxMultiplier(): float|int
    {
        return match ($this) {
            self::none, self::vat0 => 0,
            self::vat10 => 0.1,
            self::vat110 => 10 / 110,
            self::vat20 => 0.2,
            self::vat120 => 20 / 120,
            self::vat5 => 0.05,
            self::vat7 => 0.07,
            self::vat105 => 5 / 105,
            self::vat107 => 7 / 107,
        };
    }
}
