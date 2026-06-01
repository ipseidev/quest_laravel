<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
     * Build an UploadedFile whose underlying bytes are a real (10x10) PNG so
     * Imagick can read it, but whose MIME advertised to the controller is
     * image/heic. Lets us exercise the HEIC → JPEG path without bundling a
     * real .heic fixture.
     */
    private function fakeHeicUpload(string $name = 'photo.heic'): UploadedFile
    {
        $pngBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKAQMAAAC3/F3+AAAA'
            .'IGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAAAG'
            .'UExURf8AAP///0EdNBEAAAABYktHRAH/Ai3eAAAAC0lEQVQI12NgwAcAAB4A'
            .'AW6FRzIAAAAASUVORK5CYII='
        );
        $tmp = tempnam(sys_get_temp_dir(), 'heic_');
        file_put_contents($tmp, $pngBytes);

        return new UploadedFile($tmp, $name, 'image/heic', null, true);
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
            ->post('/api/uploads/attachments/'.\Illuminate\Support\Str::uuid(), ['file' => $file])
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
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $response = $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $this->fakeHeicUpload()]);

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
        $character = Character::factory()->for($this->user)->create(['remote_photo_uri' => null]);

        $response = $this->withHeaders($this->bearer())
            ->post('/api/uploads/character-photos/'.$character->id, ['file' => $this->fakeHeicUpload('avatar.heic')]);

        $response->assertOk();
        $this->assertStringEndsWith('.jpg', $response->json('remoteUri'));

        $expectedPath = 'character-photos/'.$this->user->id.'/'.$character->id.'.jpg';
        Storage::disk('s3')->assertExists($expectedPath);
    }

    public function test_heif_attachment_is_re_encoded_to_jpeg(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $att = EntryAttachment::factory()->for($entry)->create(['remote_uri' => null]);

        $heif = $this->fakeHeicUpload('photo.heif');
        $heif = new UploadedFile($heif->getPathname(), 'photo.heif', 'image/heif', null, true);

        $response = $this->withHeaders($this->bearer())
            ->post('/api/uploads/attachments/'.$att->id, ['file' => $heif]);

        $response->assertOk();
        $this->assertStringEndsWith('.jpg', $response->json('remoteUri'));
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
}
