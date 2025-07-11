<?php

namespace App\AmazonAds\Http\DTO\AdGroup;

use App\Http\DTO\BaseDTO;

class CreateDTO extends BaseDTO
{
    public string $campaignId;
    public string $name;
    public string $state;
    public float $defaultBid;
    public string $userId;
    /**
     * Constructor to initialize the campaign data.
     *
     * @param string $campaignId
     * @param string $name
     * @param string $state
     * @param float $defaultBid
     * @param string $userId
     */
    public function __construct(
        string $campaignId,
        string $name,
        string $state,
        float $defaultBid,
        string $userId
    ) {
        $this->campaignId = $campaignId;
        $this->name = $name;
        $this->state = $state;
        $this->defaultBid = $defaultBid;
        $this->userId = $userId;
    }

    public function toArray(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'name' => $this->name,
            'state' => $this->state,
            'default_bid' => $this->defaultBid,
            'user_id' => $this->userId
        ];
    }
} 