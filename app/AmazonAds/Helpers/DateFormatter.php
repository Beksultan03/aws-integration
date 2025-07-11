<?php

namespace App\AmazonAds\Helpers;

class DateFormatter
{
    /**
     * Convert ISO 8601 date to MySQL datetime format
     */
    public static function formatDateTime(?string $dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime($dateTime));
    }

    public static function formatDateToAmazon(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return date('Y-m-d', strtotime($date));
    }
} 