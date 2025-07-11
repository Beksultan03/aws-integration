<?php

namespace App\BlueOcean\Mapper;

class Compare
{
    public function __construct(public Specification $base, public Specification $upgraded) {}

    public function compare(): array
    {
        $upgraded = $this->upgraded->toArray();
        $base = $this->base->toArray();

        if (isset($base['additional_storage'])) {
          unset($base['storage']);
        }

        return array_diff(
            $upgraded,
            $base,
        );
    }
}
