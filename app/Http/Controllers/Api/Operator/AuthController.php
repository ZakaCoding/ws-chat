<?php

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $email = strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($data['password'], $user->password) || ! $user->is_operator) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }

        $expiresAt = now()->addDays((int) config('chat.operator_token_ttl_days'));
        $token = $user->createToken(trim($data['device_name']), ['chat:read', 'chat:reply'], $expiresAt);

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
            'operator' => ['id' => (string) $user->id, 'name' => $user->name],
        ]]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        return response()->json(['data' => [
            'id' => (string) $user->id,
            'name' => $user->name,
            'is_operator' => true,
            'device' => ['name' => $token->name, 'expires_at' => $token->expires_at?->toISOString()],
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Operator device signed out.']);
    }
}
