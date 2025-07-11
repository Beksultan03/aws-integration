<?php

namespace App\Services\Pagination;

class PaginationService
{
    public function paginate($query, $page, $per_page)
    {
        $perPage = $per_page ?? 10;
        $page = $page ?? 1;
        $query->paginate($perPage, ['*'], 'page', $page);

        return $query;
    }

    public function getInfo($items): array
    {
        return [
            'pagination' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total_pages' => $items->lastPage(),
                'total_items' => $items->total(),
            ]
        ];
    }
}
