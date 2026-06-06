<?php

namespace App;

use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

trait PaginationResponse
{
    protected function formatTraditionalPagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more_pages' => $paginator->hasMorePages(),
            'type' => 'traditional',
        ];
    }

    protected function formatCursorPagination(CursorPaginator $paginator, int $total): array
    {
        return [
            'per_page' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
            'total' => $total,
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'type' => 'cursor',
        ];
    }
}
