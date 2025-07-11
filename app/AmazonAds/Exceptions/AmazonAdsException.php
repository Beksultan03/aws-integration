<?php

namespace App\AmazonAds\Exceptions;

use Exception;

class AmazonAdsException extends Exception
{
    /**
     * Constructor for AmazonAdsException.
     *
     * @param string $message The exception message.
     * @param int $code The exception code (optional).
     * @param Exception|null $previous The previous exception for chaining (optional).
     */
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get a user-friendly error message for logging or display.
     *
     * @return string
     */
    public function getFriendlyMessage(): string
    {
        return sprintf(
            'Amazon Ads Error: %s (Code: %d)',
            $this->getMessage(),
            $this->getCode()
        );
    }
}
