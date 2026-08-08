<?php

declare(strict_types=1);

namespace Tribux\Dian\Validation;

use LibXMLError;

final readonly class XmlValidationError
{
    public function __construct(
        public int $level,
        public int $code,
        public int $line,
        public int $column,
        public string $message,
        public string $file,
    ) {
    }

    public static function fromLibxml(LibXMLError $error): self
    {
        return new self(
            level: $error->level,
            code: $error->code,
            line: $error->line,
            column: $error->column,
            message: trim($error->message),
            file: $error->file,
        );
    }

    /** @return array{level:int,code:int,line:int,column:int,message:string,file:string} */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'code' => $this->code,
            'line' => $this->line,
            'column' => $this->column,
            'message' => $this->message,
            'file' => $this->file,
        ];
    }
}
