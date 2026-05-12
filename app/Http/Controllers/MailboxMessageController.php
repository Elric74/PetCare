<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\MailboxAttachment;
use App\Models\MailboxMessage;
use App\Models\Pet;
use App\Services\MailboxImapIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MailboxMessageController extends Controller
{
	public function index(Request $request): Response
	{
		$page = max(1, (int) $request->query('page', 1));
		$unassignedOnly = ! $request->has('unassigned_only') || $request->boolean('unassigned_only');

		$query = MailboxMessage::query()
			->with(['attachments', 'pet.client']);

		if ($unassignedOnly) {
			$query->whereNull('pet_id');
		}

		$paginator = $query
			->orderByDesc('received_at')
			->paginate(25, ['*'], 'page', $page)
			->withQueryString();

		$pets = Pet::query()
			->with('client:id,name')
			->orderBy('name')
			->get(['id', 'name', 'client_id'])
			->map(function (Pet $pet): array {
				$ownerName = $pet->client?->name;
				$label = $pet->name;
				if (! empty($ownerName)) {
					$label .= ' (' . $ownerName . ')';
				}

				return [
					'id' => $pet->id,
					'name' => $pet->name,
					'owner_name' => $ownerName,
					'label' => $label,
				];
			})
			->values();

		$messages = collect($paginator->items())->map(function (MailboxMessage $m): array {
			return [
				'id' => $m->id,
				'from_email' => $m->from_email,
				'from_name' => $m->from_name,
				'subject' => $m->subject,
				'body_plain' => $m->body_plain,
				'received_at' => optional($m->received_at)->toIso8601String(),
				'pet_id' => $m->pet_id,
				'pet' => $m->pet ? [
					'id' => $m->pet->id,
					'name' => $m->pet->name,
					'slug' => $m->pet->slug,
				] : null,
				'attachments' => $m->attachments->map(fn (MailboxAttachment $a): array => [
					'id' => $a->id,
					'original_filename' => $a->original_filename,
					'download_url' => route('mailbox.attachment.download', ['mailboxAttachment' => $a->id]),
				])->values()->all(),
			];
		})->values()->all();

		return Inertia::render('Mailbox/Index', [
			'messages' => $messages,
			'meta' => [
				'currentPage' => $paginator->currentPage(),
				'lastPage' => $paginator->lastPage(),
				'totalItems' => $paginator->total(),
				'perPage' => $paginator->perPage(),
			],
			'pets' => $pets,
			'unassigned_only' => $unassignedOnly,
		]);
	}

	public function sync(MailboxImapIngestService $ingest): JsonResponse
	{
		$result = $ingest->sync();

		return response()->json($result, $result['ok'] ? 200 : 422);
	}

	public function associate(Request $request, MailboxMessage $mailboxMessage): JsonResponse
	{
		$validated = $request->validate([
			'pet_id' => ['required', 'integer', 'exists:pets,id'],
		]);

		$newPetId = (int) $validated['pet_id'];
		$previousPetId = $mailboxMessage->pet_id;

		$mailboxMessage->pet_id = $newPetId;
		$mailboxMessage->save();

		$mailboxMessage->load('attachments');

		if ($previousPetId && (int) $previousPetId !== $newPetId) {
			$this->removeMailboxMessageGalleryImages($mailboxMessage, (int) $previousPetId);
		}

		$this->publishAttachmentsToPetGallery($mailboxMessage, $newPetId);

		return response()->json(['message' => 'Message associé à l’animal.']);
	}

	public function downloadAttachment(MailboxAttachment $mailboxAttachment): StreamedResponse|\Illuminate\Http\Response
	{
		$path = $mailboxAttachment->stored_path;
		if (! Storage::disk('local')->exists($path)) {
			abort(404);
		}

		return Storage::disk('local')->download($path, $mailboxAttachment->original_filename);
	}

	private function galleryStoragePrefix(MailboxMessage $message): string
	{
		return 'mailbox_attachments/msg-' . $message->id;
	}

	/**
	 * Retire de la galerie d’un animal les fichiers issus de ce message (changement d’association).
	 */
	private function removeMailboxMessageGalleryImages(MailboxMessage $message, int $petId): void
	{
		$like = 'storage/' . $this->galleryStoragePrefix($message) . '/%';

		$images = Image::query()
			->where('pet_id', $petId)
			->where('path', 'like', $like)
			->get();

		foreach ($images as $img) {
			$relative = str_starts_with($img->path, 'storage/')
				? substr($img->path, strlen('storage/'))
				: $img->path;

			if (Storage::disk('public')->exists($relative)) {
				Storage::disk('public')->delete($relative);
			}

			$img->delete();
		}
	}

	/**
	 * Copie les pièces jointes vers le disque public et crée les lignes images (onglet Galerie), comme pour les rapports lab.
	 */
	private function publishAttachmentsToPetGallery(MailboxMessage $message, int $petId): void
	{
		$galleryExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

		foreach ($message->attachments as $attachment) {
			$ext = strtolower(
				(string) (pathinfo($attachment->original_filename, PATHINFO_EXTENSION)
					?: pathinfo($attachment->stored_path, PATHINFO_EXTENSION)
					?: 'bin')
			);

			if (! in_array($ext, $galleryExtensions, true)) {
				continue;
			}

			if (! Storage::disk('local')->exists($attachment->stored_path)) {
				continue;
			}

			$prefix = $this->galleryStoragePrefix($message);
			$destRelative = $prefix . '/att-' . $attachment->id . '.' . $ext;

			Storage::disk('public')->put(
				$destRelative,
				Storage::disk('local')->get($attachment->stored_path)
			);

			$galleryPath = 'storage/' . $destRelative;

			Image::query()->firstOrCreate(
				['pet_id' => $petId, 'path' => $galleryPath],
			);
		}
	}
}
