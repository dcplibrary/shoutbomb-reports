<?php

namespace Dcplibrary\ShoutbombFailureReports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NoticeFailureReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlook_message_id',
        'subject',
        'from_address',
        'patron_phone',
        'patron_id',
        'patron_barcode',
        'barcode_partial',
        'patron_name',
        'notice_type',
        'failure_type',
        'failure_reason',
        'account_status',
        'notice_description',
        'attempt_count',
        'received_at',
        'processed_at',
        'raw_content',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'attempt_count' => 'integer',
        'barcode_partial' => 'boolean',
    ];

    /**
     * Get the table name from config
     */
    public function getTable()
    {
        return config('shoutbomb-reports.storage.table_name', 'notice_failure_reports');
    }

    /**
     * Scope to get unprocessed reports
     */
    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    /**
     * Scope to get reports by notice type
     */
    public function scopeByNoticeType($query, string $type)
    {
        return $query->where('notice_type', $type);
    }

    /**
     * Scope to get reports by failure type
     */
    public function scopeByFailureType($query, string $type)
    {
        return $query->where('failure_type', $type);
    }

    /**
     * Scope to get opted-out patrons
     */
    public function scopeOptedOut($query)
    {
        return $query->where('failure_type', 'opted-out');
    }

    /**
     * Scope to get invalid phone numbers
     */
    public function scopeInvalid($query)
    {
        return $query->where('failure_type', 'invalid');
    }

    /**
     * Scope to get reports for a specific patron
     */
    public function scopeForPatron($query, string $patronIdOrPhone)
    {
        return $query->where(function ($q) use ($patronIdOrPhone) {
            $q->where('patron_id', $patronIdOrPhone)
              ->orWhere('patron_phone', $patronIdOrPhone)
              ->orWhere('patron_barcode', $patronIdOrPhone);
        });
    }

    /**
     * Scope to get recent reports
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('received_at', '>=', now()->subDays($days));
    }

    /**
     * Mark this report as processed
     */
    public function markAsProcessed(): bool
    {
        $this->processed_at = now();
        return $this->save();
    }

    /**
     * Check if this is an opted-out failure
     */
    public function isOptedOut(): bool
    {
        return $this->failure_type === 'opted-out';
    }

    /**
     * Check if this is an invalid phone number
     */
    public function isInvalid(): bool
    {
        return $this->failure_type === 'invalid';
    }

    /**
     * Check if this has a partial (redacted) barcode
     */
    public function hasPartialBarcode(): bool
    {
        return $this->barcode_partial === true;
    }

    /**
     * Scope to get reports with partial barcodes only
     */
    public function scopePartialBarcodes($query)
    {
        return $query->where('barcode_partial', true);
    }

    /**
     * Scope to get invalid/removed barcodes
     */
    public function scopeInvalidBarcodes($query)
    {
        return $query->where('failure_type', 'invalid-barcode-removed');
    }

    /**
     * Check if account is deleted
     */
    public function isAccountDeleted(): bool
    {
        return $this->account_status === 'deleted';
    }

    /**
     * Check if account is unavailable
     */
    public function isAccountUnavailable(): bool
    {
        return in_array($this->account_status, ['deleted', 'unavailable']);
    }

    /**
     * Scope to get deleted/unavailable accounts
     */
    public function scopeUnavailableAccounts($query)
    {
        return $query->whereIn('account_status', ['deleted', 'unavailable']);
    }

    /**
     * Scope to get deleted accounts
     */
    public function scopeDeletedAccounts($query)
    {
        return $query->where('account_status', 'deleted');
    }
}
