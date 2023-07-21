<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Options;

/**
 * Данные для получения статуса чека
 */
class ReceiptStatusOptions
{
    /**
     * @param int $id Идентификатор операции
     * @param ?string $merchantId Идентификатор магазина в Robokassa, который Вы придумали при создании магазина.
     * (берется из {@see RobokassaApi::$merchantLogin} при использовании в методах {@see RobokassaApi} если не указан)
     */
    public function __construct(
        public readonly int $id,
        public ?string $merchantId = null,
    ) {
    }
}
