<?php

declare(strict_types=1);

namespace Tribux\Dian\Validation;

final readonly class XmlValidationResult
{
    /** @param list<XmlValidationError> $errors */
    public function __construct(
        public bool $valid,
        public array $errors,
    ) {
    }

    /** @return array{valid:bool,errors:list<array{level:int,code:int,line:int,column:int,message:string,file:string}>} */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => array_map(
                static fn (XmlValidationError $error): array => $error->toArray(),
                $this->errors,
            ),
        ];
    }
}
