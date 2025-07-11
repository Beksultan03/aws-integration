<?php

namespace App\Services\Sentry;

use Sentry\Event;

class SentryBeforeSendFilter
{
    private string $cache_path;

    public function __construct()
    {
        $this->cache_path = storage_path('logs/sentry_cache.json');
    }

    public function __invoke(Event $event): ?Event
    {
        $event_exceptions = $event->getExceptions();

        $event_hash = md5(json_encode($event_exceptions));

        \Illuminate\Log\log('SENTRY ERROR ' . $event_hash . print_r($event_exceptions, true));

        $cache = $this->load_cache();

        if (array_key_exists($event_hash, $cache)) {
            $cache[$event_hash] += 1;

            $this->save_cache($cache);

            return null;
        }

        $cache[$event_hash] = 1;

        $this->save_cache($cache);

        return $event;
    }

    private function load_cache()
    {
        if (!file_exists($this->cache_path)) {
            return [];
        }

        $content = file_get_contents($this->cache_path);
        return json_decode($content, true) ?: [];
    }

    private function save_cache($cache): void
    {
        file_put_contents($this->cache_path, json_encode($cache, JSON_PRETTY_PRINT));
    }
}
