<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use OwenIt\Auditing\Auditable;

trait IsAuditedUuid
{
    use Auditable;
    use HasUuids;

    public function initializeIsAuditedUuid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
