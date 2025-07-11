<?php

namespace App\Logger;

use App\Models\Log;

class DatabaseLogger implements LoggerInterface
{
    public function log(string $text, string $type, ?int $entity_id = null): void
    {
        $log = new Log();

        $log->text = $text;
        $log->type = $type;
        $log->entity_id = $entity_id;
        $log->save();
    }
    public function logs(array $logs): void
    {
        Log::query()->insert($logs);
    }
}
