<?php

namespace App;

use Illuminate\Pagination\CursorPaginator;

trait ApiResponse
{
    use PaginationResponse;

    protected function successResponse($data = null, string $message = 'Success', int $status = 200, array $meta = [])
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    protected function errorResponse(string $message = 'Something went wrong', int $status = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    protected function paginatedResponse($data, array $pagination = [], string $message = 'Success', int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination,
        ], $status);
    }

    protected function cursorPaginatedResponse($data, CursorPaginator $paginator, string $message = 'Success', int $total, int $status = 200)
    {
        return $this->paginatedResponse(
            $data,
            $this->formatCursorPagination($paginator,$total),
            $message,
            $status
        );
    }
}
