<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Identity\DTO\PasswordResetCodeData;
use App\Modules\Identity\Mail\PasswordResetCodeMail;
use App\Modules\Identity\Repositories\Contracts\PasswordResetCodeRepositoryInterface;
use App\Modules\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Identity\Services\PasswordResetService;
use App\Shared\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class PasswordResetServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_send_code_stores_hash_and_emails_when_user_exists(): void
    {
        Mail::fake();

        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findByEmail')->with('owner@biz.ru')->andReturn(new User);

        $codes = Mockery::mock(PasswordResetCodeRepositoryInterface::class);
        $codes->shouldReceive('put')->once()->with('owner@biz.ru', Mockery::type('string'));

        (new PasswordResetService($users, $codes))->sendCode('owner@biz.ru');

        Mail::assertQueued(PasswordResetCodeMail::class);
    }

    public function test_send_code_is_silent_when_user_missing(): void
    {
        Mail::fake();

        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findByEmail')->with('nobody@biz.ru')->andReturnNull();

        $codes = Mockery::mock(PasswordResetCodeRepositoryInterface::class);
        $codes->shouldNotReceive('put');

        (new PasswordResetService($users, $codes))->sendCode('nobody@biz.ru');

        Mail::assertNothingQueued();
    }

    public function test_reset_succeeds_with_valid_fresh_code(): void
    {
        $record = new PasswordResetCodeData(Hash::make('123456'), Carbon::now());

        $owner = Mockery::mock(User::class);
        $owner->shouldReceive('update')->once()->with(['password' => 'new-strong-pass']);

        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('findByEmail')->with('owner@biz.ru')->andReturn($owner);

        $codes = Mockery::mock(PasswordResetCodeRepositoryInterface::class);
        $codes->shouldReceive('get')->with('owner@biz.ru')->andReturn($record);
        $codes->shouldReceive('delete')->once()->with('owner@biz.ru');

        $ok = (new PasswordResetService($users, $codes))->reset('owner@biz.ru', '123456', 'new-strong-pass');

        $this->assertTrue($ok);
    }

    public function test_reset_fails_with_wrong_code(): void
    {
        $record = new PasswordResetCodeData(Hash::make('111111'), Carbon::now());

        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldNotReceive('findByEmail');

        $codes = Mockery::mock(PasswordResetCodeRepositoryInterface::class);
        $codes->shouldReceive('get')->with('owner@biz.ru')->andReturn($record);
        $codes->shouldNotReceive('delete');

        $ok = (new PasswordResetService($users, $codes))->reset('owner@biz.ru', '999999', 'whatever-pass');

        $this->assertFalse($ok);
    }

    public function test_reset_fails_when_code_expired(): void
    {
        $record = new PasswordResetCodeData(Hash::make('123456'), Carbon::now()->subMinutes(7));

        $users = Mockery::mock(UserRepositoryInterface::class);
        $codes = Mockery::mock(PasswordResetCodeRepositoryInterface::class);
        $codes->shouldReceive('get')->with('owner@biz.ru')->andReturn($record);
        $codes->shouldNotReceive('delete');

        $ok = (new PasswordResetService($users, $codes))->reset('owner@biz.ru', '123456', 'whatever-pass');

        $this->assertFalse($ok);
    }

    public function test_reset_fails_when_no_code_requested(): void
    {
        $users = Mockery::mock(UserRepositoryInterface::class);
        $codes = Mockery::mock(PasswordResetCodeRepositoryInterface::class);
        $codes->shouldReceive('get')->with('owner@biz.ru')->andReturnNull();

        $ok = (new PasswordResetService($users, $codes))->reset('owner@biz.ru', '123456', 'whatever-pass');

        $this->assertFalse($ok);
    }
}
