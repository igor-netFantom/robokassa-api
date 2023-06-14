<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Options;

/**
 * Данные для фискализации
 */
class Receipt
{
    /**
     * @param Item[] $items Массив данных о позициях чека
     * @param ?Sno $sno Система налогообложения.
     * Необязательное поле, если у организации имеется только один тип налогообложения.
     * (Данный параметр обязательно задается в личном кабинете магазина)
     */
    public function __construct(
        public readonly array $items,
        public readonly ?Sno $sno = null,
    ) {
    }
}
