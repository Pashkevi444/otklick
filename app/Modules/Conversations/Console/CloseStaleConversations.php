<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Console;

use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Console\Command;

/**
 * Закрывает «протухшие» диалоги как потерянные лиды (два прохода):
 *  • открытые без активности дольше N минут и без записи — клиент перестал отвечать;
 *  • зависшие в статусе «нужен человек» дольше N часов — оператор не разобрал.
 * Оба прохода переводят диалог в Closed ⇒ ConversationOutcome::Lost. Идёт по всем
 * тенантам в их контексте (RLS). Запускается планировщиком (см. routes/console.php).
 */
final class CloseStaleConversations extends Command
{
    protected $signature = 'conversations:close-stale
        {--minutes=30 : Порог неактивности открытых диалогов, минут}
        {--needs-human-hours=24 : Порог зависания в статусе «нужен человек», часов}';

    protected $description = 'Закрывает протухшие диалоги (потерянные лиды): открытые без активности и зависшие «нужен человек».';

    public function handle(TenantInitializer $tenancy, ConversationRepositoryInterface $conversations): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $needsHumanHours = max(1, (int) $this->option('needs-human-hours'));
        $openThreshold = now()->subMinutes($minutes);
        $needsHumanThreshold = now()->subHours($needsHumanHours);
        $closedOpen = 0;
        $closedNeedsHuman = 0;

        Tenant::query()->pluck('id')->each(function (string $tenantId) use ($tenancy, $conversations, $openThreshold, $needsHumanThreshold, &$closedOpen, &$closedNeedsHuman): void {
            $tenancy->run($tenantId, function () use ($conversations, $openThreshold, $needsHumanThreshold, &$closedOpen, &$closedNeedsHuman): void {
                $closedOpen += $conversations->closeStaleOpen($openThreshold);
                $closedNeedsHuman += $conversations->closeStaleNeedsHuman($needsHumanThreshold);
            });
        });

        $this->info("Закрыто потерянных диалогов: открытых {$closedOpen}, «нужен человек» {$closedNeedsHuman}.");

        return self::SUCCESS;
    }
}
