<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailboxMessage extends Model
{
	protected $fillable = [
		'imap_folder',
		'imap_uid',
		'imap_uidvalidity',
		'dedupe_key',
		'internet_message_id',
		'from_email',
		'from_name',
		'to_email',
		'subject',
		'body_plain',
		'body_html',
		'received_at',
		'pet_id',
	];

	protected $casts = [
		'received_at' => 'datetime',
	];

	public function pet(): BelongsTo
	{
		return $this->belongsTo(Pet::class);
	}

	public function attachments(): HasMany
	{
		return $this->hasMany(MailboxAttachment::class);
	}
}
