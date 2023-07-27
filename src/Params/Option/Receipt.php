<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Params\Option;

use netFantom\RobokassaApi\Exceptions\InvalidArgumentException;
use netFantom\RobokassaApi\Params\Receipt\{Item, Sno};

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
        foreach ($this->items as $item) {
            if (!$item instanceof Item) {
                throw new InvalidArgumentException(
                    '$items must be array of Item objects'
                );
            }
        }
    }
}
