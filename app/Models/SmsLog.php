<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $table = 'sms_log';
    public $timestamps = false;
    protected $fillable = [
        'pet_id',
        'vaccination_id',
            'medication_id',
        'message',
        'sent_at',
        'sms_sent_as_normal',
        'sms_sent_as_overdue',
        'sms_sent_as_late',
        'medication_sms_sent_as_normal',
        'medication_sms_sent_as_overdue',
        'medication_sms_sent_as_late',
    ];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }
}
