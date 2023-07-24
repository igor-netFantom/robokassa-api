<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Params\Receipt;

use JsonSerializable;
use netFantom\RobokassaApi\Options\JsonSerializeMethod;
use netFantom\RobokassaApi\Params\Item\{PaymentMethod, PaymentObject};

class Item implements JsonSerializable
{
    use JsonSerializeMethod;

    public readonly string $sum;

    /**
     * @param string $name Обязательное поле. Наименование товара. Строка, максимальная длина 128 символа.
     * Если в наименовании товара Вы используете специальные символы, например кавычки,
     * то их обязательно необходимо экранировать.
     * @param int $quantity Обязательное поле. Количество товаров.
     * @param float|string $sum Обязательное поле.
     * Полная сумма в рублях за итоговое количество данного товара с учетом всех возможных скидок,
     * бонусов и специальных цен.
     * Десятичное положительное число: целая часть не более 8 знаков, дробная часть не более 2 знаков.
     * @param Tax $tax Обязательное поле. Это поле устанавливает налоговую ставку в ККТ.
     * Определяется для каждого вида товара по отдельности, но за все единицы конкретного товара вместе.
     * @param PaymentMethod|null $payment_method Признак способа расчёта. Этот параметр необязательный.
     * Если этот параметр не передан клиентом, то в чеке будет
     * указано значение параметра по умолчанию из Личного кабинета.
     * @param PaymentObject|null $payment_object Признак предмета расчёта. Этот параметр необязательный.
     * Если этот параметр не передан клиентом, то в чеке будет
     * указано значение параметра по умолчанию из Личного кабинета.
     * @param float|null $cost Необязательное поле.
     * Полная сумма в рублях за единицу товара с учетом всех возможных скидок, бонусов и специальных цен.
     * Десятичное положительное число: целая часть не более 8 знаков, дробная часть не более 2 знаков.
     * Параметр можно передавать вместо параметра sum.
     * При передаче параметра общая сумма товарных позиций рассчитывается по формуле (cost*quantity)=sum.
     * Если в запросе переданы и sum и cost последний будет игнорироваться.
     * @param string|null $nomenclature_code Маркировка товара, передаётся в том виде, как она напечатана
     * на упаковке товара. Параметр является обязательным только для тех магазинов,
     * которые продают товары подлежащие обязательной маркировке.
     * Код маркировки расположен на упаковке товара, рядом со штрих-кодом или в виде QR-кода.
     */
    public function __construct(
        public readonly string $name,
        public readonly int $quantity,
        float|string $sum,
        public readonly Tax $tax,
        /** Значение по-умолчанию не выставлено в null для автоматического предложения заполнить параметр в Phpstorm */
        public readonly ?PaymentMethod $payment_method,
        /** Значение по-умолчанию не выставлено в null для автоматического предложения заполнить параметр в Phpstorm */
        public readonly ?PaymentObject $payment_object,
        public readonly ?float $cost = null,
        public readonly ?string $nomenclature_code = null,
    ) {
        $this->sum = number_format(num: (float)$sum, decimals: 2, thousands_separator: '');
    }
}
