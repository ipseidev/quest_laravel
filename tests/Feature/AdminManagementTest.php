<?php

namespace Tests\Feature;

use App\Filament\Resources\Admins\AdminResource;
use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Managing operators from inside the panel.
 *
 * The screen grants panel access, so the tests that matter are the ones about
 * what it refuses: weak passwords, blanking a password by accident, and the two
 * deletions that would lock everybody out.
 */
class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = Admin::factory()->create();
        $this->actingAs($this->operator, 'admin');
    }

    public function test_an_operator_can_add_another_operator(): void
    {
        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'Second Operator',
                'email' => 'second@example.test',
                'password' => 'a-long-enough-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = Admin::query()->where('email', 'second@example.test')->sole();

        $this->assertSame('Second Operator', $created->name);
        $this->assertTrue(
            Hash::check('a-long-enough-password', $created->password),
            'The password was not hashed, or was stored verbatim.',
        );
    }

    public function test_a_short_password_is_rejected(): void
    {
        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'Weak',
                'email' => 'weak@example.test',
                'password' => 'short',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);

        $this->assertSame(1, Admin::query()->count());
    }

    public function test_an_email_already_in_use_is_rejected(): void
    {
        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'Duplicate',
                'email' => $this->operator->email,
                'password' => 'a-long-enough-password',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    /**
     * The password field is always blank on load. If it were dehydrated
     * unconditionally, saving a name change would overwrite the stored hash with an
     * empty string and lock that operator out.
     */
    public function test_editing_without_touching_the_password_leaves_it_intact(): void
    {
        $other = Admin::factory()->create(['password' => Hash::make('original-password')]);

        Livewire::test(EditAdmin::class, ['record' => $other->getKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $other->refresh();

        $this->assertSame('Renamed', $other->name);
        $this->assertTrue(Hash::check('original-password', $other->password));
    }

    public function test_editing_with_a_password_replaces_it(): void
    {
        $other = Admin::factory()->create(['password' => Hash::make('original-password')]);

        Livewire::test(EditAdmin::class, ['record' => $other->getKey()])
            ->fillForm(['password' => 'a-brand-new-password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $other->refresh();

        $this->assertTrue(Hash::check('a-brand-new-password', $other->password));
    }

    /**
     * Signing out of your own account permanently, with no undo and no warning that
     * would help afterwards.
     */
    public function test_an_operator_cannot_delete_themselves(): void
    {
        $this->assertFalse(AdminResource::canDelete($this->operator));
    }

    /**
     * The worse one: it cannot be repaired from the panel at all. Recovering means
     * shell access to the server to run `nacre:admin`.
     */
    public function test_the_last_operator_cannot_be_deleted(): void
    {
        $other = Admin::factory()->create();
        $this->actingAs($other, 'admin');

        // Two exist, so deleting one that is not you is allowed.
        $this->assertTrue(AdminResource::canDelete($this->operator));

        $this->operator->delete();

        $this->assertFalse(AdminResource::canDelete($other));
    }

    /**
     * `canDelete()` is only worth anything if the UI consults it. Pinned because
     * Filament resolves resource authorization through a fallback when no policy is
     * registered, and a future policy would silently take over.
     */
    public function test_the_delete_action_is_hidden_for_your_own_account(): void
    {
        Livewire::test(EditAdmin::class, ['record' => $this->operator->getKey()])
            ->assertActionHidden('delete');
    }

    public function test_the_operator_screens_render(): void
    {
        $guard = auth()->guard('admin');

        $this->withSession([$guard->getName() => $this->operator->getAuthIdentifier()])
            ->get('/admin/admins')
            ->assertOk();

        $this->withSession([$guard->getName() => $this->operator->getAuthIdentifier()])
            ->get('/admin/admins/create')
            ->assertOk();
    }
}
