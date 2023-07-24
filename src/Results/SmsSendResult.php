<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace netFantom\RobokassaApi\Results;

class SmsSendResult
{
    /** Запрос обработан успешно */
    public const RESULT_CODE_OK = 0;
    /** Отсутствует параметр запроса */
    public const RESULT_CODE_MISSING_PARAMETER = 1;
    /** Указанный партнер не найден */
    public const RESULT_CODE_PARTNER_NOT_FOUND = 2;
    /** Партнер неактивен */
    public const RESULT_CODE_PARTNER_DISABLED = 3;
    /** Отправка SMS-сообщений для данного партнера недоступна */
    public const RESULT_CODE_SMS_NOT_ENABLED = 4;
    /** В данный момент отправка SMS-сообщений указанным партнером невозможна */
    public const RESULT_CODE_SMS_NOT_AVAILABLE = 5;
    /** Превышен лимит SMS-сообщений */
    public const RESULT_CODE_SMS_LIMIT_EXCEEDED = 6;
    /** Неверная подпись запроса */
    public const RESULT_CODE_INVALID_SIGNATURE = 1000;
    /** Внутренняя ошибка */
    public const RESULT_CODE_INTERNAL_ERROR = 9999;

    /**
     * @param bool $result Значение логического типа, указывающее на общий успех или неуспех обработки запроса.
     * Запрос можно считать успешно исполненным если поле result = true
     * @param int $errorCode Целочисленное значение кода ошибки обработки
     * @param string $errorMessage Текстовое описание возникшей в процессе обработки запроса ошибки.
     * @param int|null $count Целочисленное значение, указывающее на количество SMS, доступное после этого запроса
     * (данное значение заполняется только в случае успешного исполнения запроса).
     */
    public function __construct(
        public readonly bool $result,
        public readonly int $errorCode,
        public readonly string $errorMessage,
        public readonly ?int $count = null,
    ) {
    }
}
