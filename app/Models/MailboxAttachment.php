<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxAttachment extends Model
{
	protected $fillable = [
		'mailbox_message_id',
		'original_filename',
		'stored_path',
		'content_type',
		'size_bytes',
	];

	public function message(): BelongsTo
	{
		return $this->belongsTo(MailboxMessage::class, 'mailbox_message_id');
	}
}
