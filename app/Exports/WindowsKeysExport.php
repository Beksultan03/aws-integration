<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class WindowsKeysExport implements FromView
{
    public function __construct(private array $keys)
    {
        $this->keys = $keys;
    }

    public function view(): View
    {
        return view('exports.windows_keys', [
            'keys' => $this->keys
        ]);
    }
}
