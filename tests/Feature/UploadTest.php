<?php

namespace Tests\Feature;

use App\Exceptions\BinaryStorageException;
use App\Models\Character;
use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Models\EntryVideo;
use App\Models\User;
use App\Services\Upload\BinaryUploadService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('mobile')->plainTextToken;
    }

    private function bearer(?string $token = null): array
    {
        return ['Authorization' => 'Bearer '.($token ?? $this->token)];
    }

    /**
     * Grant the test user Nacre Plus. Video cloud backup is subscriber-only
     * (`UploadController::videoRequiresPlus`), so every video happy path has to
     * say so explicitly — a video test on the default free user asserts the
     * refusal, not the upload.
     */
    private function subscribeUser(): void
    {
        $this->user->forceFill([
            'subscription_product_id' => 'annual',
            'subscription_expires_at' => now()->addYear(),
        ])->save();
    }

    /**
     * A genuine 10x10 HEIC (HEVC-coded, `heic` brand), the same container an iPhone
     * produces.
     *
     * This used to be a PNG advertised as image/heic, which made all three HEIC tests
     * pass without ever exercising a HEIC decode — they were green while production
     * failed on every photo. If this fixture is ever swapped for a stand-in, the tests
     * stop testing anything.
     */
    private function realHeicUpload(string $name = 'photo.heic', string $mime = 'image/heic'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'heic_');
        copy(self::heicFixturePath(), $tmp);

        return new UploadedFile($tmp, $name, $mime, null, true);
    }

    private static function heicFixturePath(): string
    {
        return base_path('tests/Fixtures/real.heic');
    }

    /**
     * Skip loudly rather than pass quietly when the host cannot decode HEIC.
     *
     * Decoding needs three things that are all environment, not code: the imagick
     * extension, an ImageMagick policy that grants the HEIC coder read rights (the
     * hardened default denies every coder then re-allows only GIF/JPEG/PNG/WEBP), and
     * libheif with an HEVC decoder. A skipped test says so; a faked one does not.
     */
    private function skipWithoutHeicDecoding(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('The imagick extension is not installed.');
        }

        try {
            (new \Imagick)->readImageBlob((string) file_get_contents(self::heicFixturePath()));
        } catch (\Throwable $e) {
            $this->markTestSkipped('This ImageMagick cannot decode HEIC: '.$e->getMessage());
        }
    }

    public function test_b3_upload_valid_image_to_attachment(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $file]);

        $response->assertOk()
            ->assertJsonStructure(['remoteUri']);

        $remoteUri = $response->json('remoteUri');
        $this->assertNotNull($remoteUri);

        $files = Storage::disk('s3')->files('attachments/'.$this->user->id);
        $this->assertNotEmpty($files);
        $this->assertStringContainsString($att->id, $files[0]);

        $att->refresh();
        $this->assertSame($remoteUri, $att->remote_uri);
    }

    public function test_b4_upload_to_nonexistent_attachment_returns_404(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.Str::uuid(), ['file' => $file])
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    public function test_b5_re_upload_returns_409_already_uploaded(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $file = UploadedFile::fake()->image('photo.jpg');

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $file])
            ->assertOk();

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => UploadedFile::fake()->image('again.jpg')])
            ->assertStatus(409)
            ->assertJsonPath('error', 'already_uploaded');
    }

    public function test_b6_unsupported_mime_returns_415(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $file])
            ->assertStatus(415)
            ->assertJsonPath('error', 'unsupported_media_type');
    }

    public function test_b9_upload_valid_audio(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $audio = EntryAudio::factory()->for($entry)->create(['remote_uri' => null]);

        $file = UploadedFile::fake()->create('note.m4a', 200, 'audio/mp4');

        $response = $this->withHeaders($this->bearer())
            ->post('/api/uploads/audio/'.$audio->id, ['file' => $file]);

        $response->assertOk()
            ->assertJsonStructure(['remoteUri']);

        $audio->refresh();
        $this->assertNotNull($audio->remote_uri);

        $files = Storage::disk('s3')->files('audio/'.$this->user->id);
        $this->assertNotEmpty($files);
    }

    public function test_b9_re_upload_audio_returns_409(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $audio = EntryAudio::factory()->for($entry)->create(['remote_uri' => null]);

        $file = UploadedFile::fake()->create('note.m4a', 200, 'audio/mp4');
        $this->withHeaders($this->bearer())->post('/api/uploads/audio/'.$audio->id, ['file' => $file])->assertOk();
        $this->withHeaders($this->bearer())
            ->post('/api/uploads/audio/'.$audio->id, ['file' => UploadedFile::fake()->create('a.m4a', 100, 'audio/mp4')])
            ->assertStatus(409);
    }

    public function test_b9_unsupported_audio_mime_returns_415(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $audio = EntryAudio::factory()->for($entry)->create(['remote_uri' => null]);

        $file = UploadedFile::fake()->create('movie.mp4', 200, 'video/mp4');

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/audio/'.$audio->id, ['file' => $file])
            ->assertStatus(415);
    }

    public function test_x3_upload_to_foreign_attachment_returns_404(): void
    {
        $otherUser = User::factory()->create();
        $otherEntry = Entry::factory()->for($otherUser)->create();
        $otherAtt = EntryAttachment::factory()->for($otherEntry)->create(['remote_uri' => null]);

        $file = UploadedFile::fake()->image('photo.jpg');

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$otherAtt->id, ['file' => $file])
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');

        $otherAtt->refresh();
        $this->assertNull($otherAtt->remote_uri);
    }

    public function test_upload_bumps_attachment_updated_at_so_other_devices_pull_the_change(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $beforeUpdatedAt = $att->updated_at;

        // Tiny sleep to ensure the next now() is strictly greater (ms precision)
        usleep(10_000);

        $file = UploadedFile::fake()->image('photo.jpg');
        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $file])
            ->assertOk();

        $att->refresh();
        $this->assertTrue($att->updated_at->greaterThan($beforeUpdatedAt));
    }

    public function test_upload_unauthenticated_returns_401(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create();

        $this->post('/api/uploads/attachments/'.$att->id, [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_character_photo_upload_works(): void
    {
        $character = Character::factory()->for($this->user)->create(['remote_photo_uri' => null]);

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->withHeaders($this->bearer())
            ->post('/api/uploads/character-photos/'.$character->id, ['file' => $file]);

        $response->assertOk()->assertJsonStructure(['remoteUri']);

        $character->refresh();
        $this->assertNotNull($character->remote_photo_uri);

        $files = Storage::disk('s3')->files('character-photos/'.$this->user->id);
        $this->assertNotEmpty($files);
    }

    public function test_character_photo_upload_to_foreign_returns_404(): void
    {
        $otherUser = User::factory()->create();
        $otherCharacter = Character::factory()->for($otherUser)->create();

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/character-photos/'.$otherCharacter->id, ['file' => UploadedFile::fake()->image('a.jpg')])
            ->assertStatus(404);
    }

    public function test_heic_attachment_is_re_encoded_to_jpeg(): void
    {
        $this->skipWithoutHeicDecoding();

        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $response = $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $this->realHeicUpload()]);

        $response->assertOk();
        $remoteUri = $response->json('remoteUri');
        $this->assertStringEndsWith('.jpg', $remoteUri);

        $expectedPath = 'attachments/'.$this->user->id.'/'.$att->id.'.jpg';
        Storage::disk('s3')->assertExists($expectedPath);
        Storage::disk('s3')->assertMissing('attachments/'.$this->user->id.'/'.$att->id.'.heic');

        // JPEG magic bytes.
        $bytes = Storage::disk('s3')->get($expectedPath);
        $this->assertSame("\xFF\xD8\xFF", substr($bytes, 0, 3));

        $att->refresh();
        $this->assertStringEndsWith('.jpg', $att->remote_uri);
    }

    public function test_heic_character_photo_is_re_encoded_to_jpeg(): void
    {
        $this->skipWithoutHeicDecoding();

        $character = Character::factory()->for($this->user)->create(['remote_photo_uri' => null]);

        $response = $this->withHeaders($this->bearer())
            ->post('/api/uploads/character-photos/'.$character->id, ['file' => $this->realHeicUpload('avatar.heic')]);

        $response->assertOk();
        $this->assertStringEndsWith('.jpg', $response->json('remoteUri'));

        $expectedPath = 'character-photos/'.$this->user->id.'/'.$character->id.'.jpg';
        Storage::disk('s3')->assertExists($expectedPath);
    }

    public function test_heif_attachment_is_re_encoded_to_jpeg(): void
    {
        $this->skipWithoutHeicDecoding();

        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $response = $this->withHeaders($this->bearer())->post(
            '/api/uploads/attachments/'.$att->id,
            ['file' => $this->realHeicUpload('photo.heif', 'image/heif')]
        );

        $response->assertOk();
        $this->assertStringEndsWith('.jpg', $response->json('remoteUri'));
    }

    /**
     * The declared Content-Type is a claim; the ISOBMFF brand is a fact. Routing on the
     * bytes is what makes the re-encode independent of the host's libmagic, which
     * recognises HEIC on some Ubuntu releases and not others.
     */
    public function test_a_heic_declared_as_jpeg_is_still_re_encoded(): void
    {
        $this->skipWithoutHeicDecoding();

        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $response = $this->withHeaders($this->bearer())->post(
            '/api/uploads/attachments/'.$att->id,
            ['file' => $this->realHeicUpload('photo.jpg', 'image/jpeg')]
        );

        $response->assertOk();
        $expectedPath = 'attachments/'.$this->user->id.'/'.$att->id.'.jpg';
        Storage::disk('s3')->assertExists($expectedPath);
        $this->assertSame("\xFF\xD8\xFF", substr(Storage::disk('s3')->get($expectedPath), 0, 3));
    }

    /**
     * Voice notes are ISOBMFF as well — an .m4a carries an `ftyp` box too. Only
     * still-image brands may route to the JPEG encoder, or every audio upload would be
     * handed to Imagick and fail.
     */
    public function test_an_audio_upload_is_not_mistaken_for_a_heif_image(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $audio = EntryAudio::factory()->for($entry)->create(['remote_uri' => null]);

        $tmp = tempnam(sys_get_temp_dir(), 'm4a_');
        // ftyp box with the brands an .m4a actually carries, none of them HEIF.
        file_put_contents($tmp, "\x00\x00\x00\x18ftypM4A \x00\x00\x02\x00isomiso2".str_repeat("\x00", 64));
        $file = new UploadedFile($tmp, 'note.m4a', 'audio/mp4', null, true);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/audio/'.$audio->id, ['file' => $file])
            ->assertOk();

        Storage::disk('s3')->assertExists('audio/'.$this->user->id.'/'.$audio->id.'.m4a');
    }

    /**
     * A file whose brand says HEIF but whose payload cannot be decoded must terminate
     * the request, not invite a retry. The client's HTTP layer retries 5xx with
     * exponential backoff, so the previous 500 turned one broken photo into a request
     * storm — five attempts in twenty-five seconds, indefinitely.
     */
    public function test_an_undecodable_heif_returns_415_and_records_nothing(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $tmp = tempnam(sys_get_temp_dir(), 'heic_');
        // A well-formed ftyp box announcing `heic`, followed by nothing decodable.
        file_put_contents($tmp, "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 128));
        $file = new UploadedFile($tmp, 'broken.heic', 'image/heic', null, true);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $file])
            ->assertStatus(415)
            ->assertJsonPath('error', 'unsupported_media_type');

        $att->refresh();
        $this->assertNull($att->remote_uri);
        $this->assertEmpty(Storage::disk('s3')->files('attachments/'.$this->user->id));
    }

    /**
     * The rejection log has to identify the file, because nothing else survives it.
     *
     * From a production incident: the same attachment was refused five times in
     * thirty-eight seconds and the log carried only Intervention's flattened
     * sentence, which cannot separate a truncated body from a missing codec. The
     * bytes are never stored on this path, so whatever the log leaves out is gone.
     * Two wrong diagnoses were argued from that log before anyone noticed it did
     * not contain the answer either way.
     */
    public function test_a_rejected_image_is_logged_with_enough_to_identify_it(): void
    {
        Log::spy();

        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $tmp = tempnam(sys_get_temp_dir(), 'heic_');
        file_put_contents($tmp, "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic".str_repeat("\x00", 128));
        $file = new UploadedFile($tmp, 'broken.heic', 'image/heic', null, true);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $file])
            ->assertStatus(415);

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) {
                return $message === 'quest.upload.undecodable_image'
                    && $context['bytes'] === 152
                    // Both the major brand and the compatible brand that routed it
                    // into the decoder, deduplicated.
                    && $context['brands'] === ['heic', 'mif1']
                    && $context['client_mime'] === 'image/heic'
                    && strlen((string) $context['digest']) === 16
                    && $context['cause'] !== '';
            })
            ->once();
    }

    /**
     * The `s3` disk runs with 'throw' => false, so a failed write returns false instead
     * of raising. Unchecked, that stored nothing while the caller recorded a remote_uri
     * — the shape of the bug that left the bucket empty for weeks.
     */
    public function test_a_failed_write_is_not_reported_as_success(): void
    {
        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $file = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');

        $this->expectException(BinaryStorageException::class);

        app(BinaryUploadService::class)->store('attachments', $this->user->id, Str::uuid()->toString(), $file);
    }

    public function test_jpeg_upload_is_not_re_encoded(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $file = UploadedFile::fake()->create('photo.jpg', 50, 'image/jpeg');

        $response = $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $file]);

        $response->assertOk();
        Storage::disk('s3')->assertExists('attachments/'.$this->user->id.'/'.$att->id.'.jpg');
        // The path we ship for a native JPEG is the same .jpg target.
    }

    public function test_missing_file_returns_422_validation(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->postJson('/api/uploads/attachments/'.$att->id, [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation');
    }

    // --- Videos ---

    /**
     * A video upload whose CLIENT mime type is really the one passed.
     *
     * `UploadedFile::fake()->create('clip.mp4', …, 'video/mp4')` does NOT produce
     * that: the reported mime is applied after construction, while
     * `getClientMimeType()` — what the whitelist checks — is fixed at construction
     * from an extension guess, and Symfony guesses `application/mp4` for `.mp4`.
     * The client sends `video/mp4` explicitly (see `binary-uploads.ts`), so
     * building the file by hand is what actually reproduces production.
     */
    private function fakeVideo(string $name, string $mime, int $kilobytes): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vid_');
        file_put_contents($tmp, str_repeat("\0", $kilobytes * 1024));

        return new UploadedFile($tmp, $name, $mime, null, true);
    }

    public function test_upload_valid_video(): void
    {
        $this->subscribeUser();
        $entry = Entry::factory()->for($this->user)->create();
        $video = EntryVideo::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/videos/'.$video->id, [
                'file' => $this->fakeVideo('clip.mp4', 'video/mp4', 2048),
            ])
            ->assertOk()
            ->assertJsonStructure(['remoteUri']);

        $video->refresh();
        $this->assertNotNull($video->remote_uri);
        $this->assertEquals(2048 * 1024, $video->size_bytes);
        Storage::disk('s3')->assertExists('videos/'.$this->user->id.'/'.$video->id.'.mp4');
    }

    public function test_upload_quicktime_video_is_stored_as_mov(): void
    {
        $this->subscribeUser();
        $entry = Entry::factory()->for($this->user)->create();
        $video = EntryVideo::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/videos/'.$video->id, [
                'file' => $this->fakeVideo('clip.mov', 'video/quicktime', 512),
            ])->assertOk();

        Storage::disk('s3')->assertExists('videos/'.$this->user->id.'/'.$video->id.'.mov');
    }

    public function test_re_upload_video_returns_409(): void
    {
        $this->subscribeUser();
        $entry = Entry::factory()->for($this->user)->create();
        $video = EntryVideo::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/videos/'.$video->id, [
                'file' => $this->fakeVideo('clip.mp4', 'video/mp4', 100),
            ])->assertOk();

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/videos/'.$video->id, [
                'file' => $this->fakeVideo('clip.mp4', 'video/mp4', 100),
            ])->assertStatus(409);
    }

    public function test_unsupported_video_mime_returns_415(): void
    {
        $this->subscribeUser();
        $entry = Entry::factory()->for($this->user)->create();
        $video = EntryVideo::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/videos/'.$video->id, [
                'file' => $this->fakeVideo('clip.avi', 'video/x-msvideo', 100),
            ])->assertStatus(415);
    }

    public function test_upload_to_foreign_video_returns_404(): void
    {
        $otherEntry = Entry::factory()->for(User::factory()->create())->create();
        $otherVideo = EntryVideo::factory()->for($otherEntry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/videos/'.$otherVideo->id, [
                'file' => $this->fakeVideo('clip.mp4', 'video/mp4', 100),
            ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');

        $this->assertNull($otherVideo->refresh()->remote_uri);
    }

    /**
     * An .mp4/.mov is ISOBMFF, exactly like a HEIC — it carries its own `ftyp` box.
     * Only image kinds may take the HEIF → JPEG branch, or a video would be handed to
     * Imagick and stored as a corrupt object (or fail outright).
     */
    public function test_a_video_upload_is_not_mistaken_for_a_heif_image(): void
    {
        $this->subscribeUser();
        $entry = Entry::factory()->for($this->user)->create();
        $video = EntryVideo::factory()->for($entry)->create(['remote_uri' => null]);

        // An ftyp box advertising a HEIF still-image brand on a *video* upload: the
        // bytes would satisfy `isHeif()`, so only the kind gate keeps it out of the
        // JPEG encoder.
        $tmp = tempnam(sys_get_temp_dir(), 'mp4_');
        file_put_contents($tmp, "\x00\x00\x00\x18ftypheic\x00\x00\x02\x00mif1isom".str_repeat("\x00", 64));
        $file = new UploadedFile($tmp, 'clip.mp4', 'video/mp4', null, true);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/videos/'.$video->id, ['file' => $file])
            ->assertOk();

        // Stored verbatim as .mp4, not re-encoded to .jpg.
        Storage::disk('s3')->assertExists('videos/'.$this->user->id.'/'.$video->id.'.mp4');
        Storage::disk('s3')->assertMissing('videos/'.$this->user->id.'/'.$video->id.'.jpg');
    }

    /**
     * Video cloud backup is a Nacre Plus feature. Recording stays fully free — the
     * clip simply lives on the device — so the refusal must be clean and must not
     * leave a half-stored object behind.
     */
    public function test_video_backup_is_refused_for_a_free_account(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $video = EntryVideo::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/videos/'.$video->id, [
                'file' => $this->fakeVideo('clip.mp4', 'video/mp4', 100),
            ])
            ->assertStatus(402)
            ->assertJsonPath('error', 'video_backup_requires_plus');

        $this->assertNull($video->refresh()->remote_uri);
        Storage::disk('s3')->assertMissing('videos/'.$this->user->id.'/'.$video->id.'.mp4');
    }

    /**
     * The counterpart of the rule above, and the reason it exists: one minute of
     * high-quality phone video can weigh a third of the whole free budget. Clips a
     * free account already has on the server (backed up while it held Plus, or
     * before backup became Plus-only) are grandfathered — they must not eat the
     * photo/voice-note budget the user still has.
     */
    public function test_video_bytes_do_not_count_toward_the_free_media_quota(): void
    {
        config(['quest.free_media_quota_bytes' => 1024 * 1024]); // 1 MB
        $entry = Entry::factory()->for($this->user)->create();
        $old = EntryVideo::factory()->for($entry)
            ->create(['remote_uri' => 'https://cdn.test/videos/old.mp4']);
        $old->forceFill(['size_bytes' => 900 * 1024])->save(); // not mass-assignable

        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        // 900 KB of video + a 300 KB photo exceeds the 1 MB budget on paper. The
        // photo goes through because only photos, voice notes and character photos
        // are metered.
        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, [
                'file' => UploadedFile::fake()->create('photo.jpg', 300, 'image/jpeg'),
            ])
            ->assertOk();

        $this->assertNotNull($att->refresh()->remote_uri);
    }

    // --- Free-tier media backup quota ---

    public function test_free_account_media_backup_is_capped_by_quota(): void
    {
        config(['quest.free_media_quota_bytes' => 100 * 1024]); // 100 KB
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, [
                'file' => UploadedFile::fake()->create('photo.jpg', 600, 'image/jpeg'), // 600 KB > quota
            ])
            ->assertStatus(402)
            ->assertJsonPath('error', 'media_quota_exceeded');

        $att->refresh();
        $this->assertNull($att->remote_uri);
        Storage::disk('s3')->assertMissing('attachments/'.$this->user->id.'/'.$att->id.'.jpg');
    }

    public function test_upload_persists_size_bytes_and_accumulates_toward_quota(): void
    {
        config(['quest.free_media_quota_bytes' => 1024 * 1024]); // 1 MB — fits one 600 KB photo, not two
        $entry = Entry::factory()->for($this->user)->create();
        $att1 = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);
        $att2 = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att1->id, [
                'file' => UploadedFile::fake()->create('a.jpg', 600, 'image/jpeg'),
            ])->assertOk();

        $att1->refresh();
        $this->assertEquals(600 * 1024, $att1->size_bytes);

        // A second 600 KB upload would total 1.2 MB > 1 MB → blocked.
        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att2->id, [
                'file' => UploadedFile::fake()->create('b.jpg', 600, 'image/jpeg'),
            ])->assertStatus(402);
    }

    public function test_deleting_media_frees_quota(): void
    {
        config(['quest.free_media_quota_bytes' => 1024 * 1024]); // 1 MB
        $entry = Entry::factory()->for($this->user)->create();
        $att1 = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);
        $att2 = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att1->id, [
                'file' => UploadedFile::fake()->create('a.jpg', 600, 'image/jpeg'),
            ])->assertOk();

        // Soft-delete frees the quota immediately (only live rows count).
        $att1->forceFill(['is_deleted' => true])->save();

        $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att2->id, [
                'file' => UploadedFile::fake()->create('b.jpg', 600, 'image/jpeg'),
            ])->assertOk();
    }

    public function test_subscriber_media_backup_is_unlimited(): void
    {
        config(['quest.free_media_quota_bytes' => 1024]); // 1 KB — tiny
        $subscriber = User::factory()->subscribed()->create();
        $token = $subscriber->createToken('mobile')->plainTextToken;
        $entry = Entry::factory()->for($subscriber)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $this->withHeaders($this->bearer($token))
            ->post('/api/uploads/attachments/'.$att->id, [
                'file' => UploadedFile::fake()->create('big.jpg', 600, 'image/jpeg'),
            ])->assertOk();
    }
}
