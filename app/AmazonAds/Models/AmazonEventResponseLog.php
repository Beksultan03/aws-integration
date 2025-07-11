<?php

namespace App\AmazonAds\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonEventResponseLog extends Model
{
    use HasFactory;

    protected $table = 'tbl_amazon_event_response_log';

    protected $fillable = [
        'event_dispatch_id', 
        'http_status', 
        'response_data', 
        'error_message',
        'entity_id',
        'entity_type'
    ];

    protected $casts = [
        'response_data' => 'array',
    ];
    
    /**
     * Get the dispatch log that owns this response
     */
    public function dispatchLog()
    {
        return $this->belongsTo(AmazonEventDispatchLog::class, 'event_dispatch_id');
    }
    
    /**
     * Get the related entity based on entity_type
     */
    public function entity()
    {
        return $this->morphTo();
    }
    
    /**
     * Get all responses for a specific entity
     * 
     * @param string $entityType
     * @param mixed $entityId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getResponsesForEntity($entityType, $entityId)
    {
        return self::where('entity_type', $entityType)
                  ->where('entity_id', $entityId)
                  ->select('http_status', 'response_data', 'error_message')
                  ->get();
    }
    
    /**
     * Get all error responses
     */
    public static function getAllErrors()
    {
        return self::where(function($query) {
                $query->whereNotNull('error_message')
                      ->orWhere('http_status', '>=', 400);
            })
            ->with('dispatchLog')
            ->get();
    }
}
