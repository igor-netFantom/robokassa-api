<?php

declare(strict_types=1);

namespace tests;

use netFantom\RobokassaApi\Options\Client;
use netFantom\RobokassaApi\Options\Item;
use netFantom\RobokassaApi\Options\Payment;
use netFantom\RobokassaApi\Options\PaymentMethod;
use netFantom\RobokassaApi\Options\PaymentObject;
use netFantom\RobokassaApi\Options\SecondReceiptOptions;
use netFantom\RobokassaApi\Options\Sno;
use netFantom\RobokassaApi\Options\Tax;
use netFantom\RobokassaApi\RobokassaApi;
use PHPUnit\Framework\TestCase;

class SecondReceiptOptionsTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $items = [
            new Item(
                name: 'Товар',
                quantity: 1,
                sum: 100,
                tax: Tax::none,
                payment_method: PaymentMethod::full_payment,
                payment_object: PaymentObject::commodity
            )
        ];
        $secondReceiptOptions = new SecondReceiptOptions(
            id: 14,
            originId: 13,
            url: 'https://www.robokassa.ru/',
            total: 100,
            items: $items,
            vats: RobokassaApi::getVatsFromItems($items),
            sno: Sno::osn,
            client: new Client(
                email: 'test@test.ru',
                phone: '71234567890',
            ),
            payments: [
                new Payment(100),
            ],
            merchantId: 'test',
        );

        $serializedSecondReceiptOptions = json_encode(
            $secondReceiptOptions,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        $expectedSerializedSecondReceiptOptions = <<<'JSON'
            {
                "operation": "sell",
                "total": "100.00",
                "payments": [
                    {
                        "type": 2,
                        "sum": "100.00"
                    }
                ],
                "id": 14,
                "originId": 13,
                "url": "https://www.robokassa.ru/",
                "items": [
                    {
                        "sum": "100.00",
                        "name": "Товар",
                        "quantity": 1,
                        "tax": "none",
                        "payment_method": "full_payment",
                        "payment_object": "commodity"
                    }
                ],
                "vats": [
                    {
                        "sum": "0.00",
                        "type": "none"
                    }
                ],
                "sno": "osn",
                "client": {
                    "email": "test@test.ru",
                    "phone": "71234567890"
                },
                "merchantId": "test"
            }
            JSON;
        $this->assertEquals($expectedSerializedSecondReceiptOptions, $serializedSecondReceiptOptions);

        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
        );
        $secondReceiptOptions->merchantId = null;
        $secondReceiptPostDateWithSignature = $robokassa->getBase64SignedPostData($secondReceiptOptions);
        $expectedPostDataWithSignature = 'eyJvcGVyYXRpb24iOiJzZWxsIiwidG90YWwiOiIxMDAuMDAiLCJwYXltZW50cyI6W3sidHlwZSI'
            . '6Miwic3VtIjoiMTAwLjAwIn1dLCJpZCI6MTQsIm9yaWdpbklkIjoxMywidXJsIjoiaHR0cHM6Ly93d3cucm9ib2thc3NhLnJ1LyIsI'
            . 'ml0ZW1zIjpbeyJzdW0iOiIxMDAuMDAiLCJuYW1lIjoi0KLQvtCy0LDRgCIsInF1YW50aXR5IjoxLCJ0YXgiOiJub25lIiwicGF5bWV'
            . 'udF9tZXRob2QiOiJmdWxsX3BheW1lbnQiLCJwYXltZW50X29iamVjdCI6ImNvbW1vZGl0eSJ9XSwidmF0cyI6W3sic3VtIjoiMC4wM'
            . 'CIsInR5cGUiOiJub25lIn1dLCJzbm8iOiJvc24iLCJjbGllbnQiOnsiZW1haWwiOiJ0ZXN0QHRlc3QucnUiLCJwaG9uZSI6IjcxMjM'
            . '0NTY3ODkwIn0sIm1lcmNoYW50SWQiOiJyb2JvLWRlbW8ifQ.OGNhYmQ0ZDQyNDZhNzQ4YWMyNTRhMWIxNWY0MDFlYmI';

        $this->assertEquals($expectedPostDataWithSignature, $secondReceiptPostDateWithSignature);
    }

    public function testWrongItems(): void
    {
        $this->expectExceptionMessage('$items must be array of Item objects');
        /** @noinspection PhpParamsInspection */
        new SecondReceiptOptions(
            id: 14,
            originId: 13,
            url: 'https://www.robokassa.ru/',
            total: 100,
            items: ['wrong'],
            vats: [],
            merchantId: 'test',
        );
    }

    public function testWrongPayments(): void
    {
        $this->expectExceptionMessage('$payments must be array of Payment objects');
        /** @noinspection PhpParamsInspection */
        new SecondReceiptOptions(
            id: 14,
            originId: 13,
            url: 'https://www.robokassa.ru/',
            total: 100,
            items: [],
            vats: [],
            payments: ['wrong'],
            merchantId: 'test',
        );
    }

    public function testWrongVats(): void
    {
        $this->expectExceptionMessage('$vats must be array of Vat objects');
        /** @noinspection PhpParamsInspection */
        new SecondReceiptOptions(
            id: 14,
            originId: 13,
            url: 'https://www.robokassa.ru/',
            total: 100,
            items: [],
            vats: ['wrong'],
            merchantId: 'test',
        );
    }
}
