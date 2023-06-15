robokassa-api
==============
[![Latest Stable Version](http://poser.pugx.org/netfantom/robokassa-api/v)](https://packagist.org/packages/netfantom/robokassa-api)
[![Total Downloads](http://poser.pugx.org/netfantom/robokassa-api/downloads)](https://packagist.org/packages/netfantom/robokassa-api)
[![License](http://poser.pugx.org/netfantom/robokassa-api/license)](https://packagist.org/packages/netfantom/robokassa-api)
[![PHP Version Require](http://poser.pugx.org/netfantom/robokassa-api/require/php)](https://packagist.org/packages/netfantom/robokassa-api)

Данный компонент базовый набор методов для взаимодействия с Робокассой.

Для работы требуется `PHP 8.1+`

Для настройки модуля, формирования запросов и обработки ответов используются объекты
`InvoiceOptions` и `ResultOptions`

## Установка с помощью Composer

~~~
composer require igor-netfantom/robokassa-api:@dev
~~~

## Настройка модуля

```php
$robokassaApi= new \netFantom\RobokassaApi\RobokassaApi(
    merchantLogin: 'robo-demo',
    password1: 'password_1',
    password2: 'password_2',
    isTest: false,
);
```

## Объект для формирования оплаты счета

```php
$invoiceOptions = new \netFantom\RobokassaApi\Options\InvoiceOptions(
    outSum: 999.99,
    invId: 1,
    description: 'Description',
    receipt: new \netFantom\RobokassaApi\Options\Receipt(
        items: [
            new \netFantom\RobokassaApi\Options\Item(
                name: "Название товара 1",
                quantity: 1,
                sum: 100,
                tax: \netFantom\RobokassaApi\Options\Tax::vat10,
                payment_method: \netFantom\RobokassaApi\Options\PaymentMethod::full_payment,
                payment_object: \netFantom\RobokassaApi\Options\PaymentObject::commodity,
            ),
            new \netFantom\RobokassaApi\Options\Item(
                name: "Название товара 2",
                quantity: 3,
                sum: 450,
                tax: \netFantom\RobokassaApi\Options\Tax::vat10,
                payment_method: \netFantom\RobokassaApi\Options\PaymentMethod::full_payment,
                payment_object: \netFantom\RobokassaApi\Options\PaymentObject::service,
                cost: 150,
                nomenclature_code: '04620034587217',
            ),
        ],
        sno: \netFantom\RobokassaApi\Options\Sno::osn
    ),
    expirationDate: (new \DateTime('2030-01-01 10:20:30', new \DateTimeZone('+3'))),
    email: 'user@email.com',
    outSumCurrency: \netFantom\RobokassaApi\Options\OutSumCurrency::USD,
    userIP: '127.0.0.1', 
    incCurrLabel: null,
    userParameters: [
        'user_id'=>'123',
        'parameter2'=>'parameter2_value',
        // ...
    ],
    encoding: 'utf-8',
    culture: \netFantom\RobokassaApi\Options\Culture::ru,
)
```

## Объект для получения и обработки ответа Робокассы

```php
/** @var \netFantom\RobokassaApi\Options\ResultOptions $resultOptions  */
$resultOptions=\netFantom\RobokassaApi\RobokassaApi::getResultOptionsFromRequestArray($_POST);

/** @var \netFantom\RobokassaApi\RobokassaApi $robokassaApi */
if(!$robokassaApi->checkSignature($resultOptions)) {
    throw new RuntimeException('Bad signature');
}

$resultOptions->invId; // номер счета
$resultOptions->outSum; // сумма оплаты
$resultOptions->signatureValue; // подпись
$resultOptions->userParameters; // дополнительные пользовательские параметры
```

## Методы

### Методы для оплаты и отправки других запросов

Получение URL для оплаты счета с указанными параметрами

```php
/**
 * (GET запрос длиной более 2083 символов может не работать,
 * поэтому счет на оплату с чеком Receipt рекомендуется
 * отправлять, формирую форму с параметрами RobokassaApi::getPaymentParameters()
 * и методом отправки POST)
 */
public function getPaymentUrl(InvoiceOptions $invoiceOptions): string
```

Получает параметры платежа для передачи в Робокассу (для формирования формы оплаты с методом передачи POST запросом)

```php
public function paymentParameters(InvoiceOptions $invoiceOptions): array
//[
//    'MerchantLogin' => ...,
//    'OutSum' => ...,
//    'Description' => ...,
//    'SignatureValue' => ...,
//    'IncCurrLabel' => ...,
//    'InvId' => ...,
//    'Culture' => ...,
//    'Encoding' => ...,
//    'Email' => ...,
//    'ExpirationDate' => ...,
//    'OutSumCurrency' => ...,
//    'UserIp' => ...,
//    'Receipt' => ...,
//    'IsTest' => ...,
//    'shp_...' => ...,
//    'shp_...' => ...,
//    'shp_...' => ...,
//    // ...
//]
```

Готовые параметры для формирования запроса на `$robokassaApi->smsUrl` для отправки СМС

```php
/**
 * @param int $phone Номер телефона в международном формате без символа «+». Например, 8999*******.
 * @param string $message строка в кодировке UTF-8 длиной до 128 символов, содержащая текст отправляемого SMS.
 * @return array
 */
public function getSendSmsRequestData(int $phone, string $message): array
//[
//    'login' => ...,
//    'phone' => $phone,
//    'message' => $message,
//    'signature' => ...
//]
```

Формирование и кодирование подписи `SignatureValue`

```php
/** 
 * Метода сам формирует нужный вариант подписи выкидывая null значения
 * 
 * Минимальный вариант подписи до кодирования
 * MerchantLogin:OutSum:Пароль#1
 * 
 * Максимальный вариант подписи до кодирования
 * MerchantLogin:OutSum:InvId:OutSumCurrency:UserIp:Receipt:Пароль#1:Shp_...=...:Shp_...=...
 */
public function generateSignatureForPayment(InvoiceOptions $invoiceOptions): string
```

### Методы проверки ответа Робокассы

Получение параметров результата `ResultOptions` от Робокассы
из массива `GET` или `POST` параметров HTTP запроса

```php
public static function getResultOptionsFromRequestArray(array $requestParameters): ResultOptions
// $resultOptions = \netFantom\RobokassaApi\RobokassaApi::getResultOptionsFromRequestArray($_GET);
// $resultOptions = \netFantom\RobokassaApi\RobokassaApi::getResultOptionsFromRequestArray($_POST);
```

Получение пользовательских параметров (`shp_...`) из массива `GET` или `POST` параметров

```php
// $requestParameters=[
//     // ...
//     'shp_user_id' => '123',
//     'shp_parameter2' => 'parameter2_value',
//     'shp_...' => ...,
//     // ...
// ];
public static function getUserParametersFromRequestArray(array $requestParameters): array
// [
//     'user_id'=>'123',
//     'parameter2'=>'parameter2_value',
//     '...' => ...,
// ]
```

Проверка корректности подписи параметров результата `ResultOptions` от Робокассы

```php
public function checkSignature(ResultOptions $resultOptions): bool
```
