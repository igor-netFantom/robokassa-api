<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Params\Receipt;

/**
 * Данные о покупателе для формирования второго чека. Содержит любое из полей или все поля одновременно.
 */
class Client
{
    /**
     * @param string|null $email Эл. почта покупателя.
     * @param string|null $phone Телефон покупателя.
     */
    public function __construct(
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
    ) {
    }
}
