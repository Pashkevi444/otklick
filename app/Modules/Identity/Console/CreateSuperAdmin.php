<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\DTO\NewUserData;
use App\Modules\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Shared\Enums\UserRole;
use Illuminate\Console\Command;

/**
 * Бутстрап первого супер-админа (оператора SaaS). Дальше супер-админ заводит
 * тенантов и их владельцев через веб-панель.
 */
final class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super-admin {name} {email} {password}';

    protected $description = 'Создаёт супер-админа (кросс-тенантный пользователь)';

    public function handle(UserRepositoryInterface $users): int
    {
        $email = (string) $this->argument('email');

        if ($users->findByEmail($email) !== null) {
            $this->error("Пользователь с email {$email} уже существует.");

            return self::FAILURE;
        }

        $users->create(new NewUserData(
            name: (string) $this->argument('name'),
            email: $email,
            password: (string) $this->argument('password'),
            role: UserRole::SuperAdmin,
            tenantId: null,
        ));

        $this->info("Супер-админ создан: {$email}");

        return self::SUCCESS;
    }
}
