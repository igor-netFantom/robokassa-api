<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace tests;

use ArgumentCountError;
use DateTimeImmutable;
use DateTimeZone;
use Http\Discovery\Psr18Client;
use netFantom\RobokassaApi\Exceptions\{MissingRequestFactory, MissingStreamFactory, TooLongSmsMessageException};
use netFantom\RobokassaApi\Options\{InvoiceOptions, ReceiptStatusOptions, SecondReceiptOptions};
use netFantom\RobokassaApi\Params\Item\{PaymentMethod, PaymentObject};
use netFantom\RobokassaApi\Params\Option\{Culture, OutSumCurrency, Receipt};
use netFantom\RobokassaApi\Params\Receipt\{Item, Sno, Tax};
use netFantom\RobokassaApi\Results\{InvoicePayResult, ReceiptAttachResult, ReceiptStatusResult, SmsSendResult};
use netFantom\RobokassaApi\RobokassaApi;
use PHPUnit\Framework\TestCase as Unit;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

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
                expirationDate: (new DateTimeImmutable('2030-01-01 10:20:30', new DateTimeZone('+3'))),
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

    public function testGetInvoicePayResultFromRequestArray(): void
    {
        $signatureValue = md5('100:1:password_2');
        $email = 'test@exmaple.com';

        $_POST['OutSum'] = 100;
        $_POST['InvId'] = 1;
        $_POST['SignatureValue'] = $signatureValue;
        $_POST['shp_email'] = $email;

        $expectedInvoicePayResult = new InvoicePayResult(
            outSum: 100,
            invId: 1,
            signatureValue: $signatureValue,
            userParameters: [
                'email' => 'test@exmaple.com'
            ]
        );

        $invoicePayResult = RobokassaApi::getInvoicePayResultFromRequestArray($_POST);

        $this->assertEquals($expectedInvoicePayResult, $invoicePayResult);
    }

    public function testGetPaymentParameters(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
        );

        $invoiceOptions = new InvoiceOptions(
            outSum: 100,
            invId: 999,
            description: "Description\"'><b>",
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
                        name: "Название товара 2\"'><b>",
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
        );
        $paymentParameters = $robokassa->getPaymentParameters(
            $invoiceOptions,
        );

        $expectedPaymentParameters = [
            'MerchantLogin' => 'robo-demo',
            'OutSum' => '100.00',
            'Description' => "Description\"'><b>",
            'SignatureValue' => '7c6a95094fa5c3e5e2baf83c8817e8c0',
            'IncCurrLabel' => null,
            'InvId' => '999',
            'Culture' => 'en',
            'Encoding' => 'utf-8',
            'Email' => null,
            'ExpirationDate' => null,
            'OutSumCurrency' => null,
            'UserIp' => null,
            'Receipt' => '{"items":['
                . '{"sum":"100.00","name":"Название товара 1 (50%)","quantity":1,"tax":"vat10",'
                . '"payment_method":"full_payment","payment_object":"commodity"},'
                . '{"sum":"450.00","name":"Название товара 2\"\'><b>","quantity":3,"tax":"vat120",'
                . '"payment_method":"full_payment","payment_object":"excise","nomenclature_code":"04620034587217"}'
                . '],"sno":"osn"}',
            'IsTest' => null,
        ];
        $this->assertEquals($expectedPaymentParameters, $paymentParameters);

        $paymentParametersAsJson = $robokassa->getPaymentParametersAsJson($invoiceOptions);
        $expectedPaymentParametersAsJson = '{"MerchantLogin":"robo-demo","OutSum":"100.00","Description":"Description\"\'><b>",'
            . '"SignatureValue":"7c6a95094fa5c3e5e2baf83c8817e8c0","IncCurrLabel":null,"InvId":"999","Culture":"en",'
            . '"Encoding":"utf-8","Email":null,"ExpirationDate":null,"OutSumCurrency":null,"UserIp":null,'
            . '"Receipt":"{\"items\":['
            . '{\"sum\":\"100.00\",\"name\":\"Название товара 1 (50%)\",\"quantity\":1,\"tax\":\"vat10\",'
            . '\"payment_method\":\"full_payment\",\"payment_object\":\"commodity\"},'
            . '{\\"sum\\":\\"450.00\\",\\"name\\":\\"Название товара 2\\\\\\"\'><b>\\",\\"quantity\\":3,\\"tax\\":\\"vat120\\",'
            . '\"payment_method\":\"full_payment\",\"payment_object\":\"excise\",\"nomenclature_code\":\"04620034587217\"}],'
            . '\"sno\":\"osn\"}","IsTest":null}';
        $this->assertEquals($expectedPaymentParametersAsJson, $paymentParametersAsJson);

        $decodedJson = json_decode($paymentParametersAsJson, true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($expectedPaymentParameters, $decodedJson);
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

    public function testReceiptStatus(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robokassa_state',
            password1: 'robokassatest',
            password2: 'password#2',
        );

        $secondReceiptOptions = new ReceiptStatusOptions(
            id: 34,
        );
        $response = $robokassa->getReceiptStatus($secondReceiptOptions);

        $this->assertEquals(200, $response->getStatusCode());
        $expectedBodyContent = '{"Code":"1000","Description":"Error","Statuses":null}';
        $this->assertEquals($expectedBodyContent, (string)$response->getBody());

        $expectedReceiptStatusResult = new ReceiptStatusResult(
            Code: "1000",
            Description: "Error",
            Statuses: null
        );
        $receiptStatusResult = $robokassa->getReceiptStatusResult($response);
        $this->assertEquals($expectedReceiptStatusResult, $receiptStatusResult);
    }

    public function testSendSecondReceiptAttach(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robokassa_sell',
            password1: 'robokassatest',
            password2: 'password#2',
        );

        $secondReceiptOptions = new SecondReceiptOptions(
            id: 14,
            originId: 13,
            url: 'https://www.robokassa.ru/',
            total: 100,
            items: [],
            vats: [],
        );
        $response = $robokassa->sendSecondReceiptAttach($secondReceiptOptions);

        $this->assertEquals(200, $response->getStatusCode());
        $expectedBodyContent = '{"ResultCode":"1000",'
            . '"ResultDescription":'
            . '"Exception of type \u0027Robox.Merchant.Util.Exceptions.MerchantNotFoundException\u0027 was thrown.",'
            . '"OpKey":null}';
        $this->assertEquals($expectedBodyContent, (string)$response->getBody());

        $expectedReceiptAttachResult = new ReceiptAttachResult(
            ResultCode: "1000",
            ResultDescription: "Exception of type 'Robox.Merchant.Util.Exceptions.MerchantNotFoundException' was thrown.",
            OpKey: null
        );
        $receiptAttachResult = $robokassa->getReceiptAttachResult($response);
        $this->assertEquals($expectedReceiptAttachResult, $receiptAttachResult);
    }

    public function testSendSms(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'demo_merchant',
            password1: 'Password#1',
            password2: 'password#2',
        );
        $smsRequestData = $robokassa->getSendSmsData(89991234567, 'All work fine!');
        $expectedSmsRequestData = [
            'login' => 'demo_merchant',
            'phone' => 89991234567,
            'message' => 'All work fine!',
            'signature' => 'a6243e85f7cf7ca3627aa1f506619ef3',
        ];
        $this->assertEquals($expectedSmsRequestData, $smsRequestData);

        $response = $robokassa->sendSms(89991234567, 'All work fine!');
        $this->assertEquals(200, $response->getStatusCode());
        $expectedBodyContent = '{"result":false,"errorCode":2,"errorMessage":"partner not found"}';
        $this->assertEquals($expectedBodyContent, (string)$response->getBody());

        $expectedSmsSendResult = new SmsSendResult(
            result: false,
            errorCode: 2,
            errorMessage: 'partner not found',
        );
        $smsSendResult = $robokassa->getSmsSendResult($response);
        $this->assertEquals($expectedSmsSendResult, $smsSendResult);
    }

    public function testSetPsr18Client(): void
    {
        $robokassaApi = new RobokassaApi(
            merchantLogin: 'robokassa_state',
            password1: 'robokassatest',
            password2: 'password#2',
        );
        $psr18Client = new Psr18Client();
        $robokassaApi->setPsr18Client($psr18Client);
        $this->assertSame($psr18Client, $robokassaApi->getPsr18Client());
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
                new InvoicePayResult(
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
                new InvoicePayResult(
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
                new InvoicePayResult(outSum: 10, invId: 1, signatureValue: $signatureValue),
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
                new InvoicePayResult(
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

    public function testWrongPsr18ClientWithoutRequestFactory(): void
    {
        $this->expectException(MissingRequestFactory::class);

        /** @noinspection PhpParamsInspection */
        new RobokassaApi(
            merchantLogin: 'robokassa_state',
            password1: 'robokassatest',
            password2: 'password#2',
            psr18Client: $this->createMockForIntersectionOfInterfaces([
                ClientInterface::class,
                StreamFactoryInterface::class,
            ]),
        );
    }

    public function testWrongPsr18ClientWithoutStreamFactory(): void
    {
        $this->expectException(MissingStreamFactory::class);

        /** @noinspection PhpParamsInspection */
        new RobokassaApi(
            merchantLogin: 'robokassa_state',
            password1: 'robokassatest',
            password2: 'password#2',
            psr18Client: $this->createMockForIntersectionOfInterfaces([
                ClientInterface::class,
                RequestFactoryInterface::class,
            ]),
        );
    }

    public function testgetSmsParameters(): void
    {
        $robokassa = new RobokassaApi(
            merchantLogin: 'robo-demo',
            password1: 'password#1',
            password2: 'password#2',
        );

        $expectedParameters = [
            'login' => 'robo-demo',
            'phone' => 70001234567,
            'message' => 'message text',
            'signature' => '745a08955b089f7d4a64a24e6ba37611',
        ];
        $parameters = $robokassa->getSendSmsData(70001234567, 'message text');

        $this->assertEquals($expectedParameters, $parameters);

        $robokassa->getSendSmsData(70001234567, str_repeat('x', 128));

        try {
            $robokassa->getSendSmsData(70001234567, str_repeat('x', 129));
            $this->fail('Expect ' . TooLongSmsMessageException::class);
        } catch (TooLongSmsMessageException) {
        }
    }
}
