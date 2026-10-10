<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Enum;

/**
 * Форма оплати. Фіскальний код і назву, які потраплять у чек, визначає
 * сервіс; залишок готівки каси рухає лише {@see PaymentType::Cash}.
 */
enum PaymentType: string
{
    /** 0 ГОТІВКА. */
    case Cash = 'cash';
    /** 301 КАРТКА. */
    case Card = 'card';
    /** 305 СЕРТИФІКАТ. */
    case Certificate = 'certificate';
    /** 306 ПЕРЕКАЗ З ПОТОЧНОГО РАХУНКУ. */
    case BankTransfer = 'bank_transfer';
    /** 100000 ПРЯМИЙ ДЕБЕТ. */
    case DirectDebit = 'direct_debit';
}
