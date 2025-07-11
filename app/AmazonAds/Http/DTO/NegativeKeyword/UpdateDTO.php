<?php

namespace App\AmazonAds\Http\DTO\NegativeKeyword;

class UpdateDTO
{
    private ?string $state;
    private ?string $text;

    public function __construct(
        ?string $state = null,
        ?string $text = null
    ) {
        $this->state = $state;
        $this->text = $text;
    }

    public function toArray(): array
    {
        return array_filter([
            'state' => $this->state,
            'text' => $this->text,
        ]);
    }
} 