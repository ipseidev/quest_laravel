<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Models\Character;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Services\Upload\BinaryUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class UploadController extends Controller
{
    private const ATTACHMENT_MIMES = [
        'image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp', 'image/gif',
    ];

    private const ATTACHMENT_MAX_BYTES = 25 * 1024 * 1024;

    private const AUDIO_MIMES = [
        'audio/mp4', 'audio/m4a', 'audio/aac', 'audio/mpeg', 'audio/wav', 'audio/x-wav',
    ];

    private const AUDIO_MAX_BYTES = 50 * 1024 * 1024;

    public function __construct(private readonly BinaryUploadService $service) {}

    public function attachment(UploadFileRequest $request, string $attachmentId): JsonResponse
    {
        $attachment = EntryAttachment::query()->find($attachmentId);
        if ($attachment === null) {
            return $this->notFound();
        }
        if ($attachment->remote_uri !== null) {
            return $this->alreadyUploaded();
        }
        if (($error = $this->validateUpload($request->file('file'), self::ATTACHMENT_MIMES, self::ATTACHMENT_MAX_BYTES)) !== null) {
            return $error;
        }

        $url = $this->service->store('attachments', $request->user()->id, $attachmentId, $request->file('file'));

        $attachment->remote_uri = $url;
        $attachment->save();

        return response()->json(['remoteUri' => $url]);
    }

    public function audio(UploadFileRequest $request, string $audioId): JsonResponse
    {
        $audio = EntryAudio::query()->find($audioId);
        if ($audio === null) {
            return $this->notFound();
        }
        if ($audio->remote_uri !== null) {
            return $this->alreadyUploaded();
        }
        if (($error = $this->validateUpload($request->file('file'), self::AUDIO_MIMES, self::AUDIO_MAX_BYTES)) !== null) {
            return $error;
        }

        $url = $this->service->store('audio', $request->user()->id, $audioId, $request->file('file'));

        $audio->remote_uri = $url;
        $audio->save();

        return response()->json(['remoteUri' => $url]);
    }

    public function characterPhoto(UploadFileRequest $request, string $characterId): JsonResponse
    {
        $character = Character::query()->find($characterId);
        if ($character === null) {
            return $this->notFound();
        }
        if ($character->remote_photo_uri !== null) {
            return $this->alreadyUploaded();
        }
        if (($error = $this->validateUpload($request->file('file'), self::ATTACHMENT_MIMES, self::ATTACHMENT_MAX_BYTES)) !== null) {
            return $error;
        }

        $url = $this->service->store('character-photos', $request->user()->id, $characterId, $request->file('file'));

        $character->remote_photo_uri = $url;
        $character->save();

        return response()->json(['remoteUri' => $url]);
    }

    private function validateUpload(UploadedFile $file, array $allowedMimes, int $maxBytes): ?JsonResponse
    {
        if (! in_array($file->getClientMimeType(), $allowedMimes, true)) {
            return response()->json([
                'error' => 'unsupported_media_type',
                'message' => 'The uploaded file type is not supported.',
            ], ResponseAlias::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        if ($file->getSize() > $maxBytes) {
            return response()->json([
                'error' => 'payload_too_large',
                'message' => 'The uploaded file exceeds the maximum allowed size.',
            ], ResponseAlias::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return null;
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'error' => 'not_found',
            'message' => 'Resource not found.',
        ], 404);
    }

    private function alreadyUploaded(): JsonResponse
    {
        return response()->json([
            'error' => 'already_uploaded',
            'message' => 'A binary has already been uploaded for this resource.',
        ], 409);
    }
}
