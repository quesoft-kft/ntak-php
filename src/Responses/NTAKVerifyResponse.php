<?php

namespace QueSoft\Ntak\Responses;

use QueSoft\Ntak\Enums\NTAKVerifyStatus;

class NTAKVerifyResponse
{
    /**
     * @param  list<mixed>  $successfulMessages
     * @param  list<mixed>  $unsuccessfulMessages
     * @param  list<mixed>  $headerErrors
     */
    public function __construct(
        public readonly array $successfulMessages,
        public readonly array $unsuccessfulMessages,
        public readonly array $headerErrors,
        public readonly NTAKVerifyStatus $status,
        public readonly string $processId,
    ) {
    }

    public function successful(): bool
    {
        return $this->status === NTAKVerifyStatus::TELJESEN_SIKERES;
    }

    public function unsuccessful(): bool
    {
        return ! $this->successful();
    }
}
