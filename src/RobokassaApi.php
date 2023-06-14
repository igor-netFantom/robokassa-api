<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace netFantom\RobokassaApi;

use JsonException;
use netFantom\RobokassaApi\Exceptions\TooLongSmsMessageException;
use netFantom\RobokassaApi\Options\InvoiceOptions;
use netFantom\RobokassaApi\Options\Receipt;
use netFantom\RobokassaApi\Options\ResultOptions;

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
    ) {
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
     * Проверка корректности подписи параметров результата {@see ResultOptions} от Робокассы
     */
    public function checkSignature(ResultOptions $resultOptions): bool
    {
        return $this->getValidSignatureForResult($resultOptions) === strtolower($resultOptions->signatureValue);
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
     * Получает параметры платежа для передачи в Робокассу
     *
     * # ВНИМАНИЕ
     * В инструкции сказано, что поле Receipt на данном этапе должно быть закодирован через **urlencode() ДО отправки**,
     * то есть в GET запросе поле Receipt должно кодироваться дважды - urlencode(urlencode($Receipt)),
     * но в их демо магазине оно дополнительно **не закодировано и работает без этого**. Данный момент требует тщательной проверки.
     * {@link https://docs.robokassa.ru/fiscalization/}
     */
    public function getPaymentParameters(InvoiceOptions $invoiceOptions): array
    {
        return [
            'MerchantLogin' => $this->merchantLogin,
            'OutSum' => $invoiceOptions->outSum,
            'Description' => $invoiceOptions->description,
            'SignatureValue' => $invoiceOptions->signatureValue ?? $this->generateSignatureForPayment($invoiceOptions),
            'IncCurrLabel' => $invoiceOptions->incCurrLabel,
            'InvId' => $invoiceOptions->invId,
            'Culture' => $invoiceOptions->culture?->value,
            'Encoding' => $invoiceOptions->encoding,
            'Email' => $invoiceOptions->email,
            'ExpirationDate' => $invoiceOptions->expirationDate,
            'OutSumCurrency' => $invoiceOptions->outSumCurrency?->value,
            'UserIp' => $invoiceOptions->userIP,
            'Receipt' => self::getEncodedReceipt($invoiceOptions),
            'IsTest' => $this->isTest ? 1 : null,
            ...$invoiceOptions->getFormattedUserParameters()
        ];
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

    private function encryptSignature(string $signature): string
    {
        return hash($this->hashAlgo, $signature);
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
}
