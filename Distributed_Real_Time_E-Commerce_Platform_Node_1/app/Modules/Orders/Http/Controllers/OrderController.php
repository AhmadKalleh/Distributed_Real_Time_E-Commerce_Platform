<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Http\Requests\StoreOrderRequest;
use App\Modules\Orders\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Orders\Http\Resources\OrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Support\Js;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $validated = $request->validate([
            'status'   => ['nullable', Rule::enum(OrderStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // استدعاء الخدمة لجلب البيانات
        $orders = $this->orders->getPaginatedOrders($request->user(), $validated);

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

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->place($request->user(), $request->validated('items'));
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

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->changeStatus($order, OrderStatus::from($request->validated('status')));

        return ApiResponse::success(
            new OrderResource($order->load('items.product')),
            'Order status updated successfully.'
        );
    }
}
