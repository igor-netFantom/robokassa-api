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
`InvoiceOptions`, `SecondReceiptOptions` и `InvoicePayResult`

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

https://docs.robokassa.ru/pay-interface/

```php
$robokassa = new \netFantom\RobokassaApi\RobokassaApi(
    merchantLogin: 'robo-demo',
    password1: 'password_1',
    password2: 'password_2',
    isTest: false,
    psr18Client: new \Http\Discovery\Psr18Client(),  // необязательно
);
```

`RobokassaApi::psr18Client` указывать необязательно.

`PSR-18 HTTP Client` используется для отправки некоторых запросов<br>
(формирование второго чека, получение статуса чека, отправка СМС)

Его можно изменить или получить после создания и настройки объекта RobokassaApi.

При его отсутствии будет произведена попытка автоматического поиска
подходящего уже установленного `PSR-18 HTTP Client` с помощью
[php-http/discovery](https://packagist.org/packages/php-http/discovery)

```php
$robokassa = new \netFantom\RobokassaApi\RobokassaApi(
    // ...
    psr18Client: new \Http\Discovery\Psr18Client(),  // вручную указываем свой psr18Client 
    // или
    psr18Client: null,  // разрешить автоматический поиск подходящего psr18Client
);

$psr18Client = $robokassa->getPsr18Client(); // получение указанного psr18Client или поиск подходящего

$robokassa->setPsr18Client(new \Http\Discovery\Psr18Client()); // изменение psr18Client
```

## Методы

- [Получение параметров платежа для передачи в Робокассу](#получение-параметров-платежа-для-передачи-в-робокассу)
- [Получение URL для оплаты счета с указанными параметрами](#получение-url-для-оплаты-счета-с-указанными-параметрами)
- [Отправка второго чека](#отправка-второго-чека)
- [Получение статуса чека](#получение-статуса-чека)
- [Отправка СМС](#отправка-смс)
- [Получение результата оплаты счета от Робокассы](#получение-результата-оплаты-счета-от-робокассы)
- [Вспомогательные методы](#вспомогательные-методы)
  - [Параметры для отправки СМС](#получение-данных-для-отправки-смс)
  - [Формирование данных для отправки второго чека или проверки статуса чека](#получение-данных-для-отправки-второго-чека-или-проверки-статуса-чека)

### Получение параметров платежа для передачи в Робокассу

(для формирования `ФОРМЫ` оплаты с методом передачи POST запросом)

https://docs.robokassa.ru/script-parameters/ <br>
https://docs.robokassa.ru/fiscalization/

```php
use netFantom\RobokassaApi\Options\InvoiceOptions;
use netFantom\RobokassaApi\Params\Option\{Culture,OutSumCurrency,Receipt};
use netFantom\RobokassaApi\Params\Item\{PaymentMethod,PaymentObject};
use netFantom\RobokassaApi\Params\Receipt\{Item,Sno,Tax};
use netFantom\RobokassaApi\RobokassaApi;

$robokassa = new RobokassaApi(
    // ...
);
$paymentParametersArray = $robokassa->getPaymentParameters(new InvoiceOptions(
    outSum: 999.99,
    invId: 1,
    description: 'Description',
    receipt: new Receipt(
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
                sum: 450,
                tax: Tax::vat10,
                payment_method: PaymentMethod::full_payment,
                payment_object: PaymentObject::service,
                cost: 150,
                nomenclature_code: '04620034587217',
            ),
        ],
        sno: Sno::osn
    ),
    expirationDate: (new DateTimeImmutable())->add(new DateInterval('PT48H')),
    email: 'user@email.com',
    outSumCurrency: OutSumCurrency::USD,
    userIP: '127.0.0.1',
    incCurrLabel: null,
    userParameters: [
        'user_id' => '123',
        'parameter2' => 'parameter2_value',
        // ...
    ],
    encoding: 'utf-8',
    culture: Culture::ru,
));
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

### Получение URL для оплаты счета с указанными параметрами

(GET запрос длиной более 2083 символов может не работать,
поэтому счет на оплату с чеком Receipt рекомендуется
отправлять, формирую форму с параметрами RobokassaApi::getPaymentParameters()
и методом отправки POST)

```php
use netFantom\RobokassaApi\Options\InvoiceOptions;
use netFantom\RobokassaApi\RobokassaApi;

$robokassa = new RobokassaApi(
    // ...
);
$invoiceOptions = new InvoiceOptions(
    // ...
);
$url = $robokassa->getPaymentUrl($invoiceOptions);
```

### Отправка второго чека

Для отправки используется любой PSR-18 Http Client.<br><br>
Результат отправки можно узнать:

- или напрямую из полученного Psr\Http\Message\ResponseInterface
- или преобразовав ответ в `ReceiptAttachResult` методом `getReceiptAttachResult()`

https://docs.robokassa.ru/second-check/

```php
use Http\Discovery\Psr18Client;
use netFantom\RobokassaApi\Options\SecondReceiptOptions;
use netFantom\RobokassaApi\Params\Item\{PaymentMethod, PaymentObject};
use netFantom\RobokassaApi\Params\Receipt\{Client, Item, Payment, Sno, Tax};
use netFantom\RobokassaApi\Results\ReceiptAttachResult;
use netFantom\RobokassaApi\RobokassaApi;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

$robokassa = new RobokassaApi(
    // ...
);
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
/** @var ResponseInterface $response */
$response = $robokassa->sendSecondReceiptAttach(new SecondReceiptOptions(
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
));

/** @var ReceiptAttachResult $receiptAttachResult */
$receiptAttachResult = $robokassa->getReceiptAttachResult($response);

$receiptAttachResult->ResultCode; // Статус получения данных от Клиента.
$receiptAttachResult->ResultDescription; // Описание результата обработки чека.
$receiptAttachResult->OpKey; // Идентификатор операции.

// ИЛИ можно сформировать запрос для самостоятельной отправки PSR-18 HTTP клиентом:
/** @var RequestInterface $request */
$request = $robokassa->secondReceiptAttachRequest(new SecondReceiptOptions(
    // ...
));
$response = (new Psr18Client())->sendRequest($request);
$receiptAttachResult = $robokassa->getReceiptAttachResult($response);
```

### Получение статуса чека

Для отправки запроса используется любой PSR-18 Http Client.<br><br>
Результат отправки запроса можно узнать:

- или напрямую из полученного `Psr\Http\Message\ResponseInterface`
- или преобразовав ответ в `ReceiptStatusResult` методом `getReceiptStatusResult()`

https://docs.robokassa.ru/second-check/

```php
use Http\Discovery\Psr18Client;
use netFantom\RobokassaApi\Options\ReceiptStatusOptions;
use netFantom\RobokassaApi\Results\ReceiptStatusResult;
use netFantom\RobokassaApi\RobokassaApi;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

$robokassa = new RobokassaApi(
// ...
);
/** @var ResponseInterface $response */
$response = $robokassa->getReceiptStatus(new ReceiptStatusOptions(
    id: 34,
));

/** @var ReceiptStatusResult $receiptStatusResult */
$receiptStatusResult = $robokassa->getReceiptStatusResult($response);

$receiptStatusResult->Code; // Статус регистрации чека.
$receiptStatusResult->Description; // Описание результата формирования чека
$receiptStatusResult->Statuses;
$receiptStatusResult->FnNumber; // Номер ФН
$receiptStatusResult->FiscalDocumentNumber; // Фискальный номер документа
$receiptStatusResult->FiscalDocumentAttribute; // Фискальный признак документа
$receiptStatusResult->FiscalDate; // Дата и время формирования фискального чека
$receiptStatusResult->FiscalType;


// ИЛИ можно сформировать запрос для самостоятельной отправки PSR-18 HTTP клиентом:
/** @var RequestInterface $request */
$request = $robokassa->receiptStatusRequest(new ReceiptStatusOptions(
    id: 34,
));
$response = (new Psr18Client())->sendRequest($request);
$receiptStatusResult = $robokassa->getReceiptStatusResult($response);
```

### Отправка СМС

Для отправки используется любой PSR-18 Http Client.<br><br>
Результат отправки можно узнать:

- или напрямую из полученного Psr\Http\Message\ResponseInterface
- или преобразовав ответ в `SmsSendResult` методом `getSmsSendResult()`

https://docs.robokassa.ru/sms/

```php
use Http\Discovery\Psr18Client;
use netFantom\RobokassaApi\Results\SmsSendResult;
use netFantom\RobokassaApi\RobokassaApi;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

$robokassa = new RobokassaApi(
    // ...
);

/** @var int $phone Номер телефона в международном формате без символа «+». Например, 8999*******. */
$phone = 89991234567;

/** @var string $message Строка в кодировке UTF-8 длиной до 128 символов, содержащая текст отправляемого SMS. */
$message = 'All work fine!';

/** @var ResponseInterface $response */
$response = $robokassa->sendSms($phone, $message);

/** @var SmsSendResult $smsSendResult */
$smsSendResult = $robokassa->getSmsSendResult($response);

$smsSendResult->result; // Значение логического типа, указывающее на общий успех или неуспех обработки запроса.
$smsSendResult->errorCode; // Целочисленное значение кода ошибки обработки
$smsSendResult->errorMessage; // Текстовое описание возникшей в процессе обработки запроса ошибки.
$smsSendResult->count; // Целочисленное значение, указывающее на количество SMS, доступное после этого запроса
                       // (данное значение заполняется только в случае успешного исполнения запроса)


// ИЛИ можно сформировать запрос для самостоятельной отправки PSR-18 HTTP клиентом:
/** @var RequestInterface $request */
$request = $robokassa->smsRequest(
    // ...
);
$response = (new Psr18Client())->sendRequest($request);
$smsSendResult = $robokassa->getSmsSendResult($response);
```

### Получение результата оплаты счета от Робокассы

Результат формируется из массива `GET` или `POST` параметров HTTP запроса полученного от Робкассы

https://docs.robokassa.ru/pay-interface/

```php
use netFantom\RobokassaApi\Results\InvoicePayResult;
use netFantom\RobokassaApi\RobokassaApi;

$robokassa = new RobokassaApi(
    // ...
);

/** @var InvoicePayResult $invoicePayResult */
$invoicePayResult = RobokassaApi::getInvoicePayResultFromRequestArray($_POST);

/** Проверка подписи результата оплаты счета */
if (!$robokassa->checkSignature($invoicePayResult)) {
    throw new RuntimeException('Bad signature');
}

$invoicePayResult->invId; // номер счета
$invoicePayResult->outSum; // сумма оплаты
$invoicePayResult->signatureValue; // подпись
$invoicePayResult->userParameters; // дополнительные пользовательские параметры
```

### Вспомогательные методы

#### Получение данных для отправки СМС

*(данный метод может пригодиться для самостоятельного формирования запроса)*

https://docs.robokassa.ru/sms/

```php
use netFantom\RobokassaApi\RobokassaApi;

$robokassa = new RobokassaApi(
    // ...
);

/** @var int $phone Номер телефона в международном формате без символа «+». Например, 8999*******. */
$phone = 89991234567;

/** @var string $message строка в кодировке UTF-8 длиной до 128 символов, содержащая текст отправляемого SMS. */
$message = 'All work fine!';

/** @var array $parameters */
$parameters = $robokassa->getSendSmsData($phone, $message);
// [
//    'login' => ...,
//    'phone' => $phone,
//    'message' => $message,
//    'signature' => ...
// ]
```

#### Получение данных для отправки второго чека или проверки статуса чека

*(данный метод может пригодиться для самостоятельного формирования запроса)*

Данные подписаны ключом робокассы и кодированы в Base64.

https://docs.robokassa.ru/second-check/

```php
use netFantom\RobokassaApi\Options\SecondReceiptOptions;
use netFantom\RobokassaApi\RobokassaApi;

$robokassa = new RobokassaApi(
    // ...
);

$secondReceiptData = $robokassa->getBase64SignedPostData(new SecondReceiptOptions(
    // ...
));

// eyJtZXJjaGFudElkIjogInJvYm9rYXNzYV9zZWxsIiwiaWQiOiAiMTQiLCJvcmlnaW5JZCI6ICIxMyIsIm9wZXJh
// dGlvbiI6ICJzZWxsIiwKInNubyI6ICJvc24iLCJ1cmwiOiAiaHR0cHM6Ly93d3cucm9ib2thc3NhLnJ1LyIsInRv
// dGFsIjogMTAwLCJpdGVtcyI6IFt7Im5hbWUiOiAi0KLQvtCy0LDRgCIsInF1YW50aXR5IjogMSwic3VtIjogMTAw
// LCJ0YXgiOiAibm9uZSIsInBheW1lbnRfbWV0aG9kIjogImZ1bGxfcGF5bWVudCIsInBheW1lbnRfb2JqZWN0Ijog
// ImNvbW1vZGl0eSJ9XSwiY2xpZW50IjogeyJlbWFpbCI6ICJ0ZXN0QHRlc3QucnUiLCJwaG9uZSI6ICI3MTIzNDU2
// Nzg5MCJ9LCJwYXltZW50cyI6IFt7InR5cGUiOiAyLCJzdW0iOiAxMDB9XSwidmF0cyI6IFt7InR5cGUiOiAibm9u
// ZSIsInN1bSI6IDB9XX0.MzAwMTU4NjIwMTE0M2RhNDIzMjg5NmM0NDI0NGJkYmI
```