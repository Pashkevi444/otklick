<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Services\ConversationHandoffService;
use App\Tenancy\TenantInitializer;
use Illuminate\Console\Command;

/**
 * Возвращает боту диалоги, перехваченные оператором, после простоя дольше
 * {@see Conversation::OPERATOR_IDLE_MINUTES} (авто-возврат). Идёт по всем
 * тенантам в их контексте (RLS). Запускается планировщиком (routes/console.php).
 */
final class ReleaseIdleOperators extends Command
{
    protected $signature = 'conversations:release-idle';

    protected $description = 'Возвращает боту перехваченные оператором диалоги после простоя.';

    public function handle(TenantInitializer $tenancy, ConversationRepositoryInterface $conversations, ConversationHandoffService $handoff): int
    {
        $threshold = now()->subMinutes(Conversation::OPERATOR_IDLE_MINUTES);
        $released = 0;

        Tenant::query()->pluck('id')->each(function (string $tenantId) use ($tenancy, $conversations, $handoff, $threshold, &$released): void {
            $tenancy->run($tenantId, function () use ($conversations, $handoff, $threshold, &$released): void {
                foreach ($conversations->idleOperatorHandled($threshold) as $conversation) {
                    $handoff->release($conversation);
                    $released++;
                }
            });
        });

        $this->info("Возвращено боту диалогов: {$released}.");

        return self::SUCCESS;
    }
}
