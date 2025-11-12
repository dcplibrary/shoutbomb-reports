<?php

namespace Dcplibrary\OutlookFailureReports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NoticeFailureReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlook_message_id',
        'subject',
        'from_address',
        'recipient_email',
        'patron_identifier',
        'notice_type',
        'failure_reason',
        'error_code',
        'original_message_id',
        'received_at',
        'processed_at',
        'raw_content',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the table name from config
     */
    public function getTable()
    {
        return config('outlook-failure-reports.storage.table_name', 'notice_failure_reports');
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
}
