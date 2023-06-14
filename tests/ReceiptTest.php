<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace tests;

use netFantom\RobokassaApi\Options\Item;
use netFantom\RobokassaApi\Options\PaymentMethod;
use netFantom\RobokassaApi\Options\PaymentObject;
use netFantom\RobokassaApi\Options\Receipt;
use netFantom\RobokassaApi\Options\Sno;
use netFantom\RobokassaApi\Options\Tax;
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
}
