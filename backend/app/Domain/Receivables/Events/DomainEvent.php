<?php

namespace App\Domain\Receivables\Events;

use Carbon\Carbon;

abstract class DomainEvent
{
    public string $occurredAt;

    public function __construct()
    {
        $this->occurredAt = Carbon::now()->toIso8601String();
    }

    abstract public function getEventName(): string;
}