<?php

use App\Models\User;
use App\Verso\System\Health\PermissionFixer;
use App\Verso\System\Health\PermissionInspector;
use App\Verso\System\Health\ProcessIdentity;
use App\Verso\System\Models\ConfigChange;
use App\Verso\System\Server\PhpIni;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePhpIni;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Tomás Herrero']);
    $this->root = sys_get_temp_dir().'/verso-site-'.Str::random(8);
    File::ensureDirectoryExists($this->root.'/storage/logs');
    File::ensureDirectoryExists($this->root.'/public');
    file_put_contents($this->root.'/.env', 'APP_NAME="Harbour Light"');
    chmod($this->root.'/.env', 0644);

    $inspector = new PermissionInspector($this->root, ['storage', 'storage/logs', 'storage/framework/views'], [], ['storage'], 100, ProcessIdentity::current());
    $this->app->instance(PermissionInspector::class, $inspector);
    $this->app->instance(PermissionFixer::class, new PermissionFixer($inspector, fn (): bool => symlink($this->root.'/storage', $this->root.'/public/storage-test')));
    $this->app->instance(PhpIni::class, FakePhpIni::sharedHosting());
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('is only for users with manage_system', function () {
    $this->actingAs(User::factory()->editor()->create())->get(route('admin.system.health.index'))->assertForbidden();
    $this->actingAs(User::factory()->editor()->create())->post(route('admin.system.health.fix'), ['confirm' => true])->assertForbidden();
});

it('shows PHP, ini values, checks and the dry run of the fixes', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.system.health.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/system/health')
            ->where('php.sapi', 'fpm-fcgi')
            ->where('ini.0.name', 'upload_max_filesize')
            ->where('ini.0.scope', 'PHP_INI_PERDIR')
            ->where('ini.2.name', 'memory_limit')
            ->where('ini.2.runtime', true)
            ->where('plan', fn ($plan) => collect($plan)->pluck('description')->contains('Change the mode of .env to 0640'))
            ->where('checks', fn ($checks) => collect($checks)->firstWhere('key', 'env')['status'] === 'danger')
        );

    expect(fileperms($this->root.'/.env') & 0777)->toBe(0644);
});

it('fixes what PHP can fix after consent and logs it', function () {
    $this->actingAs($this->admin)->post(route('admin.system.health.fix'))->assertSessionHasErrors('confirm');

    $this->actingAs($this->admin)
        ->post(route('admin.system.health.fix'), ['confirm' => true])
        ->assertInertiaFlash('toast.type', 'success');

    expect(fileperms($this->root.'/.env') & 0777)->toBe(0640)
        ->and(is_dir($this->root.'/storage/framework/views'))->toBeTrue()
        ->and(ConfigChange::query()->where('action', 'permissions_fix')->where('user_id', $this->admin->id)->exists())->toBeTrue();
});
