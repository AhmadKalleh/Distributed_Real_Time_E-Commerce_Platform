<?php

namespace App\Http\Controllers\Performance;

use App\Modules\Orders\Domain\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

final class SqlOptimizedQueries
{
    public function products(int $perPage, ?Cursor $cursor, Request $request): CursorPaginator
    {
        [$predicate, $cursorBindings, $direction] = $this->cursorClause($cursor, 'p.id');
        $rows = DB::select(
            "SELECT p.id, p.category_id, p.name, p.slug, p.price,
                    p.is_active, p.created_at, p.updated_at,
                    c.id AS joined_category_id, c.name AS category_name, c.slug AS category_slug
            FROM products AS p
            LEFT JOIN categories AS c ON c.id = p.category_id
            WHERE p.is_active = ? {$predicate}
            ORDER BY p.id {$direction}
            LIMIT ?",
            array_merge([1], $cursorBindings, [$perPage + 1])
        );

        $imagesByProduct = $this->imagesForProducts(array_map(
            fn (object $row) => (int) $row->id,
            array_slice($rows, 0, $perPage)
        ));

        $items = array_map(fn (object $row) => $this->productPayload(
            $row,
            $imagesByProduct[(int) $row->id] ?? [],
            false
        ), $rows);

        return $this->paginator($items, $perPage, $cursor, $request);
    }

    public function product(int $id): array
    {
        $rows = DB::select(
            "SELECT p.id, p.category_id, p.name, p.slug, p.description, p.price,
                    p.is_active, p.created_at, p.updated_at,
                    c.id AS joined_category_id, c.name AS category_name, c.slug AS category_slug
            FROM products AS p
            LEFT JOIN categories AS c ON c.id = p.category_id
            WHERE p.id = ?
            LIMIT 1",
            [$id]
        );

        abort_if($rows === [], 404);

        $row = $rows[0];
        $images = $this->imagesForProducts([(int) $row->id]);

        return $this->productPayload($row, $images[(int) $row->id] ?? [], true);
    }

    public function orders(int $perPage, ?Cursor $cursor, Request $request): CursorPaginator
    {
        [$predicate, $cursorBindings, $direction] = $this->cursorClause($cursor, 'id');
        $orders = DB::select(
            "SELECT id, user_id, status, total_amount, created_at, updated_at
            FROM orders
            WHERE 1 = 1 {$predicate}
            ORDER BY id {$direction}
            LIMIT ?",
            array_merge($cursorBindings, [$perPage + 1])
        );

        $itemsByOrder = $this->itemsForOrders(array_map(
            fn (object $order) => (int) $order->id,
            array_slice($orders, 0, $perPage)
        ));

        $payload = array_map(fn (object $order) => $this->orderPayload(
            $order,
            $itemsByOrder[(int) $order->id] ?? []
        ), $orders);

        return $this->paginator($payload, $perPage, $cursor, $request);
    }

    public function order(int $id): array
    {
        $orders = DB::select(
            'SELECT id, user_id, status, total_amount, created_at, updated_at FROM orders WHERE id = ? LIMIT 1',
            [$id]
        );

        abort_if($orders === [], 404);

        $items = $this->itemsForOrders([$id]);

        return $this->orderPayload($orders[0], $items[$id] ?? []);
    }

    private function cursorClause(?Cursor $cursor, string $column): array
    {
        if ($cursor === null) {
            return ['', [], 'DESC'];
        }

        if ($cursor->pointsToNextItems()) {
            return ["AND {$column} < ?", [(int) $cursor->parameter('id')], 'DESC'];
        }

        return ["AND {$column} > ?", [(int) $cursor->parameter('id')], 'ASC'];
    }

    private function paginator(array $items, int $perPage, ?Cursor $cursor, Request $request): CursorPaginator
    {
        return new CursorPaginator($items, $perPage, $cursor, [
            'path' => $request->url(),
            'query' => $request->except('cursor'),
            'cursorName' => 'cursor',
            'parameters' => ['id'],
        ]);
    }

    private function imagesForProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($productIds), '?'));
        $rows = DB::select(
            "SELECT id, product_id, file_name, path, sort_order
            FROM product_images
            WHERE product_id IN ({$placeholders})
            ORDER BY product_id, sort_order, id",
            $productIds
        );

        $images = [];
        foreach ($rows as $row) {
            $images[(int) $row->product_id][] = [
                'id' => (int) $row->id,
                'file_name' => $row->file_name,
                'path' => $row->path,
                'sort_order' => (int) $row->sort_order,
            ];
        }

        return $images;
    }

    private function itemsForOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($orderIds), '?'));
        $rows = DB::select(
            "SELECT oi.id, oi.order_id, oi.product_id, oi.quantity, oi.unit_price, p.name AS product_name
            FROM order_items AS oi
            LEFT JOIN products AS p ON p.id = oi.product_id
            WHERE oi.order_id IN ({$placeholders})
            ORDER BY oi.order_id, oi.id",
            $orderIds
        );

        $items = [];
        foreach ($rows as $row) {
            $quantity = (int) $row->quantity;
            $unitPrice = (float) $row->unit_price;
            $items[(int) $row->order_id][] = [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'quantity' => $quantity,
                'unit_price' => $row->unit_price,
                'line_total' => number_format($unitPrice * $quantity, 2, '.', ''),
            ];
        }

        return $items;
    }

    private function productPayload(object $row, array $images, bool $includeDescription): array
    {
        $payload = [
            'id' => (int) $row->id,
            'name' => $row->name,
            'slug' => $row->slug,
            'price' => $row->price,
            'is_active' => (bool) $row->is_active,
            'category' => $row->joined_category_id === null ? null : [
                'id' => (int) $row->joined_category_id,
                'name' => $row->category_name,
                'slug' => $row->category_slug,
            ],
            'images' => $images,
            'created_at' => $this->date($row->created_at),
            'updated_at' => $this->date($row->updated_at),
        ];

        if ($includeDescription) {
            $payload['description'] = $row->description;
        }

        return $payload;
    }

    private function orderPayload(object $row, array $items): array
    {
        $status = OrderStatus::from($row->status);

        return [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'status' => $status->value,
            'allowed_next_statuses' => array_map(
                fn (OrderStatus $next) => $next->value,
                $status->allowedTransitions()
            ),
            'total_amount' => $row->total_amount,
            'items' => $items,
            'created_at' => $this->date($row->created_at),
            'updated_at' => $this->date($row->updated_at),
        ];
    }

    private function date(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 10);
    }
}
