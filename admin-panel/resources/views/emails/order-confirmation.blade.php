<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, sans-serif; padding: 24px;">
    <h2>Order Confirmed!</h2>
    <p>Order <strong>{{ $order->order_number }}</strong> has been confirmed.</p>

    <table style="width:100%; border-collapse: collapse; margin: 16px 0;">
        <tr style="background:#f5f5f5;">
            <th style="text-align:left; padding:8px; border:1px solid #ddd;">Item</th>
            <th style="padding:8px; border:1px solid #ddd;">Qty</th>
            <th style="text-align:right; padding:8px; border:1px solid #ddd;">Total</th>
        </tr>
        @foreach ($order->items as $item)
        <tr>
            <td style="padding:8px; border:1px solid #ddd;">{{ $item->name }}</td>
            <td style="padding:8px; border:1px solid #ddd; text-align:center;">{{ $item->quantity }}</td>
            <td style="padding:8px; border:1px solid #ddd; text-align:right;">{{ $item->price * $item->quantity }} ⭐</td>
        </tr>
        @endforeach
    </table>

    <p><strong>Total:</strong> {{ $order->total_amount }} ⭐</p>
    <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>

    @if ($order->shipping_address)
    <p><strong>Shipping to:</strong> {{ $order->shipping_address }}</p>
    @endif

    <p style="color:#666; font-size:14px;">Thank you for your order!</p>
</body>
</html>
