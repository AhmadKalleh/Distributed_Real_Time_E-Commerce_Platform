<?php

namespace App\Http\Controllers\Performance;

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Orders\Domain\Entities\Order;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;

final class EloquentOptimizedQueries
{
    public function products(int $perPage, ?Cursor $cursor, Request $request): CursorPaginator
    {
        return Product::query()
            ->select([
                'id',
                'category_id',
                'name',
                'slug',
                'price',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->where('is_active', true)
            ->with([
                'category:id,name,slug',
                'images' => fn (Relation  $query) => $query->select([
                    'id',
                    'product_id',
                    'file_name',
                    'path',
                    'sort_order',
                ]),
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor)
            ->appends($request->except('cursor'));
    }

    public function product(int $id): Product
    {
        return Product::query()
            ->select([
                'id',
                'category_id',
                'name',
                'slug',
                'description',
                'price',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->with([
                'category:id,name,slug',
                'images' => fn (Relation  $query) => $query->select([
                    'id',
                    'product_id',
                    'file_name',
                    'path',
                    'sort_order',
                ]),
            ])
            ->findOrFail($id);
    }

    public function orders(int $perPage, ?Cursor $cursor, Request $request): CursorPaginator
    {
        return Order::query()
            ->select([
                'id',
                'user_id',
                'status',
                'total_amount',
                'created_at',
                'updated_at',
            ])
            ->with([
                'items' => fn (Relation  $query) => $query
                    ->select(['id', 'order_id', 'product_id', 'quantity', 'unit_price'])
                    ->orderBy('id')
                    ->with('product:id,name'),
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor)
            ->appends($request->except('cursor'));
    }

    public function order(int $id): Order
    {
        return Order::query()
            ->select([
                'id',
                'user_id',
                'status',
                'total_amount',
                'created_at',
                'updated_at',
            ])
            ->with([
                'items' => fn (Relation  $query) => $query
                    ->select(['id', 'order_id', 'product_id', 'quantity', 'unit_price'])
                    ->orderBy('id')
                    ->with('product:id,name'),
            ])
            ->findOrFail($id);
    }
}
