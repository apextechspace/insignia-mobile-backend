<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuestRegistration;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Http\Request;

class ApartmentController extends Controller
{
    // ─── GUEST REGISTRATION ───────────────────────────────────────────────────

    public function listGuests(Request $request)
    {
        $guests = GuestRegistration::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($guests);
    }

    public function registerGuest(Request $request)
    {
        $validated = $request->validate([
            'guest_name'       => 'required|string|max:255',
            'guest_phone'      => 'nullable|string|max:20',
            'relationship'     => 'nullable|string|max:100',
            'visit_date'       => 'nullable|date',
            'expected_arrival' => 'nullable|string',
            'purpose'          => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;
        $guest = GuestRegistration::create($validated);

        // Notify all admins via push
        $residentName = $request->user()->name;
        $admins = User::where('role', 'admin')->whereNotNull('expo_push_token')->get();
        foreach ($admins as $admin) {
            ExpoPushService::send(
                $admin->expo_push_token,
                '👥 New Guest Registration',
                "{$residentName} has registered {$validated['guest_name']} for a visit.",
                ['type' => 'guest_registration', 'id' => $guest->id]
            );
        }

        return response()->json([
            'message' => 'Guest registered successfully. Security has been notified.',
            'guest'   => $guest,
        ], 201);
    }

    // ─── MAINTENANCE REQUESTS ─────────────────────────────────────────────────

    public function listMaintenance(Request $request)
    {
        $requests = MaintenanceRequest::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($requests);
    }

    public function submitMaintenance(Request $request)
    {
        $validated = $request->validate([
            'category'    => 'required|string|in:AC,Plumbing,Electrical,Internet,Furniture,Cleaning,General',
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'priority'    => 'nullable|in:low,normal,urgent',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['priority'] = $validated['priority'] ?? 'normal';
        $maintenance = MaintenanceRequest::create($validated);

        // Notify all admins via push
        $residentName = $request->user()->name;
        $priorityLabel = strtoupper($validated['priority']);
        $admins = User::where('role', 'admin')->whereNotNull('expo_push_token')->get();
        foreach ($admins as $admin) {
            ExpoPushService::send(
                $admin->expo_push_token,
                "🔧 [{$priorityLabel}] Maintenance Request",
                "{$residentName}: {$validated['title']}",
                ['type' => 'maintenance', 'id' => $maintenance->id]
            );
        }

        return response()->json([
            'message'      => 'Maintenance request submitted. Our team will contact you shortly.',
            'request'      => $maintenance,
            'whatsapp_url' => 'https://wa.me/2349110111133?text=' . urlencode(
                "🔧 MAINTENANCE REQUEST\n" .
                "Resident: {$residentName}\n" .
                "Category: {$validated['category']}\n" .
                "Issue: {$validated['title']}\n" .
                "Details: {$validated['description']}\n" .
                "Priority: {$priorityLabel}"
            ),
        ], 201);
    }
}
