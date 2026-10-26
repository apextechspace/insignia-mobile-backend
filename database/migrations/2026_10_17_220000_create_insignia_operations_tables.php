<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Accommodation Ledgers (Split-Ledger: Room revenue, collections & settlement status)
        Schema::create('accommodation_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('reference')->unique();
            $table->enum('type', ['revenue', 'refund', 'adjustment'])->default('revenue');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->string('payment_method')->nullable(); // opay, bank_transfer, pos, cash
            $table->string('channel')->default('app'); // app, walk_in, direct
            $table->string('status')->default('confirmed'); // pending, confirmed, disputed, refunded
            $table->string('collected_by')->nullable(); // ipl, mcl, front_desk
            $table->timestamp('transaction_date')->useCurrent();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 2. Expenses (Cash outflows: bills, repairs, fuel, petty cash; approval workflow)
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category'); // utilities_phcn, generator_fuel, maintenance_repairs, consumables_restock, security, petty_cash, other
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('payment_method')->default('petty_cash'); // petty_cash, bank_transfer, cash
            $table->string('receipt_photo')->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('approved');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expense_date')->useCurrent();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 3. Petty Cash Float & Transactions
        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['disbursement', 'replenishment']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('purpose');
            $table->string('receipt_photo')->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignId('handled_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps();
        });

        // 4. Daily Reconciliations (End of shift closing: Cash, POS, Transfers)
        Schema::create('daily_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('reconciliation_date');
            $table->string('shift')->default('morning'); // morning, evening, night
            $table->decimal('recorded_accommodation', 12, 2)->default(0);
            $table->decimal('recorded_ancillary_cash', 12, 2)->default(0);
            $table->decimal('actual_cash_counted', 12, 2)->default(0);
            $table->decimal('actual_pos_total', 12, 2)->default(0);
            $table->decimal('actual_transfer_total', 12, 2)->default(0);
            $table->decimal('discrepancy', 12, 2)->default(0); // difference between recorded & actual
            $table->text('notes')->nullable();
            $table->string('pos_settlement_receipt')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['submitted', 'verified_by_mcl', 'approved_by_ipl'])->default('submitted');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // 5. Monthly Operational Statements (Submitted by MCL for IPL sign-off)
        Schema::create('monthly_statements', function (Blueprint $table) {
            $table->id();
            $table->string('statement_code')->unique(); // e.g. STM-2026-10
            $table->year('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('total_accommodation_revenue', 12, 2)->default(0);
            $table->decimal('total_permitted_expenses', 12, 2)->default(0);
            $table->decimal('management_fee_percentage', 5, 2)->default(0); // agreed %
            $table->decimal('management_fee_amount', 12, 2)->default(0);
            $table->decimal('net_payable_to_ipl', 12, 2)->default(0);
            $table->string('document_pdf')->nullable();
            $table->enum('status', ['draft', 'submitted_to_ipl', 'queried', 'approved_by_ipl', 'settled'])->default('draft');
            $table->text('ipl_query_notes')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // 6. Consumables & Inventory Items (Strictly distinct from expenses: physical stock on shelves)
        Schema::create('consumable_items', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Bath Towels, Soap 25g, Bedsheet 6x6, Cleaning Detergent
            $table->string('category'); // toiletries, linen, cleaning_supplies, kitchen, minibar
            $table->string('unit'); // pieces, bottles, packs, sets
            $table->integer('current_stock')->default(0);
            $table->integer('minimum_alert_level')->default(5);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->timestamps();
        });

        // 7. Stock Movements (Purchases, room issuance, damage/write-offs)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_item_id')->constrained('consumable_items')->cascadeOnDelete();
            $table->enum('movement_type', ['purchase_restock', 'room_issuance', 'damaged_writeoff', 'adjustment']);
            $table->integer('quantity'); // positive for restock, negative for issuance/damage
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete(); // linked if restocked via expense
            $table->foreignId('handled_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 8. Asset Register (Durable room furniture, TVs, ACs, microwaves)
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Samsung 55" QLED TV, Daikin Inverter AC 1.5HP
            $table->string('asset_code')->unique(); // e.g. AST-ATALA-01
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('category'); // electronics, furniture, plumbing, lighting, kitchen_appliance
            $table->string('brand')->nullable();
            $table->string('model_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->enum('condition', ['brand_new', 'good', 'fair', 'needs_repair', 'condemned'])->default('good');
            $table->string('photo_url')->nullable();
            $table->date('purchase_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Asset Handover Sign-Off (Initial baseline lock between IPL & MCL)
        Schema::create('asset_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('handover_date');
            $table->enum('status', ['pending_mcl_review', 'verified_by_mcl', 'locked_and_signed_by_ipl'])->default('pending_mcl_review');
            $table->text('condition_notes')->nullable();
            $table->string('signed_document_pdf')->nullable();
            $table->foreignId('mcl_representative_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('mcl_signed_at')->nullable();
            $table->foreignId('ipl_representative_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ipl_signed_at')->nullable();
            $table->timestamps();
        });

        // 10. Incidents & Escalations (Property damage, guest complaints, security tickets)
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code')->unique(); // e.g. INC-2026-001
            $table->string('title');
            $table->enum('type', ['property_damage', 'missing_item', 'maintenance_failure', 'security_concern', 'guest_complaint', 'other']);
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->text('description');
            $table->string('evidence_photo_1')->nullable();
            $table->string('evidence_photo_2')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->decimal('associated_cost', 12, 2)->default(0);
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->enum('status', ['reported', 'under_investigation', 'awaiting_parts', 'escalated_to_ipl', 'resolved', 'closed'])->default('reported');
            $table->boolean('is_escalated_to_ipl')->default(false);
            $table->text('ipl_escalation_notes')->nullable();
            $table->text('immediate_action_taken')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 11. Staff Shifts (Duty Roster & Attendance)
        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('shift_date');
            $table->enum('shift_type', ['morning', 'evening', 'night', 'full_day']);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('status', ['scheduled', 'present', 'late', 'absent', 'completed'])->default('scheduled');
            $table->text('handover_notes')->nullable();
            $table->timestamps();
        });

        // 12. Staff Tasks & Housekeeping Checklists
        Schema::create('staff_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('task_title'); // e.g. Turnaround Clean Room Junonia, Restock Linen Atala
            $table->enum('task_type', ['room_turnaround', 'daily_refresh', 'maintenance_fix', 'guest_delivery', 'inspection']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'verified'])->default('pending');
            $table->json('checklist_items')->nullable(); // e.g. [{"item": "Bedsheets changed", "done": true}]
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_tasks');
        Schema::dropIfExists('staff_shifts');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('asset_handovers');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('consumable_items');
        Schema::dropIfExists('monthly_statements');
        Schema::dropIfExists('daily_reconciliations');
        Schema::dropIfExists('petty_cash_transactions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('accommodation_ledgers');
    }
};
