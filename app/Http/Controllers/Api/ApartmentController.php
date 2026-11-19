<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuestRegistration;
use App\Models\MaintenanceRequest;
use App\Models\User;
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
            'notes'            => 'nullable|string|max:4000',
        ]);

        $validated['user_id'] = $request->user()->id;
        $residentName = $request->user()->name;
        $guest = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $residentName) {
            $guest = GuestRegistration::create($validated);
            foreach (User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_IPL_ADMIN, User::ROLE_GUEST_RELATIONS])->get() as $recipient) {
                $recipient->notify(new \App\Notifications\PropertyActivityNotification('New Visitor Registration',
                    "{$residentName} registered {$validated['guest_name']} for a visit.", 'guest_registration', $guest->id));
            }
            return $guest;
        });

        return response()->json([
            'message' => 'Visitor registration sent to Guest Relations.',
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
            'description' => 'required|string|max:4000',
            'priority'    => 'nullable|in:low,normal,urgent',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['priority'] = $validated['priority'] ?? 'normal';
        $residentName = $request->user()->name;
        $priorityLabel = strtoupper($validated['priority']);
        $maintenance = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $residentName, $priorityLabel) {
            $maintenance = MaintenanceRequest::create($validated);
            foreach (User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_IPL_ADMIN, User::ROLE_DELIVERY_PARTNER, User::ROLE_GUEST_RELATIONS])->get() as $recipient) {
                $recipient->notify(new \App\Notifications\PropertyActivityNotification("[{$priorityLabel}] Maintenance Request",
                    "{$residentName}: {$validated['title']} — {$validated['description']}", 'maintenance', $maintenance->id));
            }
            return $maintenance;
        });

        return response()->json([
            'message'      => 'Maintenance request submitted. Our team will contact you shortly.',
            'request'      => $maintenance,
            'whatsapp_url' => 'https://wa.me/' . preg_replace('/\D/', '', config('property.whatsapp_number')) . '?text=' . urlencode(
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
