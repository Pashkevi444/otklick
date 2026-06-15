<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\SiteSettingsRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentChannelRepository;
use App\Repositories\Eloquent\EloquentConversationRepository;
use App\Repositories\Eloquent\EloquentCrmConnectionRepository;
use App\Repositories\Eloquent\EloquentKnowledgeEntryRepository;
use App\Repositories\Eloquent\EloquentMessageRepository;
use App\Repositories\Eloquent\EloquentSiteSettingsRepository;
use App\Repositories\Eloquent\EloquentTenantRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Привязка контрактов репозиториев к Eloquent-реализациям.
 * Сервисы зависят от интерфейсов (DIP), а не от конкретных классов.
 */
final class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        TenantRepositoryInterface::class => EloquentTenantRepository::class,
        ChannelRepositoryInterface::class => EloquentChannelRepository::class,
        ConversationRepositoryInterface::class => EloquentConversationRepository::class,
        MessageRepositoryInterface::class => EloquentMessageRepository::class,
        UserRepositoryInterface::class => EloquentUserRepository::class,
        KnowledgeEntryRepositoryInterface::class => EloquentKnowledgeEntryRepository::class,
        CrmConnectionRepositoryInterface::class => EloquentCrmConnectionRepository::class,
        SiteSettingsRepositoryInterface::class => EloquentSiteSettingsRepository::class,
    ];
}
