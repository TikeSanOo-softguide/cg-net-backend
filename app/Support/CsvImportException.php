<?php

namespace App\Support;

use RuntimeException;

final class CsvImportException extends RuntimeException
{
    /**
     * @param array<string, string|int> $replace
     */
    public function __construct(
        public readonly string $translationKey,
        public readonly array $replace = [],
    ) {
        parent::__construct($translationKey);
    }
}