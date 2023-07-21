<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace netFantom\RobokassaApi;

use Http\Discovery\Psr18Client;
use JsonException;
use netFantom\RobokassaApi\Exceptions\MissingRequestFactory;
use netFantom\RobokassaApi\Exceptions\MissingStreamFactory;
use netFantom\RobokassaApi\Exceptions\TooLongSmsMessageException;
use netFantom\RobokassaApi\Options\InvoiceOptions;
use netFantom\RobokassaApi\Options\Item;
use netFantom\RobokassaApi\Options\Receipt;
use netFantom\RobokassaApi\Options\ReceiptStatusOptions;
use netFantom\RobokassaApi\Options\ResultOptions;
use netFantom\RobokassaApi\Options\SecondReceiptOptions;
use netFantom\RobokassaApi\Options\Vat;
use netFantom\RobokassaApi\Response\ReceiptAttachResponse;
use netFantom\RobokassaApi\Response\ReceiptStatusResponse;
use netFantom\RobokassaApi\Response\SmsSendResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class RobokassaApi
{
    /**
     * @param string $merchantLogin Идентификатор магазина
     * @param string $password1 Пароль №1 - для формирования подписи запроса
     * @param string $password2 Пароль №2 - для проверки подписи ответа
     * @param bool $isTest Для работы в тестовом режиме
     * @param string $hashAlgo Алгоритм хеширования
     * @param string $paymentUrl URL оплаты
     * @param string $secondReceiptAttachUrl URL Формирования второго чека (В РАЗРАБОТКЕ)
     * @param string $secondReceiptStatusUrl URL Получение статуса чека (В РАЗРАБОТКЕ)
     * @param string $smsUrl URL отправки SMS
     * @param string $recurringUrl URL периодических платежей (В РАЗРАБОТКЕ)
     * @param string $splitPaymentUrl URL для сплитования (разделения) платежей (В РАЗРАБОТКЕ)
     * @param ?ClientInterface $psr18Client PSR-18 HTTP Client клиент используется для отправки некоторых запросов
     * (формирование второго чека, получение статуса чека, отправка СМС)<br><br>
     *
     * RobokassaApi::psr18Client указывать необязательно.<br><br>
     *
     * При его отсутствии будет произведена попытка автоматического поиска
     * подходящего уже установленного PSR-18 HTTP клиента.
     */
    public function __construct(
        public string $merchantLogin,
        public string $password1,
        public string $password2,
        public bool $isTest = false,
        public string $hashAlgo = 'md5',
        public string $paymentUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx',
        public string $secondReceiptAttachUrl = 'https://ws.roboxchange.com/RoboFiscal/Receipt/Attach',
        public string $secondReceiptStatusUrl = 'https://ws.roboxchange.com/RoboFiscal/Receipt/Status',
        public string $smsUrl = 'https://services.robokassa.ru/SMS/',
        public string $recurringUrl = 'https://auth.robokassa.ru/Merchant/Recurring',
        public string $splitPaymentUrl = 'https://auth.robokassa.ru/Merchant/Payment/CreateV2',
        private ?ClientInterface $psr18Client = null,
    ) {
        if ($psr18Client !== null) {
            $this->checkPsr18Client();
        }
    }

    /**
     * @return void
     */
    public function checkPsr18Client(): void
    {
        if (!$this->psr18Client instanceof RequestFactoryInterface) {
            throw new MissingRequestFactory(
                'RobokassaApi::psr18HttpClient must implement Psr\Http\Message\RequestFactoryInterface'
            );
        }

        if (!$this->psr18Client instanceof StreamFactoryInterface) {
            throw new MissingStreamFactory(
                'RobokassaApi::psr18HttpClient must implement Psr\Http\Message\StreamFactoryInterface'
            );
        }
    }

    /**
     * Получение параметров результата {@see ResultOptions} от Робокассы
     * из массива GET или POST параметров HTTP запроса
     * @param array<string, string> $requestParameters
     * @return ResultOptions
     */
    public static function getResultOptionsFromRequestArray(array $requestParameters): ResultOptions
    {
        return new ResultOptions(
            outSum: $requestParameters['OutSum'],
            invId: isset($requestParameters['InvId']) ? (int)$requestParameters['InvId'] : null,
            signatureValue: $requestParameters['SignatureValue'],
            userParameters: self::getUserParametersFromRequestArray($requestParameters),
        );
    }

    /**
     * Получение пользовательских параметров (shp_...) из массива GET или POST параметров
     * @param array<string,string> $requestParameters
     * @return array<string, string>
     */
    public static function getUserParametersFromRequestArray(array $requestParameters): array
    {
        $userParameters = array_filter(
            $requestParameters,
            static fn($key) => str_starts_with(strtolower((string)$key), 'shp_'),
            ARRAY_FILTER_USE_KEY
        );
        $userParametersWithoutPrefix = [];
        foreach ($userParameters as $index => $userParameter) {
            $userParametersWithoutPrefix[substr_replace($index, '', 0, 4)] = $userParameter;
        }
        return $userParametersWithoutPrefix;
    }

    /**
     * @param Item[] $items
     * @return Vat[]
     */
    public static function getVatsFromItems(array $items): array
    {
        $vats = [];
        foreach ($items as $item) {
            $vats[] = new Vat($item->tax, $item->tax->getTaxSumFromItemSum($item->sum));
        }
        return $vats;
    }

    /**
     * Проверка корректности подписи параметров результата {@see ResultOptions} от Робокассы
     */
    public function checkSignature(ResultOptions $resultOptions): bool
    {
        return $this->getValidSignatureForResult($resultOptions) === strtolower($resultOptions->signatureValue);
    }

    /**
     * Получение правильной подписи для параметров результата {@see ResultOptions} от Робокассы
     */
    public function getValidSignatureForResult(ResultOptions $resultOptions): string
    {
        $signature = "$resultOptions->outSum:$resultOptions->invId";
        $signature .= ":$this->password2";
        if (!empty($resultOptions->userParameters)) {
            $signature .= ':' . $this->implodeUserParameters($resultOptions->userParameters);
        }

        return strtolower($this->encryptSignature($signature));
    }

    /**
     * @param array<array-key, string> $additionalUserParameters
     * @return string
     */
    private function implodeUserParameters(array $additionalUserParameters): string
    {
        ksort($additionalUserParameters);
        foreach ($additionalUserParameters as $key => $value) {
            $additionalUserParameters[$key] = $key . '=' . $value;
        }
        return implode(':', $additionalUserParameters);
    }

    private function encryptSignature(string $signature): string
    {
        return hash($this->hashAlgo, $signature);
    }

    /**
     * Получение URL для оплаты счета с указанными параметрами
     * (GET запрос длиной более 2083 символов может не работать,
     * поэтому счет на оплату с чеком {@see Receipt} рекомендуется
     * отправлять, формирую форму с параметрами {@see RobokassaApi::getPaymentParameters()}
     * и методом отправки POST)
     */
    public function getPaymentUrl(InvoiceOptions $invoiceOptions): string
    {
        return $this->paymentUrl . '?' . http_build_query($this->getPaymentParameters($invoiceOptions));
    }

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
    public function getPaymentParameters(InvoiceOptions $invoiceOptions): array
    {
        return [
            'MerchantLogin' => $this->merchantLogin,
            'OutSum' => $invoiceOptions->outSum,
            'Description' => $invoiceOptions->description,
            'SignatureValue' => $invoiceOptions->signatureValue ?? $this->generateSignatureForPayment($invoiceOptions),
            'IncCurrLabel' => $invoiceOptions->incCurrLabel,
            'InvId' => isset($invoiceOptions->invId) ? (string)$invoiceOptions->invId : null,
            'Culture' => $invoiceOptions->culture?->value,
            'Encoding' => $invoiceOptions->encoding,
            'Email' => $invoiceOptions->email,
            'ExpirationDate' => $invoiceOptions->expirationDate,
            'OutSumCurrency' => $invoiceOptions->outSumCurrency?->value,
            'UserIp' => $invoiceOptions->userIP,
            'Receipt' => self::getEncodedReceipt($invoiceOptions),
            'IsTest' => $this->isTest ? '1' : null,
            ...$invoiceOptions->getFormattedUserParameters()
        ];
    }

    /**
     * Формирование и кодирование подписи:
     *
     * Минимальный вариант подписи до кодирования
     * MerchantLogin:OutSum:Пароль#1
     *
     * Максимальный вариант подписи до кодирования
     * MerchantLogin:OutSum:InvId:OutSumCurrency:UserIp:Receipt:Пароль#1:Shp_...=...:Shp_...=...
     */
    public function generateSignatureForPayment(InvoiceOptions $invoiceOptions): string
    {
        $signature = "$this->merchantLogin:$invoiceOptions->outSum:$invoiceOptions->invId";
        $signature .= isset($invoiceOptions->outSumCurrency) ? ":{$invoiceOptions->outSumCurrency->value}" : '';
        $signature .= isset($invoiceOptions->userIP) ? ":$invoiceOptions->userIP" : '';
        $receipt = self::getEncodedReceipt($invoiceOptions);
        $signature .= isset($receipt) ? ":$receipt" : '';
        $signature .= ":$this->password1";
        $userParameters = $invoiceOptions->getFormattedUserParameters();
        $signature .= !empty($userParameters) ? ':' . $this->implodeUserParameters($userParameters) : '';

        return strtolower($this->encryptSignature($signature));
    }

    /**
     * @throws JsonException
     */
    private static function getEncodedReceipt(InvoiceOptions $instance): ?string
    {
        if (is_string($instance->receipt)) {
            return $instance->receipt;
        }
        return is_null($instance->receipt) ? null : json_encode(
            $instance->receipt,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public function getReceiptAttachResponseFromHttpResponse(ResponseInterface $response): ReceiptAttachResponse
    {
        $jsonObject = $this->parseResponseToJsonObject($response);
        return new ReceiptAttachResponse(
            ResultCode: isset($jsonObject->ResultCode) ? (string)$jsonObject->ResultCode : null,
            ResultDescription: isset($jsonObject->ResultDescription) ? (string)$jsonObject->ResultDescription : null,
            OpKey: isset($jsonObject->OpKey) ? (string)$jsonObject->OpKey : null,
        );
    }

    public function parseResponseToJsonObject(ResponseInterface $response): object
    {
        /** @var object $jsonObject */
        $jsonObject = json_decode(
            (string)$response->getBody(),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
        return $jsonObject;
    }

    public function getReceiptStatusResponseFromHttpResponse(ResponseInterface $response): ReceiptStatusResponse
    {
        $jsonObject = $this->parseResponseToJsonObject($response);
        return new ReceiptStatusResponse(
            Code: isset($jsonObject->Code) ? (string)$jsonObject->Code : null,
            Description: isset($jsonObject->Description) ? (string)$jsonObject->Description : null,
            Statuses: (isset($jsonObject->Statuses) && is_array($jsonObject->Statuses)) ? $jsonObject->Statuses : null,
            FnNumber: isset($jsonObject->FnNumber) ? (string)$jsonObject->FnNumber : null,
            FiscalDocumentNumber: isset($jsonObject->FiscalDocumentNumber) ? (string)$jsonObject->FiscalDocumentNumber : null,
            FiscalDocumentAttribute: isset($jsonObject->FiscalDocumentAttribute) ? (string)$jsonObject->FiscalDocumentAttribute : null,
            FiscalDate: isset($jsonObject->FiscalDate) ? (string)$jsonObject->FiscalDate : null,
            FiscalType: isset($jsonObject->FiscalType) ? (string)$jsonObject->FiscalType : null,
        );
    }

    public function getSmsSendResponseFromHttpResponse(ResponseInterface $response): SmsSendResponse
    {
        $jsonObject = $this->parseResponseToJsonObject($response);
        return new SmsSendResponse(
            result: isset($jsonObject->result) && $jsonObject->result,
            errorCode: isset($jsonObject->errorCode) ? (int)$jsonObject->errorCode : 9999,
            errorMessage: isset($jsonObject->errorMessage) ? (string)$jsonObject->errorMessage : '',
            count: isset($jsonObject->count) ? (int)$jsonObject->count : null,
        );
    }

    /**
     * Получение статуса чека
     * Для отправки запроса используется любой PSR-18 Http Client.
     *
     * ### Результат отправки запроса можно узнать:
     * - или напрямую из полученного Psr\Http\Message\ResponseInterface
     * - или преобразовав ответ в {@see ReceiptStatusResponse} с помощью
     * {@see RobokassaApi::getReceiptStatusResponseFromHttpResponse()}
     *
     * ### Пример:
     * ```php
     * $response = $robokassa->receiptStatus(new ReceiptStatusOptions(
     *     id: 34,
     * ));
     * $receiptStatusResponse = $robokassa->getReceiptStatusResponseFromHttpResponse($response);
     * ```
     *
     * @param ReceiptStatusOptions $secondReceiptOptions
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function receiptStatus(ReceiptStatusOptions $secondReceiptOptions): ResponseInterface
    {
        $request = $this->receiptStatusRequest($secondReceiptOptions);
        return $this->getPsr18Client()->sendRequest($request);
    }

    public function receiptStatusRequest(
        ReceiptStatusOptions $receiptStatusOptions
    ): RequestInterface {
        $postData = $this->getBase64SignedPostData($receiptStatusOptions);
        return $this->getPsr18Client()
            ->createRequest('POST', $this->secondReceiptStatusUrl)
            ->withBody($this->getPsr18Client()->createStream($postData));
    }

    public function getPsr18Client(): RequestFactoryInterface&ClientInterface&StreamFactoryInterface
    {
        if ($this->psr18Client === null) {
            $this->psr18Client = new Psr18Client();
        }

        $this->checkPsr18Client();
        /** @var RequestFactoryInterface&ClientInterface&StreamFactoryInterface $this- >psr18Client */
        return $this->psr18Client;
    }

    public function getBase64SignedPostData(SecondReceiptOptions|ReceiptStatusOptions $options): string
    {
        if ($options->merchantId === null) {
            $options->merchantId = $this->merchantLogin;
        }
        $encodedSecondReceipt = $this->clearBase64Encode(
            json_encode(
                $options,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );
        return $encodedSecondReceipt . '.' . $this->generateSignatureForSecondReceipt($encodedSecondReceipt);
    }

    /**
     * Робокасса требует удаление знаков "=" из результата кодирования base64
     * @param string $string
     * @return string
     */
    protected function clearBase64Encode(string $string): string
    {
        return rtrim(base64_encode($string), '=');
    }

    /**
     * @param string $encodedSecondReceipt
     * @return string
     */
    public function generateSignatureForSecondReceipt(string $encodedSecondReceipt): string
    {
        return $this->clearBase64Encode($this->encryptSignature($encodedSecondReceipt . $this->password1));
    }

    /**
     * Отправка второго чека
     * Для отправки используется любой PSR-18 Http Client.
     *
     * ### Результат отправки можно узнать:
     * - или напрямую из полученного Psr\Http\Message\ResponseInterface
     * - или преобразовав ответ в {@see ReceiptAttachResponse} с помощью
     * {@see RobokassaApi::getReceiptAttachResponseFromHttpResponse()}
     *
     * ### Пример:
     * ```php
     * $items = [
     *      new Item(
     *          // ...
     *      ),
     * ];
     * $response = $robokassa->secondReceiptAttach(new SecondReceiptOptions(
     *      // ...
     *      items: $items,
     *      vats: RobokassaApi::getVatsFromItems($items),
     *      // ...
     * ));
     * $receiptAttachResponse = $robokassa->getReceiptAttachResponseFromHttpResponse($response);
     * ```
     *
     * @param SecondReceiptOptions $secondReceiptOptions
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function secondReceiptAttach(SecondReceiptOptions $secondReceiptOptions): ResponseInterface
    {
        $request = $this->secondReceiptAttachRequest($secondReceiptOptions);
        return $this->getPsr18Client()->sendRequest($request);
    }

    public function secondReceiptAttachRequest(SecondReceiptOptions $secondReceiptOptions): RequestInterface
    {
        $postData = $this->getBase64SignedPostData($secondReceiptOptions);
        return $this->getPsr18Client()
            ->createRequest('POST', $this->secondReceiptAttachUrl)
            ->withBody($this->getPsr18Client()->createStream($postData));
    }

    /**
     * Отправка СМС.
     * Для отправки используется любой PSR-18 Http Client.
     *
     * ### Результат отправки можно узнать:
     * - или напрямую из полученного Psr\Http\Message\ResponseInterface
     * - или преобразовав ответ в {@see SmsSendResponse} с помощью
     * {@see RobokassaApi::getSmsSendResponseFromHttpResponse()}
     *
     * ### Пример:
     * ```
     * $response = $robokassaApi->sendSms(89991234567, 'All work fine!');
     * $smsSendResponse = $robokassaApi->getSmsSendResponseFromHttpResponse($response);
     * ```
     * @param int $phone Номер телефона в международном формате без символа «+». Например, 8999*******.
     * @param string $message строка в кодировке UTF-8 длиной до 128 символов, содержащая текст отправляемого SMS.
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function sendSms(int $phone, string $message): ResponseInterface
    {
        $requestData = $this->getSendSmsRequestData($phone, $message);
        $requestUri = $this->smsUrl . '?' . http_build_query($requestData);
        $request = $this->getPsr18Client()->createRequest('GET', $requestUri);
        return $this->getPsr18Client()->sendRequest($request);
    }

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
    public function getSendSmsRequestData(int $phone, string $message): array
    {
        if (mb_strlen($message) > 128) {
            throw new TooLongSmsMessageException();
        }

        return [
            'login' => $this->merchantLogin,
            'phone' => $phone,
            'message' => $message,
            'signature' => $this->encryptSignature(
                "$this->merchantLogin:$phone:$message:$this->password1"
            )
        ];
    }

    public function setPsr18Client(?ClientInterface $psr18Client): void
    {
        $this->psr18Client = $psr18Client;
    }
}
