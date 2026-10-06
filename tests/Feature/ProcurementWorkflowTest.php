<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ProcurementBidActivityLog;
use App\Models\ProcurementBidProcess;
use App\Models\ProcurementPaymentActivityLog;
use App\Models\ProcurementPaymentRequisition;
use App\Models\ProcurementRequisition;
use App\Models\ProcurementRequisitionActivityLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProcurementRequisitionService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name'     => "Proc User {$n}",
            'email'    => "proc-user{$n}@example.test",
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    private function makeSupplier(User $creator, string $name): Supplier
    {
        static $n = 0;
        $n++;

        return Supplier::create(['supplier_code' => "SUP-{$n}", 'name' => $name, 'created_by' => $creator->id]);
    }

    private function makeRequisition(User $requester): ProcurementRequisition
    {
        return app(ProcurementRequisitionService::class)->create($requester, ['title' => 'Laptops'], [
            ['description' => 'Laptop', 'quantity' => 2, 'estimated_unit_cost' => 500],
        ]);
    }

    private function approvedRequisition(User $requester, User $director): ProcurementRequisition
    {
        $service = app(ProcurementRequisitionService::class);
        $req = $this->makeRequisition($requester);
        $service->submitForReview($req, $requester);
        $service->review($req, $director);
        $service->approve($req, $director);

        return $req->fresh();
    }

    public function test_full_lifecycle_from_requisition_to_recorded_payment(): void
    {
        $requester = $this->makeUser('staff');
        $hop       = $this->makeUser('programs');
        $hof       = $this->makeUser('accounting_dep');
        $director  = $this->makeUser('director');
        $cheap     = $this->makeSupplier($director, 'Cheap Co');
        $pricey    = $this->makeSupplier($director, 'Pricey Co');

        // Stages 1-3: requisition raised, reviewed, approved.
        $this->actingAs($requester)->post('/procurement-requisitions', [
            'title'  => 'Office laptops',
            'lines'  => [
                ['description' => 'Laptop', 'quantity' => 2, 'estimated_unit_cost' => 500],
                ['description' => 'Bag', 'quantity' => 2, 'estimated_unit_cost' => 25.50],
            ],
        ])->assertRedirect();

        $req = ProcurementRequisition::latest('id')->firstOrFail();
        $this->assertSame('draft', $req->status);
        $this->assertSame('REQ-'.str_pad((string) $req->id, 6, '0', STR_PAD_LEFT), $req->requisition_number);
        $this->assertEquals(1051.00, (float) $req->estimated_total);
        $this->assertCount(2, $req->items);

        $this->actingAs($requester)->post("/procurement-requisitions/{$req->id}/submit")->assertSessionHas('success');
        $this->actingAs($hop)->post("/procurement-requisitions/{$req->id}/review", ['notes' => 'OK'])->assertSessionHas('success');
        $this->actingAs($director)->post("/procurement-requisitions/{$req->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $req->fresh()->status);

        // Stages 4-8: vendor selection.
        $this->actingAs($hof)->post("/procurement-requisitions/{$req->id}/selection", [
            'bid_method'    => 'request_for_quotes',
            'bid_reference' => 'RFQ-1',
            'quotes'        => [
                ['supplier_id' => $cheap->id, 'quoted_amount' => 1000],
                ['supplier_id' => $pricey->id, 'quoted_amount' => 1400],
            ],
        ])->assertSessionHas('success');

        $bid = ProcurementBidProcess::where('requisition_id', $req->id)->firstOrFail();
        $this->assertSame('bids_requested', $bid->status);
        $this->assertCount(2, $bid->quotes);

        $this->actingAs($hof)->post("/procurement-bids/{$bid->id}/evaluate", [
            'recommended_supplier_id' => $cheap->id,
            'notes'                   => 'Lowest compliant quote',
        ])->assertSessionHas('success');
        $this->assertTrue($bid->quotes()->where('supplier_id', $cheap->id)->value('is_recommended'));
        $this->assertFalse($bid->quotes()->where('supplier_id', $pricey->id)->value('is_recommended'));

        $this->actingAs($hop)->post("/procurement-bids/{$bid->id}/hop-review")->assertSessionHas('success');
        $this->actingAs($hof)->post("/procurement-bids/{$bid->id}/hof-review")->assertSessionHas('success');
        $this->actingAs($director)->post("/procurement-bids/{$bid->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $bid->fresh()->status);

        // Stage 9: PO issued from the approved vendor selection.
        $this->actingAs($hop)->post("/procurement-bids/{$bid->id}/issue-po")->assertRedirect();

        $po = PurchaseOrder::where('requisition_id', $req->id)->firstOrFail();
        $this->assertSame($bid->id, $po->bid_process_id);
        $this->assertSame($cheap->id, $po->supplier_id);
        $this->assertEquals(1051.00, (float) $po->total);
        $this->assertCount(2, $po->items);

        // A second PO for the same requisition is refused.
        $this->actingAs($hop)->post("/procurement-bids/{$bid->id}/issue-po")->assertSessionHas('error');
        $this->assertSame(1, PurchaseOrder::where('requisition_id', $req->id)->count());

        // Stage 10: PO approved and delivery confirmed (service PO, no store).
        $poService = app(PurchaseOrderService::class);
        $poService->submitForApproval($po, $hop);
        $poService->approve($po, $director);
        $poService->confirmDelivery($po, $hof, 'Delivered in full', 'INV-77', now()->toDateString());
        $this->assertSame('received', $po->fresh()->status);

        // Stages 11-14: payment requisition.
        $this->actingAs($hof)->post("/purchase-orders/{$po->id}/payments", [
            'payee_name'     => 'Cheap Co',
            'amount'         => 1051,
            'payment_method' => 'bank_transfer',
        ])->assertRedirect();

        $payment = ProcurementPaymentRequisition::where('purchase_order_id', $po->id)->firstOrFail();
        $this->assertSame('draft', $payment->status);
        $this->assertSame('PAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT), $payment->payment_number);

        $this->actingAs($hof)->post("/procurement-payments/{$payment->id}/submit")->assertSessionHas('success');
        $this->actingAs($hof)->post("/procurement-payments/{$payment->id}/review")->assertSessionHas('success');
        $this->actingAs($director)->post("/procurement-payments/{$payment->id}/approve")->assertSessionHas('success');
        $this->actingAs($hof)->post("/procurement-payments/{$payment->id}/load-to-bank", ['bank_reference' => 'BANK-1'])->assertSessionHas('success');
        $this->actingAs($director)->post("/procurement-payments/{$payment->id}/release")->assertSessionHas('success');
        $this->actingAs($hof)->post("/procurement-payments/{$payment->id}/record", ['recording_reference' => 'GL-9'])->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame('recorded', $payment->status);
        $this->assertSame('BANK-1', $payment->bank_reference);
        $this->assertSame('GL-9', $payment->recording_reference);

        // Sign-off block: each row signed by whoever performed that step.
        $this->withoutVite()->actingAs($director)->get("/procurement-requisitions/{$req->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('signatures.0.label', 'Prepared by')->where('signatures.0.name', $requester->name)
                ->where('signatures.1.label', 'Reviewed by')->where('signatures.1.name', $hop->name)
                ->where('signatures.2.label', 'Authorized by')->where('signatures.2.name', $director->name)
                ->where('signatures.3.label', 'Paid by')->where('signatures.3.name', $hof->name)
                ->where('signatures.3.signed_at', fn ($v) => $v !== null)
            );

        // Every step is audited.
        $this->assertSame(
            ['requisition_created', 'requisition_submitted', 'requisition_reviewed', 'requisition_approved'],
            ProcurementRequisitionActivityLog::where('requisition_id', $req->id)->orderBy('id')->pluck('action')->all(),
        );
        $this->assertSame(
            ['bids_requested', 'bid_evaluated', 'hop_reviewed', 'hof_reviewed', 'vendor_approved'],
            ProcurementBidActivityLog::where('bid_process_id', $bid->id)->orderBy('id')->pluck('action')->all(),
        );
        $this->assertSame(
            ['payment_created', 'payment_submitted', 'payment_reviewed', 'payment_approved', 'payment_loaded', 'payment_released', 'payment_recorded'],
            ProcurementPaymentActivityLog::where('payment_id', $payment->id)->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_requisition_lines_accept_catalogue_items_and_custom_entries(): void
    {
        $requester = $this->makeUser('staff');
        $item = Item::forceCreate(['name' => 'Printer paper', 'created_by' => $requester->id]);

        $this->actingAs($requester)->post('/procurement-requisitions', [
            'title' => 'Office supplies',
            'lines' => [
                ['item_id' => $item->id, 'description' => 'Printer paper', 'quantity' => 10, 'estimated_unit_cost' => 5],
                ['item_id' => '', 'description' => 'Ergonomic chair (not in catalogue)', 'quantity' => 1, 'estimated_unit_cost' => 120],
            ],
        ])->assertSessionHasNoErrors();

        $lines = ProcurementRequisition::latest('id')->firstOrFail()->items()->orderBy('id')->get();
        $this->assertSame($item->id, $lines[0]->item_id);
        $this->assertNull($lines[1]->item_id);
        $this->assertSame('Ergonomic chair (not in catalogue)', $lines[1]->description);

        // A line with neither a catalogue item nor a description is refused.
        $this->actingAs($requester)->post('/procurement-requisitions', [
            'title' => 'Blank line',
            'lines' => [['item_id' => '', 'description' => '', 'quantity' => 1, 'estimated_unit_cost' => 1]],
        ])->assertSessionHasErrors('lines.0.description');
    }

    public function test_requisition_steps_are_restricted_by_role(): void
    {
        $requester = $this->makeUser('staff');
        $other     = $this->makeUser('staff');
        $hop       = $this->makeUser('programs');
        $req       = $this->makeRequisition($requester);

        $this->actingAs($other)->post("/procurement-requisitions/{$req->id}/submit")->assertForbidden();
        $this->actingAs($requester)->post("/procurement-requisitions/{$req->id}/submit")->assertSessionHas('success');

        $this->actingAs($requester)->post("/procurement-requisitions/{$req->id}/review")->assertForbidden();
        $this->actingAs($hop)->post("/procurement-requisitions/{$req->id}/review")->assertSessionHas('success');

        // Mid-flow: prepared and reviewed rows are signed, the rest are not yet.
        $this->withoutVite()->actingAs($hop)->get("/procurement-requisitions/{$req->id}")
            ->assertInertia(fn ($page) => $page
                ->where('signatures.0.name', $requester->name)
                ->where('signatures.1.name', $hop->name)
                ->where('signatures.2.signed_at', null)
                ->where('signatures.3.signed_at', null)
            );

        // Head of Programs reviews but cannot give final approval.
        $this->actingAs($hop)->post("/procurement-requisitions/{$req->id}/approve")->assertForbidden();
        $this->assertSame('pending_approval', $req->fresh()->status);
    }

    public function test_out_of_order_transitions_are_rejected(): void
    {
        $requester = $this->makeUser('staff');
        $director  = $this->makeUser('director');
        $req       = $this->makeRequisition($requester);

        $this->actingAs($director)->post("/procurement-requisitions/{$req->id}/approve")->assertSessionHas('error');
        $this->assertSame('draft', $req->fresh()->status);

        $this->actingAs($director)->post("/procurement-requisitions/{$req->id}/selection", ['bid_method' => 'sole_source'])
            ->assertSessionHas('error');
        $this->assertFalse($req->bidProcess()->exists());
    }

    public function test_rejected_requisition_cannot_progress_or_be_cancelled(): void
    {
        $requester = $this->makeUser('staff');
        $hop       = $this->makeUser('programs');
        $director  = $this->makeUser('director');
        $req       = $this->makeRequisition($requester);

        $this->actingAs($requester)->post("/procurement-requisitions/{$req->id}/submit");
        $this->actingAs($hop)->post("/procurement-requisitions/{$req->id}/reject-at-review")->assertSessionHasErrors('reason');
        $this->actingAs($hop)->post("/procurement-requisitions/{$req->id}/reject-at-review", ['reason' => 'Not budgeted'])
            ->assertSessionHas('success');

        $req->refresh();
        $this->assertSame('rejected', $req->status);
        $this->assertSame('Not budgeted', $req->review_notes);

        $this->actingAs($director)->post("/procurement-requisitions/{$req->id}/approve")->assertSessionHas('error');
        $this->actingAs($requester)->post("/procurement-requisitions/{$req->id}/cancel", ['reason' => 'x'])->assertSessionHas('error');
        $this->assertSame('rejected', $req->fresh()->status);
    }

    public function test_vendor_selection_stages_must_run_in_order(): void
    {
        $requester = $this->makeUser('staff');
        $hop       = $this->makeUser('programs');
        $director  = $this->makeUser('director');
        $supplier  = $this->makeSupplier($director, 'Only Co');
        $req       = $this->approvedRequisition($requester, $director);

        $this->actingAs($director)->post("/procurement-requisitions/{$req->id}/selection", [
            'bid_method' => 'sole_source',
            'quotes'     => [['supplier_id' => $supplier->id, 'quoted_amount' => 900]],
        ])->assertSessionHas('success');
        $bid = $req->bidProcess()->firstOrFail();

        // A second process for the same requisition is refused.
        $this->actingAs($director)->post("/procurement-requisitions/{$req->id}/selection", ['bid_method' => 'sole_source'])
            ->assertSessionHas('error');

        // Skipping evaluation / reviews is refused.
        $this->actingAs($hop)->post("/procurement-bids/{$bid->id}/hop-review")->assertSessionHas('error');
        $this->actingAs($director)->post("/procurement-bids/{$bid->id}/approve")->assertSessionHas('error');
        $this->actingAs($hop)->post("/procurement-bids/{$bid->id}/issue-po")->assertSessionHas('error');
        $this->assertSame('bids_requested', $bid->fresh()->status);
        $this->assertFalse(PurchaseOrder::where('requisition_id', $req->id)->exists());

        // Requester (plain staff) cannot run vendor selection.
        $this->actingAs($requester)->post("/procurement-bids/{$bid->id}/evaluate", [
            'recommended_supplier_id' => $supplier->id, 'notes' => 'x',
        ])->assertForbidden();
    }

    public function test_payment_requires_confirmed_delivery_and_runs_in_order(): void
    {
        $requester = $this->makeUser('staff');
        $hof       = $this->makeUser('accounting_dep');
        $director  = $this->makeUser('director');
        $supplier  = $this->makeSupplier($director, 'Vendor');

        $poService = app(PurchaseOrderService::class);
        $po = $poService->create($director, ['supplier_id' => $supplier->id, 'order_date' => now()->toDateString()], [
            ['description' => 'Service', 'quantity' => 1, 'unit_cost' => 300],
        ]);

        $paymentData = ['payee_name' => 'Vendor', 'amount' => 300];

        // Not delivered yet.
        $this->actingAs($hof)->post("/purchase-orders/{$po->id}/payments", $paymentData)->assertSessionHas('error');
        $this->assertFalse(ProcurementPaymentRequisition::exists());

        $poService->submitForApproval($po, $director);
        $poService->approve($po, $director);
        $poService->confirmDelivery($po, $hof, null, null, null);

        // Unrelated staff cannot raise a payment against someone else's PO.
        $this->actingAs($requester)->post("/purchase-orders/{$po->id}/payments", $paymentData)->assertForbidden();

        $this->actingAs($hof)->post("/purchase-orders/{$po->id}/payments", $paymentData)->assertRedirect();
        $payment = ProcurementPaymentRequisition::firstOrFail();

        // Cannot load or release before approval.
        $this->actingAs($hof)->post("/procurement-payments/{$payment->id}/load-to-bank", ['bank_reference' => 'B'])->assertSessionHas('error');
        $this->actingAs($director)->post("/procurement-payments/{$payment->id}/release")->assertSessionHas('error');

        // Head of Finance reviews but cannot approve or release.
        $this->actingAs($hof)->post("/procurement-payments/{$payment->id}/submit");
        $this->actingAs($hof)->post("/procurement-payments/{$payment->id}/review");
        $this->actingAs($hof)->post("/procurement-payments/{$payment->id}/approve")->assertForbidden();
        $this->assertSame('pending_approval', $payment->fresh()->status);

        $this->actingAs($director)->post("/procurement-payments/{$payment->id}/approve")->assertSessionHas('success');
        $this->actingAs($hof)->post("/procurement-payments/{$payment->id}/load-to-bank", ['bank_reference' => 'B'])->assertSessionHas('success');
        $this->actingAs($director)->post("/procurement-payments/{$payment->id}/release")->assertSessionHas('success');

        // Released payments can no longer be cancelled.
        $this->actingAs($director)->post("/procurement-payments/{$payment->id}/cancel", ['reason' => 'x'])->assertSessionHas('error');
        $this->assertSame('released', $payment->fresh()->status);
    }
}
