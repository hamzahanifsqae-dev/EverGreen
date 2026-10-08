<?php

namespace Tests\Unit\ColdStorage;

use App\Filament\Resources\ColdStorageBills\ColdStorageBillResource;
use App\Models\ColdStorageBill;
use App\Models\ColdStorageChamber;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColdStorageResourceEditabilityTest extends TestCase
{
    #[Test]
    public function draft_bills_are_editable_and_posted_bills_are_not(): void
    {
        $draft = new ColdStorageBill(['status' => 'draft']);
        $posted = new ColdStorageBill(['status' => 'posted']);

        $this->assertTrue(ColdStorageBillResource::recordAllowsEditing($draft));
        $this->assertFalse(ColdStorageBillResource::recordAllowsEditing($posted));
    }

    #[Test]
    public function records_without_status_remain_editable(): void
    {
        $chamber = new ColdStorageChamber(['name' => 'Chamber A']);

        $this->assertTrue(ColdStorageBillResource::recordAllowsEditing($chamber));
    }
}
