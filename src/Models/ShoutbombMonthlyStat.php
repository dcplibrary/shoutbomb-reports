<?php

namespace Dcplibrary\ShoutbombFailureReports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShoutbombMonthlyStat extends Model
{
    use HasFactory;

    protected $table = 'shoutbomb_monthly_stats';

    protected $fillable = [
        'outlook_message_id',
        'subject',
        'report_month',
        'branch_name',
        'hold_text_notices',
        'hold_text_reminders',
        'hold_voice_notices',
        'hold_voice_reminders',
        'overdue_text_notices',
        'overdue_text_eligible_renewal',
        'overdue_text_ineligible_renewal',
        'overdue_text_renewed_successfully',
        'overdue_text_renewed_unsuccessfully',
        'overdue_voice_notices',
        'overdue_voice_eligible_renewal',
        'overdue_voice_ineligible_renewal',
        'renewal_text_notices',
        'renewal_text_eligible',
        'renewal_text_ineligible',
        'renewal_text_unsuccessfully',
        'renewal_text_reminders',
        'renewal_text_reminder_eligible',
        'renewal_text_reminder_ineligible',
        'renewal_voice_notices',
        'renewal_voice_eligible',
        'renewal_voice_ineligible',
        'renewal_voice_reminders',
        'renewal_voice_reminder_eligible',
        'renewal_voice_reminder_ineligible',
        'total_registered_users',
        'total_registered_barcodes',
        'total_registered_text',
        'total_registered_voice',
        'new_registrations_month',
        'new_voice_signups',
        'new_text_signups',
        'average_daily_calls',
        'keyword_usage',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'report_month' => 'date',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'keyword_usage' => 'array',
    ];

    /**
     * Scope to get stats for a specific month
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('report_month', $year)
                     ->whereMonth('report_month', $month);
    }

    /**
     * Scope to get stats for a specific branch
     */
    public function scopeForBranch($query, string $branchName)
    {
        return $query->where('branch_name', $branchName);
    }

    /**
     * Scope to get recent stats
     */
    public function scopeRecent($query, int $months = 6)
    {
        return $query->where('report_month', '>=', now()->subMonths($months));
    }

    /**
     * Mark this stat as processed
     */
    public function markAsProcessed(): bool
    {
        $this->processed_at = now();
        return $this->save();
    }
}
