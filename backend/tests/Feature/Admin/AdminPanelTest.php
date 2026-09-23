<?php

use App\Enums\ComplaintStatus;
use App\Enums\TermsStatus;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Investigations\Pages\ListInvestigations;
use App\Filament\Resources\Investigators\Pages\ListInvestigators;
use App\Filament\Resources\TermsVersions\Pages\ManageTermsVersions;
use App\Filament\Resources\Tips\Pages\ListTips;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ModerationAction;
use App\Models\Role;
use App\Models\TermsVersion;
use App\Models\Tip;
use App\Models\User;
use App\Services\ComplaintService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->withHeaders(['Accept' => 'text/html']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('denies the panel to guests, members and investigators', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->actingAs($this->member())->get('/admin')->assertForbidden();
    $this->actingAs($this->investigator()->user)->get('/admin')->assertForbidden();
    $unverifiedStaff = User::factory()->unverified()->withRole('admin')->create();
    $this->actingAs($unverifiedStaff)->get('/admin')->assertForbidden();
});

it('renders the dashboard and every resource index for a super admin', function () {
    $this->publishedInvestigation();
    $super = $this->staff('super_admin');
    $this->actingAs($super)->get('/admin')->assertOk();
    foreach (['investigations', 'evidence', 'cms-pages', 'terms-versions', 'categories', 'comments', 'supports', 'followers',
        'complaints', 'moderation-actions', 'tips', 'users', 'investigators', 'roles', 'settings', 'audit-logs', 'login-logs', 'security-events'] as $slug) {
        $this->actingAs($super)->get("/admin/{$slug}")->assertOk();
    }
});

it('limits moderators to moderation resources', function () {
    $mod = $this->staff('moderator');
    $this->actingAs($mod)->get('/admin/complaints')->assertOk();
    $this->actingAs($mod)->get('/admin/comments')->assertOk();
    $this->actingAs($mod)->get('/admin/roles')->assertForbidden();
    $this->actingAs($mod)->get('/admin/settings')->assertForbidden();
    $this->actingAs($mod)->get('/admin/tips')->assertForbidden();
    $this->actingAs($mod)->get('/admin/cms-pages')->assertForbidden();
});

it('keeps tips oversight to super admins and audits opening content', function () {
    $pub = $this->publishedInvestigation();
    $this->postJson('/api/v1/tips', ['investigation' => $pub->public_id, 'subject' => 'Lead', 'message' => 'Some sensitive message text', 'agree_to_content_policy' => true]);
    $tip = Tip::first();
    $this->actingAs($this->staff('admin'))->get('/admin/tips')->assertForbidden();

    $super = $this->staff('super_admin');
    $this->actingAs($super);
    Livewire::test(ListTips::class)->assertCanSeeTableRecords([$tip])->assertDontSee('Some sensitive message text')
        ->callTableAction('open', $tip, ['reason' => 'Legal request ref 42 received'])->assertHasNoTableActionErrors();
    expect(AuditLog::query()->where('action', 'tip.content_viewed_by_staff')->where('actor_user_id', $super->id)->exists())->toBeTrue();
});

it('restricts an investigation through the panel via the audited moderation service', function () {
    $pub = $this->publishedInvestigation();
    $this->actingAs($this->staff('moderator'));
    Livewire::test(ListInvestigations::class)
        ->callTableAction('investigation_restrict', $pub, ['reason' => 'Privacy complaint under review', 'public_note' => 'Under review'])
        ->assertHasNoTableActionErrors();
    expect($pub->fresh()->status->value)->toBe('restricted');
    expect(ModerationAction::query()->where('action', 'restrict')->exists())->toBeTrue();
});

it('updates complaint status from the panel', function () {
    $pub = $this->publishedInvestigation();
    $this->actingAs($this->member())->postJson('/api/v1/complaints', ['complaint_type' => 'spam', 'description' => 'This is repetitive spam content.', 'investigation' => $pub->public_id, 'good_faith' => true]);
    $complaint = Complaint::first();
    $this->actingAs($this->staff('moderator'));
    Livewire::test(ListComplaints::class)
        ->callTableAction('updateStatus', $complaint, ['status' => 'dismissed', 'resolution' => 'No policy breach found.'])
        ->assertHasNoTableActionErrors();
    expect($complaint->fresh()->status->value)->toBe('dismissed');
});

it('publishes agreement versions, making them immutable and requiring re-acceptance', function () {
    $admin = $this->staff('admin');
    $draft = new TermsVersion(['agreement_type' => 'privacy_policy', 'version' => '1.1', 'title' => 'Privacy v1.1', 'content' => 'Updated']);
    $draft->save();
    $this->actingAs($admin);
    Livewire::test(ManageTermsVersions::class)->callTableAction('publish', $draft)->assertHasNoTableActionErrors();
    expect($draft->fresh()->status)->toBe(TermsStatus::Published);
    expect($admin->can('update', $draft->fresh()))->toBeFalse();
    expect(collect($this->getJson('/api/v1/auth/me')->json('data.pending_agreements'))->pluck('version'))->toContain('1.1');
    expect(AuditLog::query()->where('action', 'admin.termsversion.updated')->exists())->toBeTrue();
});

it('lets only super admins reveal private identity from the panel, audited', function () {
    $inv = $this->investigator();
    $this->actingAs($this->staff('admin'));
    Livewire::test(ListInvestigators::class)->assertTableActionHidden('revealIdentity', $inv);

    $super = $this->staff('super_admin');
    $this->actingAs($super);
    Livewire::test(ListInvestigators::class)->callTableAction('revealIdentity', $inv, ['reason' => 'Court order 7/2026 served'])->assertHasNoTableActionErrors();
    expect(AuditLog::query()->where('action', 'identity.private_viewed')->where('actor_user_id', $super->id)->exists())->toBeTrue();
});

it('prevents admins from granting super_admin and audits role changes', function () {
    $admin = $this->staff('admin');
    $target = $this->member();
    $this->actingAs($admin);
    // Admins lack roles.manage entirely.
    Livewire::test(ListUsers::class)->assertTableActionHidden('roles', $target);

    $super = $this->staff('super_admin');
    $this->actingAs($super);
    $modRole = Role::query()->where('slug', 'moderator')->value('id');
    $userRole = Role::query()->where('slug', 'user')->value('id');
    Livewire::test(ListUsers::class)->callTableAction('roles', $target, ['roles' => [$userRole, $modRole]])->assertHasNoTableActionErrors();
    expect($target->fresh()->roleSlugs())->toContain('moderator');
    expect(AuditLog::query()->where('action', 'user.roles_changed')->exists())->toBeTrue();
});

it('updates complaint status even when the table eager-loaded partial relations', function () {
    $pub = $this->publishedInvestigation();
    $this->actingAs($this->member())->postJson('/api/v1/complaints', ['complaint_type' => 'privacy', 'description' => 'Address visible in the evidence captions.', 'investigation' => $pub->public_id, 'good_faith' => true]);
    $complaint = Complaint::query()->with('investigation:id,public_id')->first();
    app(ComplaintService::class)->updateStatus($complaint, ComplaintStatus::ActionRequested, $this->staff('moderator'), null, 'note');
    expect($pub->investigator->user->notifications()->where('data->kind', 'complaint_action_requested')->exists())->toBeTrue();
});

it('forces staff to set up TOTP two-factor authentication when required', function () {
    // The panel registers its MFA middleware at boot, so boot a fresh app with MFA required.
    foreach ([&$_ENV, &$_SERVER] as &$bag) {
        $bag['ADMIN_MFA_REQUIRED'] = 'true';
    }
    putenv('ADMIN_MFA_REQUIRED=true');
    $this->refreshApplication();
    DB::beginTransaction();
    try {
        $admin = User::factory()->withRole('admin')->create();
        $res = $this->actingAs($admin)->get('/admin');
        $res->assertRedirect();
        expect($res->headers->get('Location'))->toContain('multi-factor-authentication');
    } finally {
        DB::rollBack();
        foreach ([&$_ENV, &$_SERVER] as &$bag) {
            $bag['ADMIN_MFA_REQUIRED'] = 'false';
        }
        putenv('ADMIN_MFA_REQUIRED=false');
    }
});
