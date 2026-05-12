<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('mailbox_messages', function (Blueprint $table) {
			$table->id();
			$table->string('imap_folder', 191)->default('INBOX');
			$table->unsignedBigInteger('imap_uid');
			$table->unsignedBigInteger('imap_uidvalidity');
			$table->string('dedupe_key', 64)->unique();
			$table->string('internet_message_id', 998)->nullable()->index();
			$table->string('from_email', 320);
			$table->string('from_name', 320)->nullable();
			$table->string('to_email', 320)->nullable();
			$table->text('subject')->nullable();
			$table->longText('body_plain')->nullable();
			$table->longText('body_html')->nullable();
			$table->timestamp('received_at');
			$table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
			$table->timestamps();

			$table->unique(['imap_folder', 'imap_uid', 'imap_uidvalidity'], 'mailbox_imap_uid_unique');
		});

		Schema::create('mailbox_attachments', function (Blueprint $table) {
			$table->id();
			$table->foreignId('mailbox_message_id')->constrained('mailbox_messages')->cascadeOnDelete();
			$table->string('original_filename', 512);
			$table->string('stored_path', 1024);
			$table->string('content_type', 191)->nullable();
			$table->unsignedBigInteger('size_bytes')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('mailbox_attachments');
		Schema::dropIfExists('mailbox_messages');
	}
};
