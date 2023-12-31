<?php

declare(strict_types=1);

namespace netFantom\RobokassaApi\Params\Item;

/**
 * Признак предмета расчёта. Этот параметр необязательный. Если этот параметр не передан клиентом,
 * то в чеке будет указано значение параметра по умолчанию из Личного кабинета.
 */
enum PaymentObject: string
{
    /**
     * Товар. О реализуемом товаре, за исключением подакцизного товара (наименование и иные сведения, описывающие товар)
     */
    case commodity = 'commodity';
    /**
     * Работа. О выполняемой работе (наименование и иные сведения, описывающие работу)
     */
    case job = 'job';
    /**
     * Услуга. Об оказываемой услуге (наименование и иные сведения, описывающие услугу)
     */
    case service = 'service';
    /**
     * Ставка азартной игры. О приеме ставок при осуществлении деятельности по проведению азартных игр
     */
    case gambling_bet = 'gambling_bet';
    /**
     * Выигрыш азартной игры. О выплате денежных средств в виде выигрыша при осуществлении
     * деятельности по проведению азартных игр
     */
    case gambling_prize = 'gambling_prize';
    /**
     * Лотерейный билет. О приеме денежных средств при реализации лотерейных билетов, электронных лотерейных билетов,
     * приеме лотерейных ставок при осуществлении деятельности по проведению лотерей
     */
    case lottery = 'lottery';
    /**
     * Выигрыш лотереи. О выплате денежных средств в виде выигрыша при осуществлении деятельности по проведению лотерей
     */
    case lottery_prize = 'lottery_prize';
    /**
     * Предоставление результатов интеллектуальной деятельности. О предоставлении прав на использование результатов
     * интеллектуальной деятельности или средств индивидуализации
     */
    case intellectual_activity = 'intellectual_activity';
    /**
     * Платеж. Об авансе, задатке, предоплате, кредите, взносе в счет оплаты, пени, штрафе, вознаграждении, бонусе
     * и ином аналогичном предмете расчета
     */
    case payment = 'payment';
    /**
     * Агентское вознаграждение. О вознаграждении пользователя, являющегося платежным агентом (субагентом),
     * банковским платежным агентом (субагентом), комиссионером, поверенным или иным агентом
     */
    case agent_commission = 'agent_commission';
    /**
     * Составной предмет расчета. О предмете расчета, состоящем из предметов,
     * каждому из которых может быть присвоено значение выше перечисленных признаков
     */
    case composite = 'composite';
    /**
     * Курортный сбор
     */
    case resort_fee = 'resort_fee';
    /**
     * Иной предмет расчета. О предмете расчета, не относящемуся к выше перечисленным предметам расчета
     */
    case another = 'another';
    /**
     * Имущественное право
     */
    case property_right = 'property_right';
    /**
     * Внереализационный доход
     */
    case non_operating_gain = 'non-operating_gain';
    /**
     * Страховые взносы
     */
    case insurance_premium = 'insurance_premium';
    /**
     * Торговый сбор
     */
    case sales_tax = 'sales_tax';
    /**
     * New?
     * Подакцизный товар. О реализуемом подакцизном товаре (наименование и иные сведения, описывающие товар);
     */
    case excise = 'excise';
}
