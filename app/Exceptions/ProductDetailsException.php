<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class ProductDetailsException extends Exception
{
    public static function serialNumberNotFound(): self
    {
        return new self(
            "Given serial number not found",
            Response::HTTP_NOT_FOUND
        );
    }

}
