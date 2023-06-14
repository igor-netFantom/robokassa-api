<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Options;

class ResultOptions
{
    /**
     * @param float|string $outSum Требуемая к получению сумма
     * (буквально — стоимость заказа, сделанного клиентом). Формат представления — число,
     * разделитель — точка, например: 123.45.
     * Сумма должна быть указана в рублях.
     * Но, если стоимость товаров у Вас на сайте указана, например, в долларах,
     * то при выставлении счёта к оплате Вам необходимо указывать уже пересчитанную сумму из долларов в рубли.
     * {@see self::$outSumCurrency}
     * @param int|null $invId Номер счета в магазине
     * Необязательный параметр, но мы настоятельно рекомендуем его использовать.
     * Значение этого параметра должно быть уникальным для каждой оплаты.
     * Может принимать значения от 1 до 2147483647 (231-1).
     * Если значение параметра пустое, или равно 0, или параметр вовсе не указан,
     * то при создании операции оплаты ему автоматически будет присвоено уникальное значение.
     * @param string $signatureValue
     * @param array<string, string> $userParameters
     */
    public function __construct(
        public readonly float|string $outSum,
        public readonly int|null $invId,
        public readonly string $signatureValue,
        public readonly array $userParameters = [],
    ) {
    }

    public function formatOkAnswer(): string
    {
        return isset($this->invId) ? 'OK' . $this->invId : 'OK';
    }
}
