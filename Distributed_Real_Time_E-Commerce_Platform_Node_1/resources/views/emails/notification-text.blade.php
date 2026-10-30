{{ $notification->title }}

{{ $notification->body }}
@if (isset($notification->data['order_id']))

Order reference: #{{ $notification->data['order_id'] }}
@endif

-- {{ config('app.name') }}
