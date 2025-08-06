<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace netFantom\RobokassaApi;

use Http\Discovery\Psr18Client;
use JsonException;
use netFantom\RobokassaApi\Exceptions\{InvalidArgumentException,
    MissingRequestFactory,
    MissingStreamFactory,
    TooLongSmsMessageException};
use netFantom\RobokassaApi\Options\{InvoiceOptions, ReceiptStatusOptions, SecondReceiptOptions};
use netFantom\RobokassaApi\Params\Receipt\Vat;
use netFantom\RobokassaApi\Results\{InvoicePayResult, ReceiptAttachResult, ReceiptStatusResult, SmsSendResult};
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestFactoryInterface, RequestInterface, ResponseInterface, StreamFactoryInterface};

class RobokassaApi implements RobokassaApiInterface
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

    private function checkPsr18Client(): void
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

    public static function getInvoicePayResultFromRequestArray(array $requestParameters): InvoicePayResult
    {
        return new InvoicePayResult(
            outSum: $requestParameters['OutSum']
            ?? throw new InvalidArgumentException(
                'OutSum request parameter required'
            ),
            invId: isset($requestParameters['InvId']) ? (int)$requestParameters['InvId'] : null,
            signatureValue: $requestParameters['SignatureValue']
            ?? throw new InvalidArgumentException(
                'SignatureValue request parameter required'
            ),
            userParameters: self::getUserParametersFromRequestArray($requestParameters),
        );
    }

    public static function getVatsFromItems(array $items): array
    {
        $vats = [];
        foreach ($items as $item) {
            $vats[] = new Vat($item->tax, $item->tax->getTaxSumFromItemSum($item->sum));
        }
        return $vats;
    }

    public function checkSignature(InvoicePayResult $invoicePayResult): bool
    {
        return $this->getValidSignatureForResult($invoicePayResult) === strtolower($invoicePayResult->signatureValue);
    }

    public function getPaymentUrl(InvoiceOptions $invoiceOptions): string
    {
        return $this->paymentUrl . '?' . http_build_query($this->getPaymentParameters($invoiceOptions));
    }

    /**
     * Sends a recurring payment request to the Robokassa API.
     *
     * This method constructs and sends a POST request to the Robokassa recurring payment URL.
     * The request includes the necessary parameters in the request body, encoded as `application/x-www-form-urlencoded`.
     *
     * @param array $params The parameters to include in the recurring payment request. These should include all necessary fields
     *                      such as `MerchantLogin`, `InvoiceID`, `PreviousInvoiceID`, `OutSum`, `Description`, and others required by Robokassa.
     *
     * @return \Psr\Http\Message\ResponseInterface The HTTP response returned by the Robokassa API. The response contains the status
     *                                            and any other relevant information returned by the API.
     *
     * @throws \RuntimeException If the returned response does not implement the expected `Psr\Http\Message\ResponseInterface`.
     */
    public function sendRecurringPayment(array $params): ResponseInterface
    {
        $psr18Client = $this->getPsr18Client();

        $bodyStream = $psr18Client->createStream(http_build_query($params));

        $request = $psr18Client
            ->createRequest('POST', $this->recurringUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($bodyStream);

        $response = $psr18Client->sendRequest($request);

        if (!$response instanceof ResponseInterface) {
            throw new \RuntimeException('Returned response does not implement Psr\Http\Message\ResponseInterface');
        }

        return $response;
    }

    public function getPaymentParameters(InvoiceOptions $invoiceOptions): array
    {
        return [
            'MerchantLogin' => $this->merchantLogin,
            'OutSum' => $invoiceOptions->outSum,
            'Description' => $invoiceOptions->description,
            'PreviousInvoiceID' => isset($invoiceOptions->previousInvoiceId) ? (string)$invoiceOptions->previousInvoiceId : null,
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

    public function getPaymentParametersAsJson(InvoiceOptions $invoiceOptions): string
    {
        return json_encode(
            $this->getPaymentParameters($invoiceOptions),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function smsRequest(int $phone, string $message): RequestInterface
    {
        $requestData = $this->getSendSmsData($phone, $message);
        $requestUri = $this->smsUrl . '?' . http_build_query($requestData);
        return $this->getPsr18Client()->createRequest('GET', $requestUri);
    }

    public function getReceiptAttachResult(ResponseInterface $response): ReceiptAttachResult
    {
        $jsonObject = $this->parseResponseToJsonObject($response);
        return new ReceiptAttachResult(
            ResultCode: isset($jsonObject->ResultCode) ? (string)$jsonObject->ResultCode : null,
            ResultDescription: isset($jsonObject->ResultDescription) ? (string)$jsonObject->ResultDescription : null,
            OpKey: isset($jsonObject->OpKey) ? (string)$jsonObject->OpKey : null,
        );
    }

    public function getReceiptStatusResult(ResponseInterface $response): ReceiptStatusResult
    {
        $jsonObject = $this->parseResponseToJsonObject($response);
        return new ReceiptStatusResult(
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

    public function getSmsSendResult(ResponseInterface $response): SmsSendResult
    {
        $jsonObject = $this->parseResponseToJsonObject($response);
        return new SmsSendResult(
            result: isset($jsonObject->result) && $jsonObject->result,
            errorCode: isset($jsonObject->errorCode) ? (int)$jsonObject->errorCode : 9999,
            errorMessage: isset($jsonObject->errorMessage) ? (string)$jsonObject->errorMessage : '',
            count: isset($jsonObject->count) ? (int)$jsonObject->count : null,
        );
    }

    public function getReceiptStatus(ReceiptStatusOptions $secondReceiptOptions): ResponseInterface
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
        /** @var RequestFactoryInterface&ClientInterface&StreamFactoryInterface $psr18Client */
        $psr18Client = $this->psr18Client;
        return $psr18Client;
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

    public function sendSecondReceiptAttach(SecondReceiptOptions $secondReceiptOptions): ResponseInterface
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

    public function sendSms(int $phone, string $message): ResponseInterface
    {
        $request = $this->smsRequest($phone, $message);
        return $this->getPsr18Client()->sendRequest($request);
    }

    public function getSendSmsData(int $phone, string $message): array
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

    /**
     * Получение пользовательских параметров (shp_...) из массива GET или POST параметров
     * @param array<string,string> $requestParameters
     * @return array<string, string>
     */
    private static function getUserParametersFromRequestArray(array $requestParameters): array
    {
        $userParameters = array_filter(
            $requestParameters,
            static fn($key) => str_starts_with(strtolower($key), 'shp_'),
            ARRAY_FILTER_USE_KEY
        );
        $userParametersWithoutPrefix = [];
        foreach ($userParameters as $index => $userParameter) {
            $userParametersWithoutPrefix[substr_replace($index, '', 0, 4)] = $userParameter;
        }
        return $userParametersWithoutPrefix;
    }

    /**
     * Получение правильной подписи для параметров результата {@see InvoicePayResult} от Робокассы
     */
    private function getValidSignatureForResult(InvoicePayResult $invoicePayResult): string
    {
        $signature = "$invoicePayResult->outSum:$invoicePayResult->invId";
        $signature .= ":$this->password2";
        if (!empty($invoicePayResult->userParameters)) {
            $signature .= ':' . $this->implodeUserParameters($invoicePayResult->userParameters);
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

    private function parseResponseToJsonObject(ResponseInterface $response): object
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

    /**
     * Робокасса требует удаление знаков "=" из результата кодирования base64
     * @param string $string
     * @return string
     */
    private function clearBase64Encode(string $string): string
    {
        return rtrim(base64_encode($string), '=');
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
    private function generateSignatureForPayment(InvoiceOptions $invoiceOptions): string
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

    private function generateSignatureForSecondReceipt(string $encodedSecondReceipt): string
    {
        return $this->clearBase64Encode($this->encryptSignature($encodedSecondReceipt . $this->password1));
    }
}
