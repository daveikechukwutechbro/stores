<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmation;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['orders' => $orders]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order->load('items.product', 'user');

        return response()->json(['order' => $order]);
    }

    public function createInvoice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'nullable|string',
            'phone' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $cartItems = CartItem::where('user_id', $user->id)
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        foreach ($cartItems as $item) {
            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'message' => "Insufficient stock for {$item->product->name}",
                ], 400);
            }
        }

        $totalAmount = $cartItems->sum(fn ($item) => $item->product->price * $item->quantity);
        $orderNumber = Order::generateOrderNumber();

        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            return response()->json(['message' => 'Telegram bot not configured'], 500);
        }

        $itemNames = $cartItems->map(fn ($i) => $i->product->name)->join(', ');

        $response = Http::post("https://api.telegram.org/bot{$botToken}/createInvoiceLink", [
            'title' => "Order #{$orderNumber}",
            'description' => mb_substr($itemNames, 0, 255),
            'payload' => json_encode([
                'user_id' => (string) $user->id,
                'order_number' => $orderNumber,
                'timestamp' => now()->timestamp,
            ]),
            'provider_token' => '',
            'currency' => 'XTR',
            'prices' => [
                ['label' => 'Total', 'amount' => $totalAmount],
            ],
        ]);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Failed to create invoice',
                'error' => $response->json(),
            ], 500);
        }

        $invoiceLink = $response->json('result');

        return response()->json([
            'invoice_link' => $invoiceLink,
            'order_number' => $orderNumber,
            'total_amount' => $totalAmount,
        ]);
    }

    public function paymentSuccess(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
            'transaction_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $cartItems = CartItem::where('user_id', $user->id)
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $totalAmount = $cartItems->sum(fn ($item) => $item->product->price * $item->quantity);

        $order = Order::create([
            'order_number' => $request->input('order_number'),
            'user_id' => $user->id,
            'status' => 'confirmed',
            'total_amount' => $totalAmount,
            'shipping_address' => $request->input('shipping_address', $user->address),
            'phone' => $request->input('phone', $user->phone),
            'note' => $request->input('note'),
            'transaction_id' => $request->input('transaction_id'),
            'payment_method' => 'telegram_stars',
            'estimated_delivery' => now()->addDays(rand(3, 7)),
        ]);

        foreach ($cartItems as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'price' => $item->product->price,
                'quantity' => $item->quantity,
                'image' => $item->product->image,
            ]);

            $item->product->decrement('stock', $item->quantity);
        }

        CartItem::where('user_id', $user->id)->delete();

        $order->load('items');

        try {
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new OrderConfirmation($order));
            }
            Mail::to($user->email)->send(new OrderConfirmation($order));
        } catch (\Throwable $e) {
            // mail silent fail
        }

        return response()->json([
            'message' => 'Payment successful',
            'order' => $order,
        ], 201);
    }

    public function refund(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $order = Order::where('transaction_id', $request->input('transaction_id'))
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (in_array($order->status, ['refunded', 'cancelled'])) {
            return response()->json(['message' => 'Order has already been refunded'], 400);
        }

        $order->update(['status' => 'refunded']);

        foreach ($order->items as $item) {
            Product::where('id', $item->product_id)->increment('stock', $item->quantity);
        }

        if ($order->telegram_charge_id) {
            $botToken = config('services.telegram.bot_token');
            Http::post("https://api.telegram.org/bot{$botToken}/refundStarPayment", [
                'user_id' => $order->user->telegram_id,
                'telegram_payment_charge_id' => $order->telegram_charge_id,
            ]);
        }

        return response()->json([
            'message' => 'Refund processed successfully',
            'order' => $order,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $update = $request->all();

        if (isset($update['pre_checkout_query'])) {
            $query = $update['pre_checkout_query'];
            $botToken = config('services.telegram.bot_token');

            Http::post("https://api.telegram.org/bot{$botToken}/answerPreCheckoutQuery", [
                'pre_checkout_query_id' => $query['id'],
                'ok' => true,
            ]);

            return response()->json(['ok' => true]);
        }

        if (isset($update['successful_payment'])) {
            $payment = $update['successful_payment'];
            $payload = json_decode($payment['invoice_payload'], true);

            if (isset($payload['order_number'])) {
                $order = Order::where('order_number', $payload['order_number'])->first();
                if ($order) {
                    $order->update([
                        'telegram_charge_id' => $payment['telegram_payment_charge_id'],
                    ]);
                }
            }

            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => true]);
    }
}
