<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrintJob extends Model
{
    protected $fillable = [
        'printable_type',
        'printable_id',
        'document_type',
        'category_id',
        'printer_id',
        'status',
        'attempts',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
        ];
    }

    public function printable(): MorphTo
    {
        return $this->morphTo();
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }
}
