<?php

namespace App\BlueOcean\Mapper;

class Specification
{
    public function __construct(
        public string $displayTitle,
        public string $ram,
        public string $storage,
        public string $gpu,
        public string $os,
        public string $cpu,
    ) {}

    public function toArray(): array
    {
        $specifications = [
            'display_title' => $this->displayTitle,
            'ram' => $this->ram,
            'gpu' => $this->gpu,
            'os' => $this->os,
            'cpu' => $this->cpu,
        ];

        return array_merge(
            $specifications,
            $this->generateStorage()
        );
    }

    public static function fromArray(array $specification): self
    {
        return new self(
            $specification['display_title'],
            $specification['ram'],
            $specification['storage'],
            $specification['gpu'],
            $specification['os'],
            $specification['cpu'],
        );
    }

    private function generateStorage(): array
    {
        $specifications = [];

        $storage = str_replace('  ', ' ', str_replace(['M.2', ' 2.5'], '', $this->storage));
        $storage = explode('+', $storage);

        $specifications['storage'] = trim($storage[0]);
        $specifications['additional_storage'] = trim($storage[1] ?? null);

        return $specifications;
    }
}
