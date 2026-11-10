<?php

namespace App\Http\Controllers\Performance;

use App\Modules\Catalog\Application\Caching\CatalogCacheKeys;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Domain\Entities\OrderItem;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Shared\Contracts\CacheStore;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

final class OptimizedPerformanceController
{
    public function __construct(
        private readonly EloquentOptimizedQueries $eloquent,
    ) {
    }

    public function products(
        Request $request,
        CacheStore $cache,
    ): JsonResponse {
        [$perPage, $cursor] = $this->paginationInput($request);

        $key = CatalogCacheKeys::productList(
            $cache->version(CatalogCacheKeys::PRODUCTS_GROUP),
            $request->query(),
        );

        $payload = $cache->remember(
            $key,
            CatalogCacheKeys::LIST_TTL,
            function () use ($perPage, $cursor, $request) {
                $paginator = $this->eloquent->products(
                    $perPage,
                    $cursor,
                    $request
                );

                $items = $paginator->getCollection()
                    ->map(fn (Product $product) => $this->productPayload($product))
                    ->all();

                return [
                    'items' => $items,
                    'pagination' => $this->paginationPayload($paginator),
                ];
            }
        );

        return ApiResponse::success(
            $payload,
            'Optimized products retrieved successfully.'
        );
    }

    public function showProduct(
        int $product,
        CacheStore $cache,
    ): JsonResponse {
        $payload = $cache->remember(
            CatalogCacheKeys::product($product),
            CatalogCacheKeys::PRODUCT_TTL,
            function () use ($product) {
                $found = $this->eloquent->product($product);

                return $found instanceof Product
                    ? $this->productPayload($found, true)
                    : $found;
            }
        );

        return ApiResponse::success(
            $payload,
            'Optimized product retrieved successfully.'
        );
    }

    public function orders(Request $request): JsonResponse
    {
        [$perPage, $cursor] = $this->paginationInput($request);

        $paginator = $this->eloquent->orders(
            $perPage,
            $cursor,
            $request
        );

        $items = $paginator->getCollection()
            ->map(fn (Order $order) => $this->orderPayload($order))
            ->all();

        return ApiResponse::success([
            'items' => $items,
            'pagination' => $this->paginationPayload($paginator),
        ], 'Optimized orders retrieved successfully.');
    }

    public function showOrder(int $order): JsonResponse
    {
        $found = $this->eloquent->order($order);

        $payload = $found instanceof Order
            ? $this->orderPayload($found)
            : $found;

        return ApiResponse::success(
            $payload,
            'Optimized order retrieved successfully.'
        );
    }

    private function paginationInput(Request $request): array
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $cursor = OptimizedCursor::decode($validated['cursor'] ?? null);

        return [$perPage, $cursor];
    }

    private function paginationPayload(CursorPaginator $paginator): array
    {
        return [
            'per_page' => $paginator->perPage(),
            'has_more_pages' => $paginator->hasMorePages(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'previous_cursor' => $paginator->previousCursor()?->encode(),
        ];
    }

    private function productPayload(
        Product $product,
        bool $includeDescription = false
    ): array {
        $payload = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'is_active' => $product->is_active,
            'category' => $product->category === null ? null : [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ],
            'images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'file_name' => $image->file_name,
                'path' => $image->path,
                'sort_order' => $image->sort_order,
            ])->all(),
            'created_at' => $product->created_at?->format('Y-m-d'),
            'updated_at' => $product->updated_at?->format('Y-m-d'),
        ];

        if ($includeDescription) {
            $payload['description'] = $product->description;
        }

        return $payload;
    }

    private function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'status' => $order->status->value,
            'allowed_next_statuses' => array_map(
                fn (OrderStatus $next) => $next->value,
                $order->status->allowedTransitions()
            ),
            'total_amount' => $order->total_amount,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => number_format(
                    $item->unit_price * $item->quantity,
                    2,
                    '.',
                    ''
                ),
            ])->all(),
            'created_at' => $order->created_at?->format('Y-m-d'),
            'updated_at' => $order->updated_at?->format('Y-m-d'),
        ];
    }
}
