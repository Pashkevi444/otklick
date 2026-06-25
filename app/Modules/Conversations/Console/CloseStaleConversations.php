<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Console;

use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Console\Command;

/**
 * Закрывает открытые диалоги без активности дольше N минут и без записи —
 * клиент перестал отвечать, лид считаем потерянным. Идёт по всем тенантам в их
 * контексте (RLS). Запускается планировщиком (см. routes/console.php).
 */
final class CloseStaleConversations extends Command
{
    protected $signature = 'conversations:close-stale {--minutes=30 : Порог неактивности в минутах}';

    protected $description = 'Закрывает открытые диалоги без активности (потерянные лиды).';

    public function handle(TenantInitializer $tenancy, ConversationRepositoryInterface $conversations): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $threshold = now()->subMinutes($minutes);
        $closed = 0;

        Tenant::query()->pluck('id')->each(function (string $tenantId) use ($tenancy, $conversations, $threshold, &$closed): void {
            $closed += $tenancy->run($tenantId, fn (): int => $conversations->closeStaleOpen($threshold));
        });

        $this->info("Закрыто потерянных диалогов: {$closed}.");

        return self::SUCCESS;
    }
}
