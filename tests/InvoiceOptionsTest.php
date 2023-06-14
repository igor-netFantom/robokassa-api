<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace tests;

use DateTime;
use DateTimeZone;
use netFantom\RobokassaApi\Options\InvoiceOptions;
use netFantom\RobokassaApi\RobokassaApi;
use PHPUnit\Framework\TestCase as Unit;

/**
 * @group robokassa
 * @group robokassaApi
 */
class InvoiceOptionsTest extends Unit
{
    public function testExpirationDate(): void
    {
        $invoiceOptions = new InvoiceOptions(
            outSum: 10.00,
            invId: 1,
            description: 'Description',
            expirationDate: (new DateTime('2030-01-01 10:20:30', new DateTimeZone('+3'))),
        );
        $this->assertEquals('2030-01-01T10:20:30.0000000+03:00', $invoiceOptions->expirationDate);
    }

    public function testParameters(): void
    {
        $invoiceOptions = new InvoiceOptions(
            outSum: 100,
            invId: 1,
            description: 'test',
            email: 'test@example.org',
            userParameters: [
                'email' => 'user@example.com',
                'user_id' => 1,
                'shp_wrong' => 'error',
            ],
        );

        $this->assertEquals(
            '{"email":"user@example.com","user_id":1,"shp_wrong":"error"}',
            json_encode($invoiceOptions->userParameters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );

        $this->assertEquals(
            '{"shp_email":"user@example.com","shp_shp_wrong":"error","shp_user_id":1}',
            json_encode($invoiceOptions->getFormattedUserParameters(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );
    }

    public function testStringReceipt(): void
    {
        $jsonEncodedString = '{"items":[{"sum":"10.00","name":"Название товара 1","quantity":1,"tax":"vat10",'
            . '"payment_method":"full_payment","payment_object":"commodity"}],"sno":null}';
        $robokassaApi = new RobokassaApi(merchantLogin: 'robo-demo', password1: 'password_1', password2: 'password_2');
        $invoiceOptions = new InvoiceOptions(
            outSum: 10.00,
            invId: 1,
            description: 'Description',
            receipt: $jsonEncodedString,
        );
        $this->assertEquals($jsonEncodedString, $robokassaApi->getPaymentParameters($invoiceOptions)['Receipt']);
    }

    public function testUserParametersSort(): void
    {
        $invoiceOptions = new InvoiceOptions(
            outSum: 100,
            invId: 1,
            description: 'test',
            email: 'test@example.org',
            userParameters: [
                'b' => '4',
                'd' => '2',
                'e' => '1',
                'c' => '3',
                'a' => '5',
            ],
        );

        $this->assertEquals(
            '{"shp_a":"5","shp_b":"4","shp_c":"3","shp_d":"2","shp_e":"1"}',
            json_encode($invoiceOptions->getFormattedUserParameters(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );

        $this->assertEquals(
            '{"b":"4","d":"2","e":"1","c":"3","a":"5"}',
            json_encode($invoiceOptions->userParameters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );
    }
}
