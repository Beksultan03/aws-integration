<?php

namespace App\AmazonAds\Traits;

use App\AmazonAds\Models\Portfolio;
use App\Models\SbUser;
use Illuminate\Support\Facades\Log;
trait LoggableFields
{
    private static array $loggableFields = [
        'campaign' => [
            'name',
            'state',
            'type',
            'start_date',
            'end_date',
            'budget_amount',
            'budget_type',
            'targeting_type',
            'dynamic_bidding',
            'company_id',
            'portfolio_id',
            'user_id'
        ],
        'adGroup' => [
            'name',
            'state',
            'default_bid',
            'company_id',
            'user_id'
        ],
        'keyword' => [
            'match_type',
            'state',
            'bid',
            'keyword_text',
            'user_id'
        ],
        'negativeKeyword' => [
            'match_type',
            'state',
            'keyword_text',
            'user_id'
        ],
        'productAd' => [
            'state',
            'custom_text',
            'user_id'
        ],
        'productTargeting' => [
            'state',
            'bid',
            'expression_type',
            'user_id'
        ],
        'negativeProductTargeting' => [
            'state',
            'user_id'
        ]
    ];

    private static array $fieldMappings = [
        'portfolio_id' => [
            'model' => Portfolio::class,
            'name_field' => 'name'
        ],
        'user_id' => [
            'model' => SbUser::class,
            'name_field' => 'fname',
            'last_name_field' => 'lname'
        ]
    ];

    public static function getLoggableFields(string $entityType): array
    {
        return self::$loggableFields[$entityType] ?? [];
    }

    public static function shouldLogField(string $entityType, string $field): bool
    {
        return in_array($field, self::getLoggableFields($entityType));
    }

    public static function getFieldMapping(string $field): ?array
    {
        return self::$fieldMappings[$field] ?? null;
    }

    public static function getMappedValue(string $field, $value)
    {
        $mapping = self::getFieldMapping($field);
        if (!$mapping || !$value) {
            return $value;
        }

        $model = $mapping['model'];
        $nameField = $mapping['name_field'];
        $lastNameField = $mapping['last_name_field'] ?? null;
        $record = $model::find($value);
        return $record ? ($lastNameField ? $record->$nameField . ' ' . $record->$lastNameField : $record->$nameField) : $value;
    }
} 