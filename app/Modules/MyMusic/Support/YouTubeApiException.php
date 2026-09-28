<?php

namespace App\Modules\MyMusic\Support;

class YouTubeApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $quotaExceeded = false,
    ) {
        parent::__construct($message);
    }
}
