<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Boîte IMAP (ex. Infomaniak)
	|--------------------------------------------------------------------------
	|
	| Connexion en lecture seule pour importer les messages vers mailbox_messages.
	| Exemple Infomaniak : host mail.infomaniak.com, port 993, encryption ssl.
	|
	*/

	'enabled' => (bool) env('MAILBOX_IMAP_ENABLED', false),

	'host' => env('MAILBOX_IMAP_HOST', 'mail.infomaniak.com'),

	'port' => (int) env('MAILBOX_IMAP_PORT', 993),

	'encryption' => env('MAILBOX_IMAP_ENCRYPTION', 'ssl'),

	'username' => env('MAILBOX_IMAP_USERNAME'),

	'password' => env('MAILBOX_IMAP_PASSWORD'),

	'folder' => env('MAILBOX_IMAP_FOLDER', 'INBOX'),

	/** Nombre max de messages à examiner par exécution (les plus récents en premier). */
	'sync_limit' => (int) env('MAILBOX_IMAP_SYNC_LIMIT', 50),

];
