<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi;

use JsonException;
use netFantom\RobokassaApi\Options\{InvoiceOptions, ReceiptStatusOptions, SecondReceiptOptions};
use netFantom\RobokassaApi\Params\Option\Receipt;
use netFantom\RobokassaApi\Params\Receipt\{Item, Vat};
use netFantom\RobokassaApi\Results\{InvoicePayResult, ReceiptAttachResult, ReceiptStatusResult, SmsSendResult};
use Psr\Http\Client\{ClientExceptionInterface, ClientInterface};
use Psr\Http\Message\{RequestFactoryInterface, RequestInterface, ResponseInterface, StreamFactoryInterface};

interface RobokassaApiInterface
{
    /**
     * Получение параметров результата {@see InvoicePayResult} от Робокассы
     * из массива GET или POST параметров HTTP запроса
     * @param array<string, string> $requestParameters
     * @return InvoicePayResult
     */
    public static function getInvoicePayResultFromRequestArray(array $requestParameters): InvoicePayResult;

    /**
     * Расчет величин налогов на каждый товар чека {@see Item} в формате объектов {@see Vat}
     * @param Item[] $items
     * @return Vat[]
     */
    public static function getVatsFromItems(array $items): array;

    /**
     * Проверка корректности подписи параметров результата {@see InvoicePayResult} от Робокассы
     */
    public function checkSignature(InvoicePayResult $invoicePayResult): bool;

    /**
     * Получение данных для отправки второго чека или проверки статуса чека.
     * Данные подписаны ключом робокассы и кодированы в Base64.
     *
     * *(данный метод может пригодиться для самостоятельного формирования запроса)*
     * @param SecondReceiptOptions|ReceiptStatusOptions $options
     * @return string
     * @throws JsonException
     */
    public function getBase64SignedPostData(SecondReceiptOptions|ReceiptStatusOptions $options): string;

    /**
     * Получает параметры платежа для передачи в Робокассу
     *
     * # ВНИМАНИЕ
     * В инструкции сказано, что поле Receipt на данном этапе должно быть закодирован через **urlencode() ДО отправки**,
     * то есть в GET запросе поле Receipt должно кодироваться дважды - urlencode(urlencode($Receipt)),
     * но в их демо магазине оно дополнительно **не закодировано и работает без этого**. Данный момент требует тщательной проверки.
     * {@link https://docs.robokassa.ru/fiscalization/}
     * @param InvoiceOptions $invoiceOptions
     * @return array<string, null|string>
     * @throws JsonException
     */
    public function getPaymentParameters(InvoiceOptions $invoiceOptions): array;

    /**
     * Получение URL для оплаты счета с указанными параметрами
     * (GET запрос длиной более 2083 символов может не работать,
     * поэтому счет на оплату с чеком {@see Receipt} рекомендуется
     * отправлять, формирую форму с параметрами {@see RobokassaApi::getPaymentParameters()}
     * и методом отправки POST)
     */
    public function getPaymentUrl(InvoiceOptions $invoiceOptions): string;

    /**
     * Получение указанного psr18Client или поиск подходящего
     * @return ClientInterface&RequestFactoryInterface&StreamFactoryInterface
     */
    public function getPsr18Client(): RequestFactoryInterface&ClientInterface&StreamFactoryInterface;

    /**
     * Преобразует ответ {@see RobokassaApi::sendSecondReceiptAttach()} в объект результата {@see ReceiptAttachResult}
     * @param ResponseInterface $response
     * @return ReceiptAttachResult
     */
    public function getReceiptAttachResult(ResponseInterface $response): ReceiptAttachResult;

    /**
     * Получение статуса чека
     * Для отправки запроса используется любой PSR-18 Http Client.
     *
     * ### Результат отправки запроса можно узнать:
     * - или напрямую из полученного Psr\Http\Message\ResponseInterface
     * - или преобразовав ответ в {@see ReceiptStatusResult} с помощью
     * {@see RobokassaApi::getReceiptStatusResult()}
     *
     * ### Пример:
     * ```php
     * $response = $robokassa->getReceiptStatus(new ReceiptStatusOptions(
     *     id: 34,
     * ));
     * $receiptStatusResult = $robokassa->getReceiptStatusResult($response);
     * ```
     *
     * @param ReceiptStatusOptions $secondReceiptOptions
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function getReceiptStatus(ReceiptStatusOptions $secondReceiptOptions): ResponseInterface;

    /**
     * Преобразует ответ {@see RobokassaApi::getReceiptStatus()} в объект результата {@see ReceiptStatusResult}
     * @param ResponseInterface $response
     * @return ReceiptStatusResult
     */
    public function getReceiptStatusResult(ResponseInterface $response): ReceiptStatusResult;

    /**
     * Получение массива данных для формирования запроса отправки СМС.
     * ### Результат в формате:
     * ```
     * [
     *     'login' => ...,
     *     'phone' => $phone,
     *     'message' => $message,
     *     'signature' => ...
     * ]
     * ```
     * @param int $phone Номер телефона в международном формате без символа «+». Например, 8999*******.
     * @param string $message строка в кодировке UTF-8 длиной до 128 символов, содержащая текст отправляемого SMS.
     * @return array
     */
    public function getSendSmsData(int $phone, string $message): array;

    /**
     * Преобразует ответ {@see RobokassaApi::sendSms()} в объект результата {@see SmsSendResult}
     * @param ResponseInterface $response
     * @return SmsSendResult
     */
    public function getSmsSendResult(ResponseInterface $response): SmsSendResult;

    /**
     * Формирует запрос для получения статуса чека, который может быть отправлен самостоятельно через PSR-18 HTTP Client.
     * @param ReceiptStatusOptions $receiptStatusOptions
     * @return RequestInterface
     */
    public function receiptStatusRequest(ReceiptStatusOptions $receiptStatusOptions): RequestInterface;

    /**
     * Формирует запрос для отправки второго чека, который может быть отправлен самостоятельно через PSR-18 HTTP Client.
     * @param SecondReceiptOptions $secondReceiptOptions
     * @return RequestInterface
     * @see RobokassaApi::sendSecondReceiptAttach()
     */
    public function secondReceiptAttachRequest(SecondReceiptOptions $secondReceiptOptions): RequestInterface;

    /**
     * Отправка второго чека
     * Для отправки используется любой PSR-18 Http Client.
     *
     * ### Результат отправки можно узнать:
     * - или напрямую из полученного Psr\Http\Message\ResponseInterface
     * - или преобразовав ответ в {@see ReceiptAttachResult} с помощью
     * {@see RobokassaApi::getReceiptAttachResult()}
     *
     * ### Пример:
     * ```php
     * $items = [
     *      new Item(
     *          // ...
     *      ),
     * ];
     * $response = $robokassa->sendSecondReceiptAttach(new SecondReceiptOptions(
     *      // ...
     *      items: $items,
     *      vats: RobokassaApi::getVatsFromItems($items),
     *      // ...
     * ));
     * $receiptAttachResult = $robokassa->getReceiptAttachResult($response);
     * ```
     *
     * @param SecondReceiptOptions $secondReceiptOptions
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function sendSecondReceiptAttach(SecondReceiptOptions $secondReceiptOptions): ResponseInterface;

    /**
     * Отправка СМС.
     * Для отправки используется любой PSR-18 Http Client.
     *
     * ### Результат отправки можно узнать:
     * - или напрямую из полученного Psr\Http\Message\ResponseInterface
     * - или преобразовав ответ в {@see SmsSendResult} с помощью
     * {@see RobokassaApi::getSmsSendResult()}
     *
     * ### Пример:
     * ```
     * $response = $robokassaApi->sendSms(89991234567, 'All work fine!');
     * $smsSendResult = $robokassaApi->getSmsSendResult($response);
     * ```
     * @param int $phone Номер телефона в международном формате без символа «+». Например, 8999*******.
     * @param string $message строка в кодировке UTF-8 длиной до 128 символов, содержащая текст отправляемого SMS.
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function sendSms(int $phone, string $message): ResponseInterface;

    /**
     * Изменить PSR-18 HTTP Client
     * @param ClientInterface|null $psr18Client
     * @return void
     */
    public function setPsr18Client(?ClientInterface $psr18Client): void;

    /**
     * Формирует запрос для отправки СМС, который может быть отправлен самостоятельно через PSR-18 HTTP Client.
     * @param int $phone
     * @param string $message
     * @return RequestInterface
     */
    public function smsRequest(int $phone, string $message): RequestInterface;
}
