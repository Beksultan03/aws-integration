<?php

namespace App\AmazonAds\Enums;

enum EventLogStatus:string
{
    case PROCESSING = 'processing';
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

}
