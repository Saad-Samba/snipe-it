<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Tests\TestCase;

class ImpersonateUserTest extends TestCase
{
    private function allow(User ...$users): void
    {
        config(['app.user_impersonation_usernames' => array_map(fn (User $user) => $user->username, $users)]);
    }

    public function test_impersonation_is_not_found_when_disabled(): void
    {
        config(['app.user_impersonation_usernames' => []]);
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('users.impersonate.start', $target), ['note' => 'Investigating access'])
            ->assertNotFound();
    }

    public function test_only_an_allowlisted_superuser_can_impersonate(): void
    {
        $actor = User::factory()->superuser()->create();
        $other = User::factory()->superuser()->create();
        $target = User::factory()->create();
        $this->allow($other);

        $this->actingAs($actor)
            ->post(route('users.impersonate.start', $target), ['note' => 'Investigating access'])
            ->assertForbidden();
    }

    public function test_impersonation_requires_a_reason(): void
    {
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->create();
        $this->allow($actor);

        $this->actingAs($actor)
            ->post(route('users.impersonate.start', $target))
            ->assertRedirect(route('users.show', $target))
            ->assertSessionHas('error', trans('admin/users/general.impersonate_note_required'));

        $this->assertSame($actor->id, auth()->id());
    }

    public function test_allowlisted_superuser_can_impersonate_and_audit_the_user(): void
    {
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->create(['activated' => 1]);
        $this->allow($actor);

        $this->actingAs($actor)
            ->post(route('users.impersonate.start', $target), ['note' => 'Investigating ticket #4242'])
            ->assertRedirect(route('home'));

        $this->assertSame($target->id, auth()->id());
        $this->assertSame($actor->id, session('impersonator_id'));
        $this->assertDatabaseHas('action_logs', [
            'item_type' => User::class,
            'item_id' => $target->id,
            'created_by' => $actor->id,
            'action_type' => 'impersonated',
            'note' => 'Investigating ticket #4242',
        ]);
    }

    public function test_cannot_impersonate_another_superuser(): void
    {
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->superuser()->create();
        $this->allow($actor);

        $this->actingAs($actor)
            ->post(route('users.impersonate.start', $target), ['note' => 'Investigating access'])
            ->assertRedirect(route('users.show', $target));

        $this->assertSame($actor->id, auth()->id());
    }

    public function test_cannot_impersonate_a_deactivated_user(): void
    {
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->create(['activated' => 0]);
        $this->allow($actor);

        $this->actingAs($actor)
            ->post(route('users.impersonate.start', $target), ['note' => 'Investigating access'])
            ->assertRedirect(route('users.show', $target));

        $this->assertSame($actor->id, auth()->id());
    }

    public function test_stopping_impersonation_restores_the_original_user_and_audits_it(): void
    {
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->create();
        $this->allow($actor);

        $this->actingAs($actor)
            ->post(route('users.impersonate.start', $target), ['note' => 'Investigating access'])
            ->assertRedirect(route('home'));

        $this->post(route('users.impersonate.stop'))
            ->assertRedirect(route('users.show', $target));

        $this->assertSame($actor->id, auth()->id());
        $this->assertNull(session('impersonator_id'));
        $this->assertDatabaseHas('action_logs', [
            'item_type' => User::class,
            'item_id' => $target->id,
            'created_by' => $actor->id,
            'action_type' => 'stopped impersonating',
        ]);
    }

    public function test_user_detail_displays_the_confirmation_modal_only_for_eligible_impersonations(): void
    {
        $actor = User::factory()->superuser()->create();
        $target = User::factory()->create();
        $this->allow($actor);

        $this->actingAs($actor)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertSee('confirmImpersonateModal')
            ->assertSee('name="note"', false);
    }
}
