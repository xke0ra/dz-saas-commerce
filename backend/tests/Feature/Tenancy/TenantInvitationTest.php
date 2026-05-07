<?php

use App\Actions\Tenancy\AcceptTenantInvitation;
use App\Actions\Tenancy\InviteTenantUser;
use App\Enums\TenantInvitationStatus;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

it('creates a hashed staff invitation and sends a notification', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $inviter = User::factory()->create();

    $result = app(InviteTenantUser::class)->handle(
        tenant: $tenant,
        email: 'Staff@Example.test',
        role: TenantRole::StoreStaff,
        permissions: [TenantPermission::ProductsCreate->value],
        invitedBy: $inviter,
    );

    expect($result->plainToken)->toHaveLength(64)
        ->and($result->invitation->email)->toBe('staff@example.test')
        ->and($result->invitation->token_hash)->toBe(hash('sha256', $result->plainToken))
        ->and($result->invitation->token_hash)->not->toBe($result->plainToken)
        ->and($result->invitation->status)->toBe(TenantInvitationStatus::Pending);

    Notification::assertSentOnDemand(TenantInvitationNotification::class);
});

it('revokes previous pending invitations for the same tenant email', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create();

    $first = app(InviteTenantUser::class)->handle(
        tenant: $tenant,
        email: 'staff@example.test',
        role: TenantRole::StoreStaff,
        permissions: null,
    )->invitation;

    $second = app(InviteTenantUser::class)->handle(
        tenant: $tenant,
        email: 'staff@example.test',
        role: TenantRole::StoreAdmin,
        permissions: null,
    )->invitation;

    expect($first->fresh()->status)->toBe(TenantInvitationStatus::Revoked)
        ->and($first->fresh()->revoked_at)->not->toBeNull()
        ->and($second->status)->toBe(TenantInvitationStatus::Pending);
});

it('accepts a valid invitation and attaches the invited user to the tenant', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['email' => 'staff@example.test']);

    $result = app(InviteTenantUser::class)->handle(
        tenant: $tenant,
        email: $user->email,
        role: TenantRole::StoreStaff,
        permissions: [TenantPermission::ProductsCreate->value],
    );

    $accepted = app(AcceptTenantInvitation::class)->handle($result->plainToken, $user);

    expect($accepted->status)->toBe(TenantInvitationStatus::Accepted)
        ->and($accepted->accepted_user_id)->toBe($user->id)
        ->and($accepted->accepted_at)->not->toBeNull()
        ->and($user->fresh()->belongsToTenant($tenant))->toBeTrue()
        ->and($user->fresh()->hasTenantPermission($tenant, TenantPermission::ProductsCreate))->toBeTrue();
});

it('rejects accepting an invitation with the wrong user email', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['email' => 'wrong@example.test']);

    $result = app(InviteTenantUser::class)->handle(
        tenant: $tenant,
        email: 'staff@example.test',
        role: TenantRole::StoreStaff,
        permissions: null,
    );

    app(AcceptTenantInvitation::class)->handle($result->plainToken, $user);
})->throws(ValidationException::class, 'This invitation was sent to another email address.');

it('marks expired invitations as expired when acceptance is attempted', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['email' => 'staff@example.test']);

    $result = app(InviteTenantUser::class)->handle(
        tenant: $tenant,
        email: $user->email,
        role: TenantRole::StoreStaff,
        permissions: null,
        expiresInDays: -1,
    );

    try {
        app(AcceptTenantInvitation::class)->handle($result->plainToken, $user);
    } catch (ValidationException $exception) {
        expect($result->invitation->fresh()->status)->toBe(TenantInvitationStatus::Expired);

        throw $exception;
    }
})->throws(ValidationException::class, 'The invitation has expired.');

it('protects staff invitations with the staff manage permission', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::query()
        ->withoutGlobalScope('current_tenant')
        ->create([
            'tenant_id' => $tenant->id,
            'email' => 'staff@example.test',
            'role' => TenantRole::StoreStaff,
            'permissions' => null,
            'token_hash' => hash('sha256', 'token'),
            'status' => TenantInvitationStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

    $tenant->users()->attach($owner, [
        'role' => TenantRole::Owner->value,
        'permissions' => null,
    ]);
    $tenant->users()->attach($admin, [
        'role' => TenantRole::StoreAdmin->value,
        'permissions' => null,
    ]);

    expect($owner->can('view', $invitation))->toBeTrue()
        ->and($admin->can('view', $invitation))->toBeFalse();
});
