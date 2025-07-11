<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AmazonReportMetric extends Model
{
    protected $table = 'tbl_amazon_report_metrics';
    public $timestamps = false;

    protected $fillable = [
        'statistic_id',
        'metric_name_id',
    ];

    public function value(): MorphTo
    {
        return $this->morphTo('value');
    }

    public function stringValue(): HasOne
    {
        return $this->hasOne(AmazonReportMetricStringValue::class, 'metric_id');
    }

    public function numericValue(): HasOne
    {
        return $this->hasOne(AmazonReportMetricNumericValue::class, 'metric_id');
    }

    public function getValue()
    {
        return match ($this->metric->value_type) {
            'string' => $this->stringValue?->value,
            default => $this->numericValue?->value,
        };
    }

    public function setValue($value): void
    {
        $valueType = $this->metric->value_type;
        
        match ($valueType) {
            'string' => $this->setStringValue($value),
            default => $this->setNumericValue($value),
        };
    }

    private function setStringValue($value): void
    {
        if (!$this->stringValue) {
            $this->stringValue()->create(['value' => $value]);
        } else {
            $this->stringValue->update(['value' => $value]);
        }
    }

    private function setNumericValue($value): void
    {
        if (!$this->numericValue) {
            $this->numericValue()->create(['value' => $value]);
        } else {
            $this->numericValue->update(['value' => $value]);
        }
    }

    public static function createMetric(array $attributes)
    {
        $metric = static::create([
            'statistic_id' => $attributes['statistic_id'],
            'metric_name_id' => $attributes['metric_name_id'],
        ]);

        $metric->setValue($attributes['value']);

        return $metric;
    }

    public function statistic(): BelongsTo
    {
        return $this->belongsTo(AmazonReportStatistic::class, 'statistic_id');
    }

    public function metricName(): BelongsTo
    {
        return $this->belongsTo(AmazonMetricName::class, 'metric_name_id');
    }

    public function scopeMetric(Builder $query, string $metricName): Builder
    {
        return $query->whereHas('metricName', function ($query) use ($metricName) {
            $query->where('name', $metricName);
        });
    }

    public function scopeForDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereHas('statistic', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('start_date', [$startDate, $endDate]);
        });
    }

    public function getFormattedValueAttribute(): string
    {
        $value = $this->getValue();
        return $this->metricName->formatValue($value);
    }
} 