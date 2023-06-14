<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace tests;

use ArgumentCountError;
use DateTime;
use DateTimeZone;
use netFantom\RobokassaApi\Exceptions\TooLongSmsMessageException;
use netFantom\RobokassaApi\Options\Culture;
use netFantom\RobokassaApi\Options\InvoiceOptions;
use netFantom\RobokassaApi\Options\Item;
use netFantom\RobokassaApi\Options\OutSumCurrency;
use netFantom\RobokassaApi\Options\PaymentMethod;
use netFantom\RobokassaApi\Options\PaymentObject;
use netFantom\RobokassaApi\Options\Receipt;
use netFantom\RobokassaApi\Options\ResultOptions;
use netFantom\RobokassaApi\Options\Sno;
use netFantom\RobokassaApi\Options\Tax;
use netFantom\RobokassaApi\RobokassaApi;
use PHPUnit\Framework\TestCase as Unit;

/**
 * @group robokassa
 * @group robokassaApi
 */
class RobokassaApiTest extends Unit
{
    public function testExpirationDate(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
        );

        $returnUrl = $robokassa->getPaymentUrl(
            new InvoiceOptions(
                outSum: 10.00,
                invId: 1,
                description: 'Description',
                expirationDate: (new DateTime('2030-01-01 10:20:30', new DateTimeZone('+3'))),
                userParameters: [
                    'user_id' => 1,
                    'email' => 'user@example.com',
                ],
            ),
        );
        $expected = 'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=robo-demo&OutSum=10.00'
            . '&Description=Description&SignatureValue=b98027823a17ee5be83c767f18315400&InvId=1&Encoding=utf-8'
            . '&ExpirationDate=2030-01-01T10%3A20%3A30.0000000%2B03%3A00&shp_email=user%40example.com&shp_user_id=1';
        $this->assertEquals($expected, $returnUrl);
    }

    public function testGetPaymentParameters(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
        );

        $paymentParameters = $robokassa->getPaymentParameters(
            new InvoiceOptions(
                outSum: 100,
                invId: 999,
                description: 'Description',
                receipt: new Receipt(
                    items: [
                        new Item(
                            name: 'Название товара 1 (50%)',
                            quantity: 1,
                            sum: 100,
                            tax: Tax::vat10,
                            payment_method: PaymentMethod::full_payment,
                            payment_object: PaymentObject::commodity
                        ),
                        new Item(
                            name: 'Название товара 2',
                            quantity: 3,
                            sum: 450,
                            tax: Tax::vat120,
                            payment_method: PaymentMethod::full_payment,
                            payment_object: PaymentObject::excise,
                            nomenclature_code: '04620034587217'
                        ),
                    ],
                    sno: Sno::osn,
                ),
                culture: Culture::en,
            ),
        );

        $expectedPaymentParameters = [
            'MerchantLogin' => 'robo-demo',
            'OutSum' => '100.00',
            'Description' => 'Description',
            'SignatureValue' => '9da55279722ba167a1384b05a2c5e330',
            'IncCurrLabel' => null,
            'InvId' => 999,
            'Culture' => 'en',
            'Encoding' => 'utf-8',
            'Email' => null,
            'ExpirationDate' => null,
            'OutSumCurrency' => null,
            'UserIp' => null,
            'Receipt' => '{"items":['
                . '{"sum":"100.00","name":"Название товара 1 (50%)","quantity":1,"tax":"vat10",'
                . '"payment_method":"full_payment","payment_object":"commodity"},'
                . '{"sum":"450.00","name":"Название товара 2","quantity":3,"tax":"vat120",'
                . '"payment_method":"full_payment","payment_object":"excise","nomenclature_code":"04620034587217"}'
                . '],"sno":"osn"}',
            'IsTest' => null,
        ];
        $this->assertEquals($expectedPaymentParameters, $paymentParameters);
    }

    public function testGetResultOptionsFromRequestArray(): void
    {
        $signatureValue = md5('100:1:password_2');
        $email = 'test@exmaple.com';

        $_POST['OutSum'] = 100;
        $_POST['InvId'] = 1;
        $_POST['SignatureValue'] = $signatureValue;
        $_POST['shp_email'] = $email;

        $expectedResultOptions = new ResultOptions(
            outSum: 100,
            invId: 1,
            signatureValue: $signatureValue,
            userParameters: [
                'email' => 'test@exmaple.com'
            ]
        );

        $resultOptions = RobokassaApi::getResultOptionsFromRequestArray($_POST);

        $this->assertEquals($expectedResultOptions, $resultOptions);
    }

    public function testGetSendSmsRequestData(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
        );

        $expectedRequestData = [
            'login' => 'robo-demo',
            'phone' => 70001234567,
            'message' => 'message text',
            'signature' => '745a08955b089f7d4a64a24e6ba37611',
        ];
        $actualRequestData = $robokassa->getSendSmsRequestData(70001234567, 'message text');

        $this->assertEquals($expectedRequestData, $actualRequestData);

        $robokassa->getSendSmsRequestData(70001234567, str_repeat('x', 128));

        try {
            $robokassa->getSendSmsRequestData(70001234567, str_repeat('x', 129));
            $this->fail('Expect ' . TooLongSmsMessageException::class);
        } catch (TooLongSmsMessageException) {
        }
    }

    public function testGetUserParametersFromRequestArray(): void
    {
        $requestParameters = [
            'a' => 'wrong',
            'shp_user_id' => 1,
            'login' => 'wrong',
            'shp_email' => 'user@example.com',
        ];
        $expectedParameters = json_encode([
            'user_id' => 1,
            'email' => 'user@example.com',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $userParameters = json_encode(
            RobokassaApi::getUserParametersFromRequestArray($requestParameters),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
        $this->assertEquals($expectedParameters, $userParameters);
    }

    public function testPaymentReceipt(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
        );

        $returnUrl = $robokassa->getPaymentUrl(
            new InvoiceOptions(
                outSum: 100,
                invId: 999,
                description: 'Description',
                receipt: new Receipt(
                    items: [
                        new Item(
                            name: 'Название товара 1',
                            quantity: 1,
                            sum: 100,
                            tax: Tax::vat10,
                            payment_method: PaymentMethod::full_payment,
                            payment_object: PaymentObject::commodity
                        ),
                        new Item(
                            name: 'Название товара 2',
                            quantity: 3,
                            sum: 450,
                            tax: Tax::vat120,
                            payment_method: PaymentMethod::full_payment,
                            payment_object: PaymentObject::excise,
                            nomenclature_code: '04620034587217'
                        ),
                    ],
                    sno: Sno::osn,
                ),
                culture: Culture::en,
            ),
        );

        $expected = 'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=robo-demo&OutSum=100.00&Description=Description&SignatureValue=85a14f26ea37f92bef294e8d19d1a141&InvId=999&Culture=en&Encoding=utf-8&Receipt=%7B%22items%22%3A%5B%7B%22sum%22%3A%22100.00%22%2C%22name%22%3A%22%D0%9D%D0%B0%D0%B7%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5+%D1%82%D0%BE%D0%B2%D0%B0%D1%80%D0%B0+1%22%2C%22quantity%22%3A1%2C%22tax%22%3A%22vat10%22%2C%22payment_method%22%3A%22full_payment%22%2C%22payment_object%22%3A%22commodity%22%7D%2C%7B%22sum%22%3A%22450.00%22%2C%22name%22%3A%22%D0%9D%D0%B0%D0%B7%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5+%D1%82%D0%BE%D0%B2%D0%B0%D1%80%D0%B0+2%22%2C%22quantity%22%3A3%2C%22tax%22%3A%22vat120%22%2C%22payment_method%22%3A%22full_payment%22%2C%22payment_object%22%3A%22excise%22%2C%22nomenclature_code%22%3A%2204620034587217%22%7D%5D%2C%22sno%22%3A%22osn%22%7D';
        $this->assertEquals($expected, $returnUrl);
    }

    public function testPaymentUrl(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password_1',
            password2: 'password_2',
            isTest: true,
            hashAlgo: 'md5',
        );
        $returnUrl = $robokassa->getPaymentUrl(
            new InvoiceOptions(
                outSum: 100,
                invId: 1,
                description: 'description',
                culture: Culture::ru,
            ),
        );
        $expected = 'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=robo-demo&OutSum=100.00'
            . '&Description=description&SignatureValue=8ca1d1c1a6f9353bebe5b087697ba797&InvId=1'
            . '&Culture=ru&Encoding=utf-8&IsTest=1';
        $this->assertEquals($expected, $returnUrl);

        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
            isTest: false,
            hashAlgo: 'md5',
        );
        $returnUrl = $robokassa->getPaymentUrl(
            new InvoiceOptions(
                outSum: "10",
                invId: null,
                description: 'Description 2',
                outSumCurrency: OutSumCurrency::USD,
                userIP: '127.0.0.1',
                culture: Culture::en,
            ),
        );
        $expected = 'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=robo-demo&OutSum=10.00'
            . '&Description=Description+2&SignatureValue=9113fcc7a218deb0b5bc1c9c8e28c513&Culture=en'
            . '&Encoding=utf-8&OutSumCurrency=USD&UserIp=127.0.0.1';
        $this->assertEquals($expected, $returnUrl);
    }

    public function testPaymentUrlNoInvId(): void
    {
        $wrongInvoiceOptionsArguments = [
            'outSum' => 100,
            'description' => 'Description',
            'culture' => 'en',
        ];
        $this->expectException(ArgumentCountError::class);
        new InvoiceOptions(...$wrongInvoiceOptionsArguments);
    }

    public function testSignature(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
            isTest: true,
            hashAlgo: 'md5',
        );

        $signatureValue = md5('10:1:password#2');
        $this->assertEquals('f38a17bc0db36343789587e4e5abf33c', $signatureValue);
        $this->assertTrue(
            $robokassa->checkSignature(
                new ResultOptions(
                    outSum: 10,
                    invId: 1,
                    signatureValue: $signatureValue
                ),
            )
        );

        $signatureValue = md5('10::password#2');
        $this->assertEquals('56aad7334a473638781b4946998c113e', $signatureValue);
        $this->assertTrue(
            $robokassa->checkSignature(
                new ResultOptions(
                    outSum: 10,
                    invId: null,
                    signatureValue: $signatureValue
                ),
            )
        );
    }

    public function testSignatureAlgo(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
            hashAlgo: 'sha256',
        );

        $signatureValue = hash('sha256', '10:1:password#2');
        $this->assertEquals('6229bb8aa8f4e431fc43022f2847ae3296a9d7fe649b97cb65755abc90d15f0c', $signatureValue);
        $this->assertTrue(
            $robokassa->checkSignature(
                new ResultOptions(outSum: 10, invId: 1, signatureValue: $signatureValue),
            )
        );
    }

    public function testSignatureUserParams(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
            isTest: true,
            hashAlgo: 'md5',
        );

        $signatureValue = md5('10:1:password#2:shp_email=user@example.com:shp_user_id=1');
        $this->assertEquals('9c4b932169d6b683e2b76e31bae35aeb', $signatureValue);
        $this->assertTrue(
            $robokassa->checkSignature(
                new ResultOptions(
                    outSum: 10,
                    invId: 1,
                    signatureValue: $signatureValue,
                    userParameters: [
                        'shp_user_id' => 1,
                        'shp_email' => 'user@example.com',
                    ],
                ),
            )
        );
    }

    public function testUserParameters(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
        );

        $returnUrl = $robokassa->getPaymentUrl(
            new InvoiceOptions(
                outSum: 10.00,
                invId: 1,
                description: 'Description',
                userParameters: [
                    'user_id' => 1,
                    'email' => 'user@example.com',
                ],
            ),
        );
        $expected = 'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=robo-demo&OutSum=10.00'
            . '&Description=Description&SignatureValue=b98027823a17ee5be83c767f18315400&InvId=1&Encoding=utf-8'
            . '&shp_email=user%40example.com&shp_user_id=1';
        $this->assertEquals(
            $expected,
            $returnUrl
        );
    }
}
