<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetHandover;
use App\Models\Booking;
use App\Models\ConsumableItem;
use App\Models\DailyReconciliation;
use App\Models\Expense;
use App\Models\MonthlyStatement;
use App\Models\PettyCashTransaction;
use App\Models\Room;
use App\Models\StaffShift;
use App\Models\StaffTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperationsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed the 4 Test Role Personas
        $owner = User::firstOrCreate(
            ['email' => 'owner@insigniacourt.com'],
            [
                'name' => 'IPL Executive Management',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_IPL_ADMIN,
                'phone' => '+2348011112222',
            ]
        );

        $mcl = User::firstOrCreate(
            ['email' => 'operations@mcl.ng'],
            [
                'name' => 'MCL Facility Manager',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_DELIVERY_PARTNER,
                'phone' => '+2348033334444',
            ]
        );

        $frontDesk = User::firstOrCreate(
            ['email' => 'frontdesk@insigniacourt.com'],
            [
                'name' => 'Front Desk & Guest Relations',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_GUEST_RELATIONS,
                'phone' => '+2348055556666',
            ]
        );

        $housekeeper = User::firstOrCreate(
            ['email' => 'housekeeping@insigniacourt.com'],
            [
                'name' => 'Grace Housekeeping Lead',
                'password' => Hash::make('password123'),
                'role' => User::ROLE_STAFF,
                'phone' => '+2348077778888',
            ]
        );

        // 2. Ensure the 6 Apartments have baseline status & housekeeping notes
        $rooms = Room::all();
        if ($rooms->isNotEmpty()) {
            $guestUser = User::where('email', 'user@insignia.com')->first() ?? $owner;

            foreach ($rooms as $index => $room) {
                // Stagger statuses for visual demonstration on the dashboard
                $status = match ($index % 4) {
                    0 => 'occupied',
                    1 => 'available',
                    2 => 'cleaning',
                    3 => 'maintenance',
                };

                $room->update([
                    'status' => $room->status && $room->status !== 'ready' ? $room->status : $status,
                    'housekeeping_notes' => $room->housekeeping_notes ?: 'Turnover inspected for standard VIP setup.',
                ]);

                // If occupied, ensure a confirmed active booking exists so activeBooking relationship resolves
                if ($status === 'occupied') {
                    \App\Models\Booking::firstOrCreate(
                        [
                            'room_id' => $room->id,
                            'status' => 'confirmed',
                        ],
                        [
                            'user_id' => $guestUser->id,
                            'check_in' => Carbon::today()->subDays(1),
                            'check_out' => Carbon::today()->addDays(2),
                            'total_price' => $room->price_per_night * 3,
                            'payment_method' => 'opay',
                            'payment_reference' => 'INS-DEMO-' . $room->id,
                            'paid_at' => Carbon::now()->subDays(1),
                        ]
                    );
                }
            }
        }

        // 3. Seed Consumable Inventory Items
        $consumables = [
            [
                'name' => 'Plush White Bath Towels',
                'category' => 'linen',
                'unit' => 'pieces',
                'current_stock' => 45,
                'minimum_alert_level' => 12,
                'unit_cost' => 4500.00,
            ],
            [
                'name' => 'Egyptian Cotton King Sheet (6x6)',
                'category' => 'linen',
                'unit' => 'sets',
                'current_stock' => 18,
                'minimum_alert_level' => 6,
                'unit_cost' => 16000.00,
            ],
            [
                'name' => 'Insignia Luxury Bath Soap (25g)',
                'category' => 'toiletries',
                'unit' => 'pieces',
                'current_stock' => 120,
                'minimum_alert_level' => 30,
                'unit_cost' => 450.00,
            ],
            [
                'name' => 'Organic Conditioning Shampoo (40ml)',
                'category' => 'toiletries',
                'unit' => 'bottles',
                'current_stock' => 85,
                'minimum_alert_level' => 25,
                'unit_cost' => 750.00,
            ],
            [
                'name' => 'Nespresso Master Origin Pods (Box of 10)',
                'category' => 'kitchen',
                'unit' => 'packs',
                'current_stock' => 14,
                'minimum_alert_level' => 5,
                'unit_cost' => 7000.00,
            ],
            [
                'name' => 'San Pellegrino Sparkling Water (500ml)',
                'category' => 'minibar',
                'unit' => 'bottles',
                'current_stock' => 32,
                'minimum_alert_level' => 12,
                'unit_cost' => 1200.00,
            ],
            [
                'name' => 'Antibacterial Room Surface Sanitizer',
                'category' => 'cleaning_supplies',
                'unit' => 'bottles',
                'current_stock' => 4, // Below threshold to trigger red warning
                'minimum_alert_level' => 6,
                'unit_cost' => 3500.00,
            ],
        ];

        foreach ($consumables as $item) {
            ConsumableItem::firstOrCreate(['name' => $item['name']], $item);
        }

        // 4. Seed Standard Assets for the 6 Rooms
        if ($rooms->isNotEmpty()) {
            foreach ($rooms as $room) {
                $codePrefix = 'AST-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $room->name ?? 'RM'), 0, 4));

                $assets = [
                    [
                        'name' => 'Samsung 55" Crystal UHD 4K Smart TV',
                        'asset_code' => $codePrefix . '-TV01',
                        'category' => 'electronics',
                        'brand' => 'Samsung',
                        'model_number' => 'AU7000-55',
                        'serial_number' => 'SN-SAM-' . rand(100000, 999999),
                        'condition' => 'good',
                        'purchase_date' => Carbon::now()->subMonths(6),
                        'notes' => 'Wall mounted with heavy-duty tilt bracket.',
                    ],
                    [
                        'name' => 'Panasonic Inverter Air Conditioner 1.5HP',
                        'asset_code' => $codePrefix . '-AC01',
                        'category' => 'electronics',
                        'brand' => 'Panasonic',
                        'model_number' => 'CS-YS12TKY',
                        'serial_number' => 'SN-PAN-' . rand(100000, 999999),
                        'condition' => 'good',
                        'purchase_date' => Carbon::now()->subMonths(6),
                        'notes' => 'Outdoor condenser on bracket, serviced monthly.',
                    ],
                    [
                        'name' => 'LG NeoChef 25L Smart Inverter Microwave',
                        'asset_code' => $codePrefix . '-MW01',
                        'category' => 'kitchen_appliance',
                        'brand' => 'LG',
                        'model_number' => 'MS2535GIS',
                        'serial_number' => 'SN-LG-' . rand(100000, 999999),
                        'condition' => 'brand_new',
                        'purchase_date' => Carbon::now()->subMonths(2),
                        'notes' => 'Kitchenette installation.',
                    ],
                    [
                        'name' => 'Yale Biometric In-Room Digital Safe',
                        'asset_code' => $codePrefix . '-SF01',
                        'category' => 'furniture',
                        'brand' => 'Yale',
                        'model_number' => 'YFM-420-FG2',
                        'serial_number' => 'SN-YAL-' . rand(100000, 999999),
                        'condition' => 'good',
                        'purchase_date' => Carbon::now()->subMonths(6),
                        'notes' => 'Bolted into master wardrobe.',
                    ],
                ];

                foreach ($assets as $assetData) {
                    Asset::firstOrCreate(
                        ['asset_code' => $assetData['asset_code']],
                        array_merge($assetData, ['room_id' => $room->id])
                    );
                }
            }
        }

        // 5. Seed Initial Petty Cash Float
        if (PettyCashTransaction::count() === 0) {
            PettyCashTransaction::create([
                'type' => 'replenishment',
                'amount' => 200000.00,
                'balance_after' => 200000.00,
                'purpose' => 'Initial operational cash float allocation for Wuse II property.',
                'handled_by' => $owner->id,
                'approved_by' => $owner->id,
                'transaction_date' => Carbon::now()->startOfMonth(),
            ]);

            PettyCashTransaction::create([
                'type' => 'disbursement',
                'amount' => 15500.00,
                'balance_after' => 184500.00,
                'purpose' => 'Urgent replacement of kitchen sink flex hose & tap washers in Atala suite.',
                'handled_by' => $mcl->id,
                'approved_by' => $owner->id,
                'transaction_date' => Carbon::now()->subDays(2),
            ]);
        }

        // 6. Seed Sample Housekeeping Staff Shifts
        if (StaffShift::count() === 0) {
            StaffShift::create([
                'user_id' => $housekeeper->id,
                'shift_date' => Carbon::today(),
                'shift_type' => 'morning',
                'start_time' => '07:30:00',
                'end_time' => '16:00:00',
                'status' => 'present',
                'handover_notes' => 'Checkouts completed for Atala and Junonia. Bed linen changed and mini-bar restocked.',
            ]);

            StaffShift::create([
                'user_id' => $frontDesk->id,
                'shift_date' => Carbon::today(),
                'shift_type' => 'full_day',
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
                'status' => 'present',
                'handover_notes' => 'Incoming VIP arrival scheduled for Evania at 3:00 PM.',
            ]);
        }

        // 7. Seed Sample Staff Tasks
        if (StaffTask::count() === 0 && $rooms->isNotEmpty()) {
            StaffTask::create([
                'task_title' => 'Deep Sanitization & VIP Setup - Suite Evania',
                'user_id' => $housekeeper->id,
                'room_id' => $rooms->first()->id,
                'task_type' => 'room_turnaround',
                'priority' => 'high',
                'checklist_items' => [
                    ['item' => 'Steam clean sofa and upholstery', 'done' => true],
                    ['item' => 'Replace bed linen with 6x6 Egyptian cotton', 'done' => true],
                    ['item' => 'Restock Nespresso pods and San Pellegrino water', 'done' => false],
                    ['item' => 'Final quality inspection & fragrance diffuser check', 'done' => false],
                ],
                'status' => 'in_progress',
                'notes' => 'Guest requested early check-in at 2:30 PM.',
            ]);
        }

        // 8. Seed Sample Operational Expenses (Cashless - Electronic Transfers)
        if (Expense::count() === 0) {
            Expense::create([
                'title' => 'AEDC / PHCN Electricity Grid Bill - October 2026',
                'category' => 'utilities_phcn',
                'amount' => 145000.00,
                'currency' => 'NGN',
                'payment_method' => 'bank_transfer',
                'status' => 'approved',
                'logged_by' => $mcl->id,
                'approved_by' => $owner->id,
                'approved_at' => Carbon::now()->subDays(3),
                'expense_date' => Carbon::now()->subDays(3),
                'description' => 'Dedicated transformer maintenance and monthly consumption billing.',
            ]);

            Expense::create([
                'title' => 'Inverter AC Capacitor & Gas Refill - Suite Lyria',
                'category' => 'maintenance_repairs',
                'amount' => 38000.00,
                'currency' => 'NGN',
                'payment_method' => 'bank_transfer',
                'room_id' => $rooms->skip(2)->first()?->id,
                'status' => 'approved',
                'logged_by' => $mcl->id,
                'approved_by' => $owner->id,
                'approved_at' => Carbon::now()->subDays(1),
                'expense_date' => Carbon::now()->subDays(1),
                'description' => 'Serviced AC condenser bracket and recharged R410 gas.',
            ]);
        }

        // 9. Seed Sample Digital Daily Reconciliation (Cashless Shift Closing)
        if (DailyReconciliation::count() === 0) {
            DailyReconciliation::create([
                'reconciliation_date' => Carbon::yesterday(),
                'shift' => 'evening',
                'recorded_accommodation' => 185000.00,
                'recorded_ancillary_cash' => 25000.00,
                'actual_pos_total' => 150000.00,
                'actual_transfer_total' => 60000.00,
                'actual_cash_counted' => 0.00, // Cashless policy
                'discrepancy' => 0.00,
                'status' => 'verified_by_mcl',
                'submitted_by' => $frontDesk->id,
                'verified_by' => $mcl->id,
                'verified_at' => Carbon::now()->subHours(12),
                'notes' => 'Zero-cash shift closing. All walk-in and ancillary receipts matched POS settlement batch printout and direct transfer credit alerts.',
            ]);
        }

        // 10. Seed Sample Monthly Statement (MCL -> IPL Review Workflow)
        if (MonthlyStatement::count() === 0) {
            MonthlyStatement::create([
                'statement_code' => 'STM-2026-09',
                'year' => 2026,
                'month' => 9,
                'total_accommodation_revenue' => 3450000.00,
                'total_permitted_expenses' => 450000.00,
                'management_fee_percentage' => 15.00,
                'management_fee_amount' => 450000.00,
                'net_payable_to_ipl' => 2550000.00,
                'status' => 'submitted_to_ipl',
                'submitted_by' => $mcl->id,
                'submitted_at' => Carbon::now()->subDays(4),
            ]);
        }

        // 11. Seed Baseline Asset Handover Protocol
        if (AssetHandover::count() === 0 && $rooms->isNotEmpty()) {
            AssetHandover::create([
                'room_id' => $rooms->first()->id,
                'handover_date' => Carbon::now()->subMonth(),
                'status' => 'verified_by_mcl',
                'condition_notes' => 'Baseline condition verified: Samsung 55" TV, Panasonic AC, LG Microwave, and Yale safe in full working condition with accessories.',
                'mcl_representative_id' => $mcl->id,
                'mcl_signed_at' => Carbon::now()->subMonth(),
            ]);
        }
    }
}
