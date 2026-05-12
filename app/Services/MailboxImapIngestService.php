<?php

namespace App\Services;

use App\Models\MailboxAttachment;
use App\Models\MailboxMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MailboxImapIngestService
{
	public function isImapExtensionAvailable(): bool
	{
		return \function_exists('imap_open');
	}

	public function sync(): array
	{
		if (! config('mailbox.enabled')) {
			return ['ok' => false, 'skipped' => true, 'message' => 'MAILBOX_IMAP_ENABLED=false'];
		}

		if (! $this->isImapExtensionAvailable()) {
			return ['ok' => false, 'skipped' => true, 'message' => 'Extension PHP imap (ext-imap) absente sur ce serveur.'];
		}

		$user = (string) config('mailbox.username');
		$pass = (string) config('mailbox.password');
		if ($user === '' || $pass === '') {
			return ['ok' => false, 'skipped' => true, 'message' => 'MAILBOX_IMAP_USERNAME / MAILBOX_IMAP_PASSWORD manquants.'];
		}

		$host = (string) config('mailbox.host');
		$port = (int) config('mailbox.port');
		$enc = strtolower((string) config('mailbox.encryption'));
		$folder = (string) config('mailbox.folder');
		$limit = max(1, (int) config('mailbox.sync_limit'));

		$encFlag = $enc === 'tls' ? 'tls' : 'ssl';
		$mailbox = sprintf('{%s:%d/imap/%s}%s', $host, $port, $encFlag, $folder);

		$inbox = @\imap_open($mailbox, $user, $pass, \OP_READONLY);

		if ($inbox === false) {
			$err = \imap_last_error() ?: 'Connexion IMAP impossible';

			return ['ok' => false, 'skipped' => false, 'message' => $err];
		}

		try {
			$status = \imap_status($inbox, $mailbox, \SA_UIDVALIDITY);
			if ($status === false) {
				return [
					'ok' => false,
					'skipped' => false,
					'message' => 'imap_status a échoué : ' . (\imap_last_error() ?: 'inconnu'),
				];
			}
			$uidvalidity = (int) ($status->uidvalidity ?? 0);

			$uids = \imap_sort($inbox, \SORTDATE, 1, \SE_UID);
			if ($uids === false || $uids === []) {
				return ['ok' => true, 'imported' => 0, 'message' => 'Aucun message dans la boîte.'];
			}

			$uids = array_slice($uids, 0, $limit);
			$imported = 0;

			foreach ($uids as $uid) {
				$uid = (int) $uid;
				if (MailboxMessage::query()
					->where('imap_folder', $folder)
					->where('imap_uid', $uid)
					->where('imap_uidvalidity', $uidvalidity)
					->exists()) {
					continue;
				}

				$overview = \imap_fetch_overview($inbox, (string) $uid, \FT_UID);
				if (! $overview || ! isset($overview[0])) {
					continue;
				}
				$ov = $overview[0];

				$headerRaw = \imap_fetchheader($inbox, $uid, \FT_UID) ?: '';
				$messageId = $this->extractMessageId($headerRaw);
				$dedupe = $this->buildDedupeKey($messageId, $uidvalidity, $uid, $ov);

				if (MailboxMessage::query()->where('dedupe_key', $dedupe)->exists()) {
					continue;
				}

				[$fromEmail, $fromName] = $this->parseFromOverview($ov);
				$subject = isset($ov->subject) ? $this->decodeMimeHeader((string) $ov->subject) : null;
				$receivedAt = $this->parseDate($ov);

				$parsed = $this->extractBodiesAndAttachments($inbox, $uid);

				$msg = MailboxMessage::query()->create([
					'imap_folder' => $folder,
					'imap_uid' => $uid,
					'imap_uidvalidity' => $uidvalidity,
					'dedupe_key' => $dedupe,
					'internet_message_id' => $messageId,
					'from_email' => $fromEmail,
					'from_name' => $fromName,
					'to_email' => (string) config('mailbox.username'),
					'subject' => $subject,
					'body_plain' => $parsed['plain'] !== '' ? $parsed['plain'] : null,
					'body_html' => $parsed['html'] !== '' ? $parsed['html'] : null,
					'received_at' => $receivedAt,
					'pet_id' => null,
				]);

				foreach ($parsed['attachments'] as $att) {
					$safeName = $this->sanitizeFilename($att['name']);
					$relPath = 'mailbox_attachments/' . $msg->id . '/' . Str::uuid() . '_' . $safeName;
					Storage::disk('local')->put($relPath, $att['content']);
					MailboxAttachment::query()->create([
						'mailbox_message_id' => $msg->id,
						'original_filename' => mb_substr($att['name'], 0, 500),
						'stored_path' => $relPath,
						'content_type' => $att['mime'],
						'size_bytes' => strlen($att['content']),
					]);
				}

				$imported++;
			}

			return ['ok' => true, 'imported' => $imported, 'message' => "Import terminé ({$imported} nouveau(x) message(s))."];
		} finally {
			\imap_close($inbox);
		}
	}

	private function buildDedupeKey(?string $messageId, int $uidvalidity, int $uid, object $ov): string
	{
		if ($messageId !== null && $messageId !== '') {
			return sha1(strtolower(trim($messageId)));
		}

		$from = (string) ($ov->from ?? '');
		$subject = (string) ($ov->subject ?? '');
		$date = (string) ($ov->date ?? '');
		$size = (string) ($ov->size ?? '');

		return sha1($uidvalidity . '|' . $uid . '|' . $from . '|' . $subject . '|' . $date . '|' . $size);
	}

	private function extractMessageId(string $headerRaw): ?string
	{
		if (preg_match('/^Message-ID:\s*(.+)$/im', $headerRaw, $m)) {
			$id = trim($m[1]);
			$id = trim($id, "<> \t\r\n");

			return $id !== '' ? mb_substr($id, 0, 998) : null;
		}

		return null;
	}

	private function parseFromOverview(object $ov): array
	{
		$raw = (string) ($ov->from ?? '');
		if ($raw === '') {
			return ['inconnu@invalid.local', null];
		}

		if (preg_match('/^(?P<name>.*?)\s*<(?P<email>[^>]+)>$/s', $raw, $m)) {
			$name = trim($m['name'], " \t\"'");
			$email = strtolower(trim($m['email']));

			return [$email, $this->decodeMimeHeader($name) ?: null];
		}

		if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
			return [strtolower($raw), null];
		}

		return [Str::slug(mb_substr($raw, 0, 40)) . '@invalid.local', $this->decodeMimeHeader($raw)];
	}

	private function parseDate(object $ov): \DateTimeInterface
	{
		$d = $ov->date ?? null;
		if ($d) {
			$t = strtotime((string) $d);

			if ($t !== false) {
				return \Carbon\Carbon::createFromTimestampUTC($t);
			}
		}

		return now();
	}

	private function decodeMimeHeader(string $s): string
	{
		$s = trim($s);
		if ($s === '') {
			return '';
		}
		if (function_exists('iconv_mime_decode')) {
			$decoded = @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
			if (is_string($decoded) && $decoded !== '') {
				return $decoded;
			}
		}

		return $s;
	}

	/**
	 * @return array{plain: string, html: string, attachments: list<array{name: string, content: string, mime: ?string}>}
	 */
	private function extractBodiesAndAttachments($inbox, int $uid): array
	{
		$plain = '';
		$html = '';
		$attachments = [];

		$structure = \imap_fetchstructure($inbox, $uid, \FT_UID);
		if (! $structure) {
			$raw = \imap_body($inbox, $uid, \FT_UID);

			return [
				'plain' => (string) $raw,
				'html' => '',
				'attachments' => [],
			];
		}

		if (! isset($structure->parts) || ! is_array($structure->parts)) {
			$raw = \imap_body($inbox, $uid, \FT_UID);
			$body = $this->decodeBody((string) $raw, (int) ($structure->encoding ?? 0));
			$subtype = strtolower((string) ($structure->subtype ?? ''));
			if ($subtype === 'html') {
				$html = $body;
			} else {
				$plain = $body;
			}

			return compact('plain', 'html', 'attachments');
		}

		$this->walkParts($inbox, $uid, $structure->parts, '', $plain, $html, $attachments);

		return compact('plain', 'html', 'attachments');
	}

	/**
	 * @param array<int, object> $parts
	 * @param list<array{name: string, content: string, mime: ?string}> $attachments
	 */
	private function walkParts($inbox, int $uid, array $parts, string $prefix, string &$plain, string &$html, array &$attachments): void
	{
		foreach ($parts as $idx => $part) {
			$partNo = $prefix === '' ? (string) ($idx + 1) : $prefix . '.' . ($idx + 1);

			if (isset($part->parts) && is_array($part->parts)) {
				$this->walkParts($inbox, $uid, $part->parts, $partNo, $plain, $html, $attachments);

				continue;
			}

			$subtype = strtolower((string) ($part->subtype ?? ''));
			$disposition = '';
			if (! empty($part->ifdisposition)) {
				$disposition = strtolower((string) $part->disposition);
			}

			$filename = $this->partFilename($part);
			$raw = \imap_fetchbody($inbox, $uid, $partNo, \FT_UID);
			$decoded = $this->decodeBody((string) $raw, (int) ($part->encoding ?? 0));

			$isBodySubtype = in_array($subtype, ['plain', 'html'], true);

			if ($isBodySubtype && $disposition !== 'attachment') {
				if ($subtype === 'plain') {
					$plain .= $decoded;
				} else {
					$html .= $decoded;
				}

				continue;
			}

			$isAttachment = $disposition === 'attachment'
				|| ($disposition === 'inline' && $filename !== '' && ! $isBodySubtype);

			if ($isAttachment) {
				$useName = $filename !== '' ? $filename : ('piece-jointe-' . str_replace('.', '-', $partNo));
				$attachments[] = [
					'name' => $useName,
					'content' => $decoded,
					'mime' => $this->mimeFromPart($part),
				];

				continue;
			}

			if ($subtype === 'plain') {
				$plain .= $decoded;
			} elseif ($subtype === 'html') {
				$html .= $decoded;
			}
		}
	}

	private function partFilename(object $part): string
	{
		$name = '';
		if (! empty($part->ifparameters) && is_array($part->parameters)) {
			foreach ($part->parameters as $p) {
				if (strtolower((string) $p->attribute) === 'name') {
					$name = (string) $p->value;
				}
			}
		}
		if ($name === '' && ! empty($part->ifdparameters) && is_array($part->dparameters)) {
			foreach ($part->dparameters as $p) {
				if (strtolower((string) $p->attribute) === 'filename') {
					$name = (string) $p->value;
				}
			}
		}

		return $name !== '' ? $this->decodeMimeHeader($name) : '';
	}

	private function decodeBody(string $body, int $encoding): string
	{
		switch ($encoding) {
			case 3:
				$decoded = base64_decode($body, true);

				return $decoded !== false ? $decoded : $body;
			case 4:
				return quoted_printable_decode($body);
			default:
				return str_replace(["\r\n", "\r"], "\n", $body);
		}
	}

	private function mimeFromPart(object $part): ?string
	{
		if (empty($part->ifsubtype)) {
			return null;
		}
		$map = [
			0 => 'text',
			1 => 'multipart',
			2 => 'message',
			3 => 'application',
			4 => 'audio',
			5 => 'image',
			6 => 'video',
			7 => 'model',
			8 => 'other',
		];
		$typeNum = (int) ($part->type ?? 8);
		$prefix = $map[$typeNum] ?? 'application';

		return $prefix . '/' . strtolower((string) $part->subtype);
	}

	private function sanitizeFilename(string $name): string
	{
		$name = basename(str_replace(['../', '..\\'], '', $name));
		$name = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $name) ?: 'piece-jointe';

		return mb_substr($name, 0, 180);
	}
}
