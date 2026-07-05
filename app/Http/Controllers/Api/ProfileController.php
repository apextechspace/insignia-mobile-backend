<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Setup a new 4-digit security PIN for the user
     */
    public function setupPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|size:4|regex:/^[0-9]+$/'
        ]);

        $user = $request->user();
        if ($user->security_pin) {
            return response()->json(['message' => 'Security PIN is already established.'], 400);
        }

        $user->security_pin = Hash::make($request->pin);
        $user->save();

        return response()->json(['message' => 'Executive PIN successfully established.']);
    }

    /**
     * Verify the 4-digit security PIN to grant access to secure areas
     */
    public function verifyPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|size:4'
        ]);

        $user = $request->user();
        
        if (!$user->security_pin) {
            return response()->json(['message' => 'No Security PIN configured on this account.'], 400);
        }

        if (!Hash::check($request->pin, $user->security_pin)) {
            return response()->json(['message' => 'Invalid Executive Security PIN.'], 401);
        }

        return response()->json(['message' => 'Access Granted.', 'verified' => true]);
    }

    /**
     * Update Profile Information (Name, Phone, Avatar)
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        $user = $request->user();

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        if ($request->hasFile('avatar')) {
            // If they had an old uploaded avatar, delete it to save space (ignoring web URLs)
            if ($user->avatar_url && !str_starts_with($user->avatar_url, 'http')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar_url));
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = '/storage/' . $path;
        }

        $user->save();

        return response()->json([
            'message' => 'Executive Profile successfully archived.',
            'user' => $user
        ]);
    }

    /**
     * Store or update the user's Expo Push Token
     */
    public function updatePushToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $user = $request->user();
        $user->expo_push_token = $request->token;
        $user->save();

        return response()->json(['message' => 'Communication channel established.']);
    }
}
