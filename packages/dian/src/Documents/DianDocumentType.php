<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents;

enum DianDocumentType: string
{
    case ApplicationResponse = 'application-response';
    case AttachedDocument = 'attached-document';
    case CreditNote = 'credit-note';
    case DebitNote = 'debit-note';
    case Invoice = 'invoice';

    public function filePrefix(): string
    {
        return match ($this) {
            self::ApplicationResponse => 'ar',
            self::AttachedDocument => 'ad',
            self::CreditNote => 'nc',
            self::DebitNote => 'nd',
            self::Invoice => 'fv',
        };
    }

    public function xsdFileName(): string
    {
        return match ($this) {
            self::ApplicationResponse => 'UBL-ApplicationResponse-2.1.xsd',
            self::AttachedDocument => 'UBL-AttachedDocument-2.1.xsd',
            self::CreditNote => 'UBL-CreditNote-2.1.xsd',
            self::DebitNote => 'UBL-DebitNote-2.1.xsd',
            self::Invoice => 'UBL-Invoice-2.1.xsd',
        };
    }
}
