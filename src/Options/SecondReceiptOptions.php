<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Options;

use netFantom\RobokassaApi\Exceptions\InvalidArgumentException;
use netFantom\RobokassaApi\Params\Receipt\{Client, Item, Payment, Sno, Vat};

/**
 * Данные для фискализации
 */
class SecondReceiptOptions
{
    public const SECOND_RECEIPT_OPERATION_SELL = 'sell';
    /**
     * @var string Тип чека. Может принимать только одно значение Sell.
     */
    public readonly string $operation;
    public readonly string $total;
    public readonly array $payments;

    /**
     * @param int $id Номер заказа магазина (не должен совпадать с OriginId). Значение только целое число.
     * @param int $originId Номер заказа магазина (InvId), по которому уже есть чек
     * и для которого выбивается второй чек. Значение только целое число.
     * @param string $url Адрес сайта, на котором осуществлена продажа.
     * @param float|string $total Итоговая сумма чека в рублях. Десятичное положительное число:
     * целая часть не более 8 знаков, дробная часть не более 2 знаков.
     * @param Item[] $items Массив данных о позициях чека
     * @param Vat[] $vats Тип и сумма налога.
     * @param ?Sno $sno Система налогообложения.
     * Необязательное поле, если у организации имеется только один тип налогообложения.
     * (Данный параметр обязательно задается в личном кабинете магазина)
     * @param Client|null $client Данные о покупателе. Содержит любое из полей или все поля одновременно.
     * @param Payment[] $payments Тип и сумма платежа или чека.
     * @param ?string $merchantId Идентификатор магазина в Robokassa, который Вы придумали при создании магазина.
     * (берется из {@see RobokassaApi::$merchantLogin} при использовании в методах {@see RobokassaApi}, если не указан)
     */
    public function __construct(
        public readonly int $id,
        public readonly int $originId,
        public readonly string $url,
        float|string $total,
        public readonly array $items,
        public readonly array $vats,
        public readonly ?Sno $sno = null,
        public readonly ?Client $client = null,
        ?array $payments = null,
        public ?string $merchantId = null,
    ) {
        $this->operation = self::SECOND_RECEIPT_OPERATION_SELL;
        $this->total = number_format(num: (float)$total, decimals: 2, thousands_separator: '');
        $this->payments = $payments ?? [new Payment($this->total)];

        foreach ($this->items as $item) {
            if (!$item instanceof Item) {
                throw new InvalidArgumentException(
                    '$items must be array of Item objects'
                );
            }
        }
        foreach ($this->payments as $payment) {
            if (!$payment instanceof Payment) {
                throw new InvalidArgumentException(
                    '$payments must be array of Payment objects'
                );
            }
        }

        foreach ($this->vats as $vat) {
            if (!$vat instanceof Vat) {
                throw new InvalidArgumentException(
                    '$vats must be array of Vat objects'
                );
            }
        }
    }
}
