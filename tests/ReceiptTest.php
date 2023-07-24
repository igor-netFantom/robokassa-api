<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace tests;

use netFantom\RobokassaApi\Params\Item\{PaymentMethod, PaymentObject};
use netFantom\RobokassaApi\Params\Receipt;
use netFantom\RobokassaApi\Params\Receipt\{Item, Sno, Tax};
use PHPUnit\Framework\TestCase as Unit;

/**
 * @group robokassa
 * @group robokassaApi
 */
class ReceiptTest extends Unit
{
    public function testJsonSerialize(): void
    {
        $receipt = new Receipt(
            items: [
                new Item(
                    name: "Название товара 1",
                    quantity: 1,
                    sum: 100,
                    tax: Tax::vat10,
                    payment_method: PaymentMethod::full_payment,
                    payment_object: PaymentObject::commodity,
                ),
                new Item(
                    name: "Название товара 2",
                    quantity: 3,
                    sum: 600,
                    tax: Tax::vat20,
                    payment_method: PaymentMethod::full_payment,
                    payment_object: PaymentObject::service,
                    cost: 200,
                    nomenclature_code: '04620034587217',
                ),
            ],
            sno: Sno::osn
        );
        $serializedReceipt = json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $expected = '{"items":['
            . '{"sum":"100.00","name":"Название товара 1","quantity":1,"tax":"vat10","payment_method":"full_payment",'
            . '"payment_object":"commodity"},'
            . '{"sum":"600.00","name":"Название товара 2","quantity":3,"tax":"vat20","payment_method":"full_payment",'
            . '"payment_object":"service","cost":200,"nomenclature_code":"04620034587217"}'
            . '],"sno":"osn"}';
        $this->assertEquals($expected, $serializedReceipt);
    }

    public function testWrongItems(): void
    {
        $this->expectExceptionMessage('$items must be array of Item objects');
        /** @noinspection PhpParamsInspection */
        new Receipt(
            items: ['wrong'],
            sno: Sno::osn
        );
    }
}
