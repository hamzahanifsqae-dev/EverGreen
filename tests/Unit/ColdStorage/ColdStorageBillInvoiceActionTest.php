<?php

namespace Tests\Unit\ColdStorage;

use App\Filament\ColdStorage\ColdStorageActions;
use App\Models\ColdStorageBill;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColdStorageBillInvoiceActionTest extends TestCase
{
    #[Test]
    public function invoice_action_is_only_visible_for_posted_bills(): void
    {
        Route::get('/cold-storage/{type}/{id}/print', fn () => 'ok')->name('cold-storage.print');

        $action = ColdStorageActions::invoice();

        $this->assertSame('Invoice', $action->getLabel());
        $this->assertTrue($action->record(new ColdStorageBill(['status' => 'posted']))->isVisible());
        $this->assertFalse($action->record(new ColdStorageBill(['status' => 'draft']))->isVisible());
    }

    #[Test]
    public function storage_invoice_document_renders_invoice_heading(): void
    {
        $bill = new ColdStorageBill([
            'bill_no' => 'SB-TEST-1',
            'status' => 'posted',
            'charge_basis' => 'bag',
            'charge_period' => 'daily',
            'storage_total' => 400,
            'service_total' => 0,
            'total_amount' => 400,
            'paid_amount' => 0,
            'due_amount' => 400,
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
        ]);
        $bill->setRelation('lines', collect());
        $bill->setRelation('services', collect());
        $bill->setRelation('merchant', null);
        $bill->setRelation('business', null);
        $bill->setRelation('branch', null);
        $bill->setRelation('customer', null);

        $html = view('cold-storage.document', [
            'type' => 'bill',
            'record' => $bill,
            'currency' => 'PKR',
        ])->render();

        $this->assertStringContainsString('>Invoice</h1>', $html);
        $this->assertStringContainsString('# SB-TEST-1', $html);
        $this->assertStringContainsString('Print invoice', $html);
        $this->assertStringContainsString('Amount due', $html);
        $this->assertStringContainsString('PKR 400.00', $html);
    }
}
