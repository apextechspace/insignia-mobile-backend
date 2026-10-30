<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\GuestAccessEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class GuestAccessController extends Controller
{
    public function requestAccess(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'name' => 'nullable|string|max:255', 'phone' => 'nullable|string|min:7|max:40']);
        $email = Str::lower(trim($data['email']));
        $rateKey = 'guest-access:'.hash('sha256', $email);
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateKey, 3)) {
            return response()->json(['message' => 'Please wait before requesting another sign-in email.'], 429);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateKey, 900);
        $id = (string) Str::uuid();
        $secret = Str::random(64);
        $code = (string) random_int(100000, 999999);
        DB::table('guest_access_requests')->insert([
            'id' => $id, 'email' => $email, 'name' => $data['name'] ?? null, 'phone' => $data['phone'] ?? null,
            'token_hash' => hash('sha256', $secret), 'code_hash' => hash_hmac('sha256', $code, config('app.key')),
            'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        // Customer email sign-in must never grant staff or owner access.
        if (! $user || $user->role === User::ROLE_USER) {
            Notification::route('mail', $email)->notify(new GuestAccessEmail($code,
                rtrim(config('services.guest_access.frontend_url'), '/').'/access-stay#'.http_build_query(['request_id' => $id, 'token' => $secret])));
        }

        return response()->json(['request_id' => $id, 'message' => 'Check your email for your secure sign-in code.']);
    }

    public function verify(Request $request)
    {
        $data = $request->validate(['request_id' => 'required|uuid', 'code' => 'nullable|digits:6', 'token' => 'nullable|string|size:64']);
        $user = DB::transaction(function () use ($data) {
            $access = DB::table('guest_access_requests')->where('id', $data['request_id'])->lockForUpdate()->first();
            if (! $access || $access->consumed_at || now()->gte($access->expires_at) || $access->attempts >= 5) {
                return null;
            }
            DB::table('guest_access_requests')->where('id', $access->id)->increment('attempts');
            $valid = ! empty($data['token']) ? hash_equals($access->token_hash, hash('sha256', $data['token']))
                : (! empty($data['code']) && hash_equals($access->code_hash, hash_hmac('sha256', $data['code'], config('app.key'))));
            if (! $valid) {
                return null;
            }
            $user = User::whereRaw('LOWER(email) = ?', [$access->email])->lockForUpdate()->first();
            if ($user && $user->role !== User::ROLE_USER) {
                return null;
            }
            if (! $user) {
                $user = User::firstOrCreate(['email' => $access->email], ['name' => $access->name ?: 'Insignia Guest',
                    'phone' => $access->phone, 'password' => Str::random(64), 'role' => User::ROLE_USER]);
                if ($user->role !== User::ROLE_USER) {
                    return null;
                }
            }
            // Never replace an existing customer's name/phone from a login request.
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?: now()])->save();
            DB::table('guest_access_requests')->where('id', $access->id)->update(['consumed_at' => now()]);

            return $user;
        }, 3);
        if (! $user) {
            return response()->json(['message' => 'This code or link is invalid or expired. Request a new one.'], 422);
        }

        return response()->json(['access_token' => $user->createToken('guest_email', ['*'], now()->addDays(30))->plainTextToken,
            'user' => $user, 'token_type' => 'Bearer'])->header('Cache-Control', 'no-store');
    }

    public function setPassword(Request $request)
    {
        $data = $request->validate(['password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols()]]);
        abort_unless($request->user()->role === User::ROLE_USER && $request->user()->email_verified_at, 403);
        $token = $request->user()->currentAccessToken();
        abort_unless($token && $token->name === 'guest_email' && $token->created_at->gt(now()->subMinutes(15)), 403,
            'Please sign in with a fresh email link or code before setting a password.');
        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password saved. You can now sign in with email and password.']);
    }
}
