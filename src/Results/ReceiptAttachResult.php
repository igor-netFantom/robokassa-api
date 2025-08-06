<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace netFantom\RobokassaApi\Results;

class ReceiptAttachResult
{
    /** Ожидание регистрации */
    public const RESULT_CODE_WAITING = '1';
    /** Чек зарегистрирован */
    public const RESULT_CODE_REGISTERED = '2';
    /** Ошибка регистрации чека */
    public const RESULT_CODE_RECEIPT_ERROR = '3';
    /** Внутренняя ошибка запроса */
    public const RESULT_CODE_MERCHANT_ERROR = '1000';
    /** Неправильная подпись */
    public const RESULT_CODE_INVALID_SIGNATURE = '1001';

    /**
     * @param string|null $ResultCode Статус получения данных от Клиента.
     * @param string|null $ResultDescription Описание результата обработки чека.
     * @param string|null $OpKey Идентификатор операции.
     */
    public function __construct(
        public readonly ?string $ResultCode,
        public readonly ?string $ResultDescription,
        public readonly ?string $OpKey = null,
    ) {
    }
}