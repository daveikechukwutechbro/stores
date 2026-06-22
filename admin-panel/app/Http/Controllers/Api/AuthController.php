<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function telegramLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'init_data' => 'required|string',
            'telegram_id' => 'required|string',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'username' => 'nullable|string',
            'photo_url' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $botToken = config('services.telegram.bot_token');
        if (!$this->verifyTelegramInitData($request->input('init_data'), $botToken)) {
            return response()->json(['message' => 'Invalid Telegram authentication'], 401);
        }

        $user = User::updateOrCreate(
            ['telegram_id' => $request->input('telegram_id')],
            [
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'username' => $request->input('username'),
                'avatar_url' => $request->input('photo_url'),
                'name' => trim($request->input('first_name', '') . ' ' . $request->input('last_name', '')),
            ]
        );

        $token = $user->createToken('telegram-mini-app')->plainTextToken;

        return response()->json([
            'message' => 'Authenticated successfully',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'phone' => $request->input('phone'),
        ]);

        $token = $user->createToken('web-store')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('web-store')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('cartItems.product'),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user->update($request->only('first_name', 'last_name', 'phone', 'address'));

        return response()->json([
            'message' => 'Profile updated',
            'user' => $user,
        ]);
    }

    private function verifyTelegramInitData(string $initData, string $botToken): bool
    {
        $params = [];
        parse_str($initData, $params);

        if (!isset($params['hash'])) {
            return false;
        }

        $hash = $params['hash'];
        unset($params['hash']);

        ksort($params);
        $checkString = implode("\n", array_map(
            fn ($k, $v) => "$k=$v",
            array_keys($params),
            $params
        ));

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expectedHash = hash_hmac('sha256', $checkString, $secretKey);

        return hash_equals($expectedHash, $hash);
    }
}
