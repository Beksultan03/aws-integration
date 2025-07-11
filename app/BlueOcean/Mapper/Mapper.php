<?php

namespace App\BlueOcean\Mapper;

use Exception;

class Mapper
{
    public const TYPES = [
        'os' => 'Software',
        'storage' => 'Primary Drive',
        'additional_storage' => '2nd Drive',
        'ram' => 'Memory',
    ];

    protected $software = [
        'Windows 11 Pro' => 'Windows 11Pro',
        'Windows 10 Pro' => 'Win10 Pro',
        'Windows 11 Home' => 'Windows 11H',
        'Windows 10 Home' => 'Win10 Home',
    ];

    protected array $memory = [
        '64GB' => 'TRS-64GB',
        '48GB' => 'TRS-48GB',
        '40GB' => 'TRS-40GB',
        '36GB' => 'TRS-36GB',
        '32GB' => 'TRS-32GB',
        '24GB' => 'TRS-24GB',
        '20GB' => 'TRS-20GB',
        '16GB' => 'TRS-16GB',
    ];
    private array $handlers;

    private array $customHandlers;

    public function __construct()
    {
        $this->handlers = [
            'os' => $this->createHandler('os'),
        ];

        $this->customHandlers = [
            'ram' => function($value)  {
                $data = [
                    'name' => self::TYPES['ram'],
                    'items' => [],
                ];

                $data['items'][] = [
                    'name' => $value === '8GB'
                        ? $value
                        : $this->getValue('ram', $value)
                ];

                return $data;
            },
            'storage' => function($value)  {
                $data = [
                    'name' => self::TYPES['storage'],
                    'items' => [],
                ];

                preg_match('/[0-9]+(gb|tb)/i', strtolower($value), $matches);
                if (empty($matches)) {
                    throw new Exception('Problem with storage.');
                }

                $type = str_contains($value, 'HDD') ? 'HDD' : 'PCIe';

                $amount = strtoupper($matches[0]) . ' ' . $type;

                $data['items'][] = [
                    'name' => $amount,
                ];

                return $data;
            },
            'additional_storage' => function($value)  {
                $data = [
                    'name' => self::TYPES['additional_storage'],
                    'items' => [],
                ];

                preg_match('/[0-9]+(gb|tb)/', strtolower($value), $matches);

                if (empty($matches)) {
                    throw new Exception('Problem with additional storage.');
                }

                $type = str_contains($value, 'HDD') ? 'HDD' : 'PCIe';
                $amount = strtoupper($matches[0]) . ' ' . $type;

                $data['items'][] = [
                    'name' => $amount,
                ];

                return $data;
            }
        ];
    }

    public function map(Compare $compare): array
    {
        $specifications = [];

        foreach ($compare->compare() as $specification => $value) {
            if(!($value ?? false)) {
                // Ignore empty values
                // ToDo: log this
                continue;
            }

             if  ($this->handlers[$specification] ?? false) {
                 $specifications[] = $this->handlers[$specification]($value);
             }

             if ($this->customHandlers[$specification] ?? false) {
                 $specifications[] = $this->customHandlers[$specification]($value);
             }
        }

        return $specifications;
    }

    private function createHandler(string $type): callable
    {
        if (isset($this->customHandlers[$type])) {
            return $this->customHandlers[$type];
        }

        return function($value) use ($type) {
            return [
                'name' => self::TYPES[$type],
                'items' => [
                    [
                        'name' => $this->getValue($type, $value)
                    ]
                ],
            ];
        };
    }

    private function getValue($type, $value)
    {
        if (!isset(self::TYPES[$type])) {
            throw new Exception('Unknown type: '.$type);
        }

        $type = self::TYPES[$type];
        $type = str_replace(' ', '', lcfirst($type));

        if (!isset($this->{$type}[$value])) {
            return $value;
        }

        return $this->{$type}[$value];
    }
}
