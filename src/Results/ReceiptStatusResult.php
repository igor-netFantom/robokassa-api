<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace netFantom\RobokassaApi\Results;

class ReceiptStatusResult
{
    /** Ожидание регистрации */
    public const RESULT_CODE_WAITING = '1';
    /** Чек зарегистрирован */
    public const RESULT_CODE_REGISTERED = '2';
    /** Ошибка регистрации чека */
    public const RESULT_CODE_RECEIPT_ERROR = '3';
    /** Ошибка обработки запроса */
    public const RESULT_CODE_MERCHANT_ERROR = '1000';
    /** Неправильная подпись */
    public const RESULT_CODE_INVALID_SIGNATURE = '1001';

    /**
     * @param string|null $Code Статус регистрации чека.
     * @param string|null $Description Описание результата формирования чека
     * @param mixed|null $Statuses
     * @param string|null $FnNumber Номер ФН
     * @param string|null $FiscalDocumentNumber Фискальный номер документа
     * @param string|null $FiscalDocumentAttribute Фискальный признак документа
     * @param string|null $FiscalDate Дата и время формирования фискального чека
     * @param string|null $FiscalType
     */
    public function __construct(
        public readonly ?string $Code,
        public readonly ?string $Description,
        public readonly mixed $Statuses = null,
        public readonly ?string $FnNumber = null,
        public readonly ?string $FiscalDocumentNumber = null,
        public readonly ?string $FiscalDocumentAttribute = null,
        public readonly ?string $FiscalDate = null,
        public readonly ?string $FiscalType = null,
    ) {
    }
}