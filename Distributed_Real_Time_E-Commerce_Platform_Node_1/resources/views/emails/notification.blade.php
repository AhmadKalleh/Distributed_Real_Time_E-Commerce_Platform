<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $notification->title }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr>
        <td style="padding:24px;">
            <h1 style="margin:0 0 12px;font-size:20px;">{{ $notification->title }}</h1>
            <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">{{ $notification->body }}</p>
            @if (isset($notification->data['order_id']))
                <p style="margin:0;font-size:13px;color:#616e7c;">Order reference: #{{ $notification->data['order_id'] }}</p>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:12px 24px;border-top:1px solid #e4e7eb;font-size:12px;color:#9aa5b1;">
            {{ config('app.name') }}
        </td>
    </tr>
</table>
</body>
</html>
