<?php

namespace App\Console\Commands;

use App\Services\MailboxImapIngestService;
use Illuminate\Console\Command;

class SyncMailboxIncoming extends Command
{
	protected $signature = 'mailbox:sync-incoming';

	protected $description = 'Importer les e-mails IMAP (Infomaniak) vers la table mailbox_messages';

	public function handle(MailboxImapIngestService $ingest): int
	{
		$result = $ingest->sync();
		$this->line($result['message'] ?? json_encode($result));

		if (! empty($result['imported'])) {
			$this->info('Nouveaux messages : ' . (int) $result['imported']);
		}

		if (! empty($result['skipped'])) {
			return self::SUCCESS;
		}

		return ! empty($result['ok']) ? self::SUCCESS : self::FAILURE;
	}
}
