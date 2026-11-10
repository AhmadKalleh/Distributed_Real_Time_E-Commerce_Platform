<?php

namespace App\Http\Controllers\Performance;

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Orders\Domain\Entities\Order;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BaselineController
{
    public function products(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $products = Product::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::success([
            'items' => $products->getCollection()
                ->map(fn (Product $product) => $this->productData($product))
                ->all(),
            'pagination' => $this->paginationData($products),
        ], 'Baseline products retrieved successfully.');
    }

    public function showProduct(int $product): JsonResponse
    {
        $found = Product::query()->findOrFail($product);

        return ApiResponse::success(
            $this->productData($found),
            'Baseline product retrieved successfully.'
        );
    }

    public function orders(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $orders = Order::query()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return ApiResponse::success([
            'items' => $orders->getCollection()
                ->map(fn (Order $order) => $this->orderData($order))
                ->all(),
            'pagination' => $this->paginationData($orders),
        ], 'Baseline orders retrieved successfully.');
    }

    public function showOrder(int $order): JsonResponse
    {
        $found = Order::query()->findOrFail($order);

        return ApiResponse::success(
            $this->orderData($found),
            'Baseline order retrieved successfully.'
        );
    }

    private function perPage(Request $request): int
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return (int) ($validated['per_page'] ?? 15);
    }

    private function paginationData(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
        ];
    }

    private function productData(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => $product->price,
            'is_active' => $product->is_active,
            'category' => [
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
    }

    private function orderData(Order $order): array
    {
        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'status' => $order->status->value,
            'total_amount' => $order->total_amount,
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => number_format($item->unit_price * $item->quantity, 2, '.', ''),
            ])->all(),
            'created_at' => $order->created_at?->format('Y-m-d'),
            'updated_at' => $order->updated_at?->format('Y-m-d'),
        ];
    }
}
