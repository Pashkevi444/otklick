<?php

declare(strict_types=1);

namespace App\Modules\Clients;

use App\Modules\Clients\Repositories\Contracts\ClientIdentityRepositoryInterface;
use App\Modules\Clients\Repositories\Contracts\ClientRepositoryInterface;
use App\Modules\Clients\Repositories\Eloquent\EloquentClientIdentityRepository;
use App\Modules\Clients\Repositories\Eloquent\EloquentClientRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Клиенты» (база клиентов тенанта): карточка клиента, LLM-резюме,
 * узнавание вернувшегося по нативной идентичности канала (client_identities).
 * «Лиды» (диалоги) живут в модуле Conversations и ссылаются на клиента — связь
 * лид↔клиент это межмодульный шов.
 */
final class ClientsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ClientRepositoryInterface::class => EloquentClientRepository::class,
        ClientIdentityRepositoryInterface::class => EloquentClientIdentityRepository::class,
    ];
}
