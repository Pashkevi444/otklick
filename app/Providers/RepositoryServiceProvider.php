<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\AnnouncementRepositoryInterface;
use App\Repositories\Contracts\BroadcastRepositoryInterface;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\ClientIdentityRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use App\Repositories\Contracts\CustomFieldDefRepositoryInterface;
use App\Repositories\Contracts\DashboardCardStateRepositoryInterface;
use App\Repositories\Contracts\DealRepositoryInterface;
use App\Repositories\Contracts\DealStageRepositoryInterface;
use App\Repositories\Contracts\EmailChangeCodeRepositoryInterface;
use App\Repositories\Contracts\FlowAbRepositoryInterface;
use App\Repositories\Contracts\FlowRepositoryInterface;
use App\Repositories\Contracts\GridViewRepositoryInterface;
use App\Repositories\Contracts\KnowledgeChunkRepositoryInterface;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use App\Repositories\Contracts\PasswordResetCodeRepositoryInterface;
use App\Repositories\Contracts\SandboxRepositoryInterface;
use App\Repositories\Contracts\SiteSettingsRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentAnnouncementRepository;
use App\Repositories\Eloquent\EloquentBroadcastRepository;
use App\Repositories\Eloquent\EloquentChannelRepository;
use App\Repositories\Eloquent\EloquentClientIdentityRepository;
use App\Repositories\Eloquent\EloquentClientRepository;
use App\Repositories\Eloquent\EloquentConversationRepository;
use App\Repositories\Eloquent\EloquentCrmConnectionRepository;
use App\Repositories\Eloquent\EloquentCrmKnowledgeRepository;
use App\Repositories\Eloquent\EloquentCustomFieldDefRepository;
use App\Repositories\Eloquent\EloquentDashboardCardStateRepository;
use App\Repositories\Eloquent\EloquentDealRepository;
use App\Repositories\Eloquent\EloquentDealStageRepository;
use App\Repositories\Eloquent\EloquentEmailChangeCodeRepository;
use App\Repositories\Eloquent\EloquentFlowAbRepository;
use App\Repositories\Eloquent\EloquentFlowRepository;
use App\Repositories\Eloquent\EloquentGridViewRepository;
use App\Repositories\Eloquent\EloquentKnowledgeChunkRepository;
use App\Repositories\Eloquent\EloquentKnowledgeEntryRepository;
use App\Repositories\Eloquent\EloquentKnowledgeGapRepository;
use App\Repositories\Eloquent\EloquentLeadAnalyticsRepository;
use App\Repositories\Eloquent\EloquentLeadRepository;
use App\Repositories\Eloquent\EloquentMessageRepository;
use App\Repositories\Eloquent\EloquentNotificationRecipientRepository;
use App\Repositories\Eloquent\EloquentPasswordResetCodeRepository;
use App\Repositories\Eloquent\EloquentSandboxRepository;
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
        KnowledgeGapRepositoryInterface::class => EloquentKnowledgeGapRepository::class,
        ClientRepositoryInterface::class => EloquentClientRepository::class,
        ClientIdentityRepositoryInterface::class => EloquentClientIdentityRepository::class,
        DealStageRepositoryInterface::class => EloquentDealStageRepository::class,
        DealRepositoryInterface::class => EloquentDealRepository::class,
        LeadRepositoryInterface::class => EloquentLeadRepository::class,
        CustomFieldDefRepositoryInterface::class => EloquentCustomFieldDefRepository::class,
        GridViewRepositoryInterface::class => EloquentGridViewRepository::class,
        BroadcastRepositoryInterface::class => EloquentBroadcastRepository::class,
        FlowRepositoryInterface::class => EloquentFlowRepository::class,
        FlowAbRepositoryInterface::class => EloquentFlowAbRepository::class,
        LeadAnalyticsRepositoryInterface::class => EloquentLeadAnalyticsRepository::class,
        NotificationRecipientRepositoryInterface::class => EloquentNotificationRecipientRepository::class,
        CrmConnectionRepositoryInterface::class => EloquentCrmConnectionRepository::class,
        CrmKnowledgeRepositoryInterface::class => EloquentCrmKnowledgeRepository::class,
        KnowledgeChunkRepositoryInterface::class => EloquentKnowledgeChunkRepository::class,
        EmailChangeCodeRepositoryInterface::class => EloquentEmailChangeCodeRepository::class,
        SiteSettingsRepositoryInterface::class => EloquentSiteSettingsRepository::class,
        SandboxRepositoryInterface::class => EloquentSandboxRepository::class,
        AnnouncementRepositoryInterface::class => EloquentAnnouncementRepository::class,
        DashboardCardStateRepositoryInterface::class => EloquentDashboardCardStateRepository::class,
        PasswordResetCodeRepositoryInterface::class => EloquentPasswordResetCodeRepository::class,
    ];
}
