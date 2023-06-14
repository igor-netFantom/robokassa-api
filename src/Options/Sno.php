<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Options;

/**
 * Система налогообложения. Необязательное поле, если у организации имеется только один тип налогообложения.
 * (Данный параметр обязательно задается в личном кабинете магазина)
 */
enum Sno: string
{
    /**
     * – Общая СН
     */
    case osn = 'osn';
    /**
     * – Упрощенная СН (доходы)
     */
    case usn_income = 'usn_income';
    /**
     * – Упрощенная СН (доходы минус расходы)
     */
    case usn_income_outcome = 'usn_income_outcome';
    /**
     * – Единый сельскохозяйственный налог
     */
    case esn = 'esn';
    /**
     * – Патентная СН
     */
    case patent = 'patent';
}
