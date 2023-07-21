robokassa-api
==============
[![Latest Stable Version](http://poser.pugx.org/netfantom/robokassa-api/v)](https://packagist.org/packages/netfantom/robokassa-api)
[![Total Downloads](http://poser.pugx.org/netfantom/robokassa-api/downloads)](https://packagist.org/packages/netfantom/robokassa-api)
[![License](http://poser.pugx.org/netfantom/robokassa-api/license)](https://packagist.org/packages/netfantom/robokassa-api)
[![PHP Version Require](http://poser.pugx.org/netfantom/robokassa-api/require/php)](https://packagist.org/packages/netfantom/robokassa-api)
[![codecov](https://codecov.io/gh/igor-netFantom/robokassa-api/branch/main/graph/badge.svg?token=C9GXYO8JVB)](https://codecov.io/gh/igor-netFantom/robokassa-api)
[![type-coverage](https://shepherd.dev/github/igor-netFantom/robokassa-api/coverage.svg)](https://shepherd.dev/github/igor-netfantom/robokassa-api)
[![psalm-level](https://shepherd.dev/github/igor-netFantom/robokassa-api/level.svg)](https://shepherd.dev/github/igor-netfantom/robokassa-api)

Данный модуль содержит базовый набор методов для взаимодействия с Робокассой.

Для работы требуется `PHP 8.1+` и любой `PSR-18 HTTP Client`

Для настройки модуля, формирования запросов и обработки ответов используются объекты
`InvoiceOptions`, `SecondReceiptOptions` и `ResultOptions`

## Установка с помощью Composer

~~~
composer require igor-netfantom/robokassa-api:@dev
~~~

В модуле используется
[php-http/discovery](https://packagist.org/packages/php-http/discovery),
который автоматически найдет подходящий `PSR-18 HTTP Client` из уже установленных
или, если ни один подходящий не установлен, предложит установку по спискам:

- [psr/http-client-implementation](https://packagist.org/providers/psr/http-client-implementation)
- и [psr/http-factory-implementation](https://packagist.org/providers/psr/http-factory-implementation)
  ( включая [psr/http-message-implementation](https://packagist.org/providers/psr/http-message-implementation) )

При необходимости в настройках модуля можно указать какой `PSR-18 HTTP Client` следует использовать.

## Настройка модуля

```php
$robokassaApi= new \netFantom\RobokassaApi\RobokassaApi(
    merchantLogin: 'robo-demo',
    password1: 'password_1',
    password2: 'password_2',
    isTest: false,
    psr18Client: new \Http\Discovery\Psr18Client(),  // необязательно
);
```

`PSR-18 HTTP Client` клиент используется для отправки некоторых запросов<br>
(формирование второго чека, получение статуса чека, отправка СМС)

`RobokassaApi::psr18Client` указывать необязательно.

При его отсутствии будет произведена попытка автоматического поиска
подходящего уже установленного `PSR-18` HTTP клиента.

## Объекты

### Объект для формирования оплаты счета

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

### Объект для формирования второго чека

```php
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
);
```

### Объект для обработки результата `отправки второго чека`

```php
 
/** @var \netFantom\RobokassaApi\Options\SecondReceiptOptions $secondReceiptOptions */
/** @var \netFantom\RobokassaApi\RobokassaApi $robokassaApi */
$response = $robokassaApi->secondReceiptAttach($secondReceiptOptions);

$receiptAttachResponse = $robokassaApi->getReceiptAttachResponseFromHttpResponse($response);

$receiptAttachResponse->ResultCode; // Статус получения данных от Клиента.
$receiptAttachResponse->ResultDescription; // Описание результата обработки чека.
$receiptAttachResponse->OpKey; // Идентификатор операции.
```

### Объект для обработки результата `получения статуса чека`

```php
/** @var \netFantom\RobokassaApi\RobokassaApi $robokassaApi */
$response = $robokassaApi->receiptStatus(new \netFantom\RobokassaApi\Options\ReceiptStatusOptions(
    id: 34,
));

$receiptStatusResponse = $robokassaApi->getReceiptStatusResponseFromHttpResponse($response);

$receiptStatusResponse->Code; // Статус регистрации чека.
$receiptStatusResponse->Description; // Описание результата формирования чека
$receiptStatusResponse->Statuses;
$receiptStatusResponse->FnNumber; // Номер ФН
$receiptStatusResponse->FiscalDocumentNumber; // Фискальный номер документа
$receiptStatusResponse->FiscalDocumentAttribute; // Фискальный признак документа
$receiptStatusResponse->FiscalDate; // Дата и время формирования фискального чека
$receiptStatusResponse->FiscalType;
```

### Объект для обработки результата `отправки СМС`

```php
/** @var \netFantom\RobokassaApi\RobokassaApi $robokassaApi */
$response = $robokassaApi->sendSms(89991234567, 'All work fine!');
$smsSendResponse = $robokassaApi->getSmsSendResponseFromHttpResponse($response);

$smsSendResponse->result; // Значение логического типа, указывающее на общий успех или неуспех обработки запроса.
$smsSendResponse->errorCode; // Целочисленное значение кода ошибки обработки
$smsSendResponse->errorMessage; // Текстовое описание возникшей в процессе обработки запроса ошибки.
$smsSendResponse->count; // Целочисленное значение, указывающее на количество SMS, доступное после этого запроса
                               // (данное значение заполняется только в случае успешного исполнения запроса)
```

### Объект для получения и обработки ответа Робокассы об оплате

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

#### Получение URL для оплаты счета с указанными параметрами

```php
/**
 * (GET запрос длиной более 2083 символов может не работать,
 * поэтому счет на оплату с чеком Receipt рекомендуется
 * отправлять, формирую форму с параметрами RobokassaApi::getPaymentParameters()
 * и методом отправки POST)
 */
public function getPaymentUrl(InvoiceOptions $invoiceOptions): string
```

#### Получает параметры платежа для передачи в Робокассу

(для формирования `ФОРМЫ` оплаты с методом передачи POST запросом)

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

#### Отправка второго чека

Для отправки используется любой PSR-18 Http Client.<br><br>
Результат отправки можно узнать:

- или напрямую из полученного Psr\Http\Message\ResponseInterface
- или преобразовав ответ в
  [ReceiptAttachResponse](#объект-для-обработки-результата-отправки-второго-чека)
  с помощью RobokassaApi::getReceiptAttachResponseFromHttpResponse()

```php
/**
 * @param SecondReceiptOptions $secondReceiptOptions
 * @return ResponseInterface
 * @throws ClientExceptionInterface
 */
public function secondReceiptAttach(SecondReceiptOptions $secondReceiptOptions): ResponseInterface
```

Пример отправки второго чека

```php
$items = [
     new Item(
         // ...
     ),
];
$response = $robokassa->secondReceiptAttach(new SecondReceiptOptions(
     // ...
     items: $items,
     vats: RobokassaApi::getVatsFromItems($items),
     // ...
));
$receiptAttachResponse = $robokassa->getReceiptAttachResponseFromHttpResponse($response);
```

#### Получение статуса чека

Для отправки запроса используется любой PSR-18 Http Client.<br><br>
Результат отправки запроса можно узнать:

- или напрямую из полученного Psr\Http\Message\ResponseInterface
- или преобразовав ответ в
  [ReceiptStatusResponse](#объект-для-обработки-результата-получения-статуса-чека)
  с помощью RobokassaApi::getReceiptStatusResponseFromHttpResponse()

```php
/**
 * @param ReceiptStatusOptions $secondReceiptOptions
 * @return ResponseInterface
 * @throws ClientExceptionInterface
 */
public function receiptStatus(ReceiptStatusOptions $secondReceiptOptions): ResponseInterface
```

Пример отправки запроса на получение статуса чека

```php
$response = $robokassa->receiptStatus(new ReceiptStatusOptions(
    id: 34,
));
$receiptStatusResponse = $robokassa->getReceiptStatusResponseFromHttpResponse($response);
```

#### Готовые параметры для *самостоятельного формирования* запроса на `$robokassaApi->smsUrl` для отправки СМС

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

#### Отправка СМС

Для отправки используется любой PSR-18 Http Client.<br><br>
Результат отправки можно узнать:

- или напрямую из полученного Psr\Http\Message\ResponseInterface
- или преобразовав ответ в [SmsSendResponse](#объект-для-обработки-результата-отправки-смс) с помощью
  RobokassaApi::getSmsSendResponseFromHttpResponse()

```php
/** * 
 * @param int $phone Номер телефона в международном формате без символа «+». Например, 8999*******.
 * @param string $message строка в кодировке UTF-8 длиной до 128 символов, содержащая текст отправляемого SMS.
 * @return ResponseInterface
 * @throws ClientExceptionInterface
 */
public function sendSms(int $phone, string $message): ResponseInterface
```

Пример отправки СМС

```php
$response = $robokassaApi->sendSms(89991234567, 'All work fine!');
$smsSendResponse = $robokassaApi->getSmsSendResponseFromHttpResponse($response);
```

#### Формирование и кодирование подписи `SignatureValue`

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

#### Получение параметров результата `ResultOptions` от Робокассы<br> из массива `GET` или `POST` параметров HTTP запроса

```php
public static function getResultOptionsFromRequestArray(array $requestParameters): ResultOptions
// $resultOptions = \netFantom\RobokassaApi\RobokassaApi::getResultOptionsFromRequestArray($_GET);
// $resultOptions = \netFantom\RobokassaApi\RobokassaApi::getResultOptionsFromRequestArray($_POST);
```

#### Получение пользовательских параметров (`shp_...`) из массива `GET` или `POST` параметров

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

#### Проверка корректности подписи параметров результата `ResultOptions` от Робокассы

```php
public function checkSignature(ResultOptions $resultOptions): bool
```
