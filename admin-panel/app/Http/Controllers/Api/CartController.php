<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cartItems = CartItem::where('user_id', $request->user()->id)
            ->with('product.category')
            ->get();

        $total = $cartItems->sum(fn ($item) => $item->product->price * $item->quantity);

        return response()->json([
            'items' => $cartItems,
            'total' => $total,
            'count' => $cartItems->sum('quantity'),
        ]);
    }

    public function add(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'integer|min:1|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($request->input('product_id'));

        if (!$product->isInStock()) {
            return response()->json(['message' => 'Product is out of stock'], 400);
        }

        $cartItem = CartItem::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
            ],
            [
                'quantity' => $request->input('quantity', 1),
            ]
        );

        $cartItem->load('product.category');

        return response()->json([
            'message' => 'Item added to cart',
            'item' => $cartItem,
        ]);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        if ($cartItem->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $cartItem->update(['quantity' => $request->input('quantity')]);
        $cartItem->load('product.category');

        return response()->json([
            'message' => 'Cart updated',
            'item' => $cartItem,
        ]);
    }

    public function remove(Request $request, CartItem $cartItem): JsonResponse
    {
        if ($cartItem->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $cartItem->delete();

        return response()->json(['message' => 'Item removed from cart']);
    }

    public function clear(Request $request): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Cart cleared']);
    }
}
