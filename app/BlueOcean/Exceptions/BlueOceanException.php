<?php

namespace App\BlueOcean\Exceptions;

class BlueOceanException extends ApiException
{
    private array $errors;

    public static function create(string $message, array $errors): ApiException
    {
        $error = new static($message);
        $error->errors = $errors;

        return $error;
    }

    public function getIncorrectOrdersMessage(): string
    {
        $messages = [];

        foreach ($this->errors as $error) {
            $messages[] = 'For order id: ' . $error['order_id'] . ', error: ' . $error['message'];
        }

        return implode("<br>", $messages);
    }
}
