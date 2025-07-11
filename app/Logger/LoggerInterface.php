<?php

namespace App\Logger;

interface LoggerInterface
{
    public function log(string $text, string $type, ?int $entity_id = null);
    public function logs(array $logs);
}
