<?php

namespace App\Modules\Orders\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Presentation\Http\Requests\StoreOrderRequest;
use App\Modules\Orders\Presentation\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Orders\Presentation\Http\Resources\OrderResource;
use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Application\UseCases\ListOrdersUseCase;
use App\Modules\Orders\Application\UseCases\PlaceOrderUseCase;
use App\Modules\Orders\Application\UseCases\ChangeOrderStatusUseCase;
use App\Modules\Orders\Application\DTOs\Orders\ChangeOrderStatusDTO;
use App\Modules\Orders\Application\DTOs\Orders\ListOrdersDTO;
use App\Modules\Orders\Application\DTOs\Orders\PlaceOrderDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Shared\Http\Responses\ApiResponse;

class OrderController extends Controller
{
    public function index(Request $request, ListOrdersUseCase $useCase): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validate([
            'status'   => ['nullable', Rule::enum(OrderStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // استدعاء الخدمة لجلب البيانات
        $orders = $useCase->execute(new ListOrdersDTO($request->user(), $validated));

        // التحقق مما إذا كانت الطلبات فارغة لتخصيص الرسالة
        $message = $orders->isEmpty()
            ? 'No orders found.'
            : 'Orders retrieved successfully.';

        return ApiResponse::success([
            'orders' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
                'has_more'     => $orders->hasMorePages(),
                'next_page_url' => $orders->nextPageUrl(),     // رابط الصفحة التالية (سيكون null إذا كانت الأخيرة)
                'prev_page_url' => $orders->previousPageUrl(), // رابط الصفحة السابقة (سيكون null إذا كانت الأولى)
            ]
        ], $message);
    }

    public function store(StoreOrderRequest $request, PlaceOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute(new PlaceOrderDTO($request->user(), $request->validated('items')));
        $order->load('items.product');

        return ApiResponse::success(
            new OrderResource($order),
            'Order created successfully.',
            201 // تحديد الكود 201
        )->header('Location', url("/api/v1/orders/{$order->id}"));
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        // تحميل العلاقات المطلوبة
        $order->load('items.product');

        return ApiResponse::success(
            new OrderResource($order),
            'Order retrieved successfully.'
        );

    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, ChangeOrderStatusUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute(new ChangeOrderStatusDTO(
            $order,
            OrderStatus::from($request->validated('status')),
        ));

        return ApiResponse::success(
            new OrderResource($order->load('items.product')),
            'Order status updated successfully.'
        );
    }
}
