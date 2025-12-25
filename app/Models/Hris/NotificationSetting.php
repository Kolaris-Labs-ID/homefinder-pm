<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    protected $table = 'hris_notification_settings';

    protected $fillable = [
        'employee_id',
        'enabled',
        'check_in_reminder',
        'check_out_reminder',
        'break_reminder',
        'announcements',
        'leave_updates',
        'reimbursement_updates',
        'payslip_notifications',
        'weekly_todo_reminder',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'check_in_reminder' => 'boolean',
        'check_out_reminder' => 'boolean',
        'break_reminder' => 'boolean',
        'announcements' => 'boolean',
        'leave_updates' => 'boolean',
        'reimbursement_updates' => 'boolean',
        'payslip_notifications' => 'boolean',
        'weekly_todo_reminder' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
