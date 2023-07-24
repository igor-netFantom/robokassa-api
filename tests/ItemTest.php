<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace tests;

use netFantom\RobokassaApi\Params\Item\{PaymentMethod, PaymentObject};
use netFantom\RobokassaApi\Params\Receipt\{Item, Tax};
use PHPUnit\Framework\TestCase as Unit;

/**
 * @group robokassa
 * @group robokassaApi
 */
class ItemTest extends Unit
{
    public function testJsonSerialize(): void
    {
        $item = new Item(
            name: "Название товара",
            quantity: 5,
            sum: 500,
            tax: Tax::vat10,
            payment_method: PaymentMethod::full_payment,
            payment_object: PaymentObject::service,
            cost: 100,
            nomenclature_code: '0123456789',
        );
        $serializedItem = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $expected = '{"sum":"500.00","name":"Название товара","quantity":5,"tax":"vat10",'
            . '"payment_method":"full_payment","payment_object":"service","cost":100,"nomenclature_code":"0123456789"}';
        $this->assertEquals($expected, $serializedItem);

        $item = new Item(
            name: "Название товара",
            quantity: 5,
            sum: 500,
            tax: Tax::vat10,
            payment_method: PaymentMethod::full_payment,
            payment_object: PaymentObject::service,
        );
        $serializedItem = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $expected = '{"sum":"500.00","name":"Название товара","quantity":5,"tax":"vat10",'
            . '"payment_method":"full_payment","payment_object":"service"}';
        $this->assertEquals($expected, $serializedItem);
    }
}
