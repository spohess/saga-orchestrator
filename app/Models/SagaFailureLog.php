<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SagaFailureLog extends Model
{
    protected $fillable = [
        'saga_id',
        'failed_step',
        'exception_class',
        'exception_message',
        'executed_steps',
        'compensated_steps',
        'compensation_failures',
        'context_snapshot',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'executed_steps' => 'array',
            'compensated_steps' => 'array',
            'compensation_failures' => 'array',
            'context_snapshot' => 'array',
        ];
    }
}
