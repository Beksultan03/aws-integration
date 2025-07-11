<?php

namespace App\Listeners\Logs;

use App\Models\SbUser;
use App\Models\WindowsKey;
use App\BlueOcean\Exceptions\ApiException;
use App\Logger\LoggerInterface;

abstract class WindowsLogListener
{
    public function __construct(protected LoggerInterface $logger) {}

    protected function getUser(int $user_id): SbUser
    {
        $user = SbUser::find($user_id);

        if (!$user) {
            throw new ApiException('User with this ID is not active.');
        }
        return $user;
    }

    protected function logEvent(string $eventName, string $messageTemplate, SbUser $user = null, ?array $entityIds = null): void
    {
        $userName = isset($user->full_name) ? $user->full_name : '';
        $logs = array_map(function ($entityId) use ($userName, $messageTemplate) {
            return [
                'entity_id' => $entityId,
                'text' => sprintf($messageTemplate, $userName),
                'type' => WindowsKey::class,
            ];
        }, $entityIds);

        if (!empty($logs)) {
            $this->logger->logs($logs);
        }
    }

    protected function logSingleEvent(string $eventName, string $messageTemplate, int $entityId, SbUser $user = null): void
    {
        $userName = $user->full_name;
        $log = $this->createLogEntry($entityId, $userName, $messageTemplate);

        $this->logger->log($log['text'], $log['type'], $log['entity_id']);
    }

    private function createLogEntry(int $entityId, string $userName, string $messageTemplate): array
    {
        return [
            'entity_id' => $entityId,
            'text' => sprintf($messageTemplate, $userName),
            'type' => WindowsKey::class,
        ];
    }
}
