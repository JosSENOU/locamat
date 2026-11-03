<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'telephone' => $validated['telephone'] ?? null,
            'role' => $validated['role'] ?? User::ROLE_LOCATAIRE,
        ]);

        return response()->json([
            'user' => UserResource::make($user)->resolve($request),
            'token' => $user->createToken('api')->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects.',
                'errors' => (object) [],
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Ce compte est désactivé. Contactez l’assistance pour obtenir de l’aide.',
                'errors' => (object) [],
            ], 403);
        }

        return response()->json([
            'user' => UserResource::make($user)->resolve($request),
            'token' => $user->createToken('api')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $oldPhoto = $user->photo;
        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('profiles', 'public');

            if ($photoPath === false) {
                throw new RuntimeException('Impossible d’enregistrer la photo de profil.');
            }
        }

        $attributes = [
            'name' => $validated['name'],
            'telephone' => $validated['telephone'] ?? null,
        ];

        if ($photoPath !== null) {
            $attributes['photo'] = $photoPath;
        } elseif (($validated['remove_photo'] ?? false) === true) {
            $attributes['photo'] = null;
        }

        $user->update($attributes);

        if ($oldPhoto !== null && array_key_exists('photo', $attributes) && $oldPhoto !== $user->photo) {
            $disk = Storage::disk('public');

            if ($disk->exists($oldPhoto) && ! $disk->delete($oldPhoto)) {
                throw new RuntimeException('Impossible de supprimer l’ancienne photo de profil.');
            }
        }

        return UserResource::make($user->refresh())->response();
    }
}
