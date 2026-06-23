<?php

declare(strict_types=1);

use App\Enums\DealStageAutomation;
use App\Enums\DealStageKind;
use App\Models\DealStage;
use App\Tenancy\TenantInitializer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Роль стадии в авто-движении воронки (см. DealStageAutomation): по ней бот двигает
 * сделку при событиях диалога. Null — кастомная стадия, авто-движок её не трогает.
 *
 * Бэкфилл: у существующих тенантов дефолтные стадии засеяны ДО появления ролей —
 * проставляем роли по kind (первая активная → New, прочие активные → Working,
 * won → Won, lost → Lost). Идемпотентно (только где роль ещё пуста). Под
 * tenant-контекстом — RLS соблюдён.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_stages', function (Blueprint $table): void {
            $table->string('automation', 16)->nullable()->after('kind');
        });

        /** @var TenantInitializer $tenants */
        $tenants = app(TenantInitializer::class);

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $tenants->run((string) $tenantId, function (): void {
                $firstActive = true;
                foreach (DealStage::query()->orderBy('sort_order')->get() as $stage) {
                    if ($stage->automation !== null) {
                        if ($stage->kind === DealStageKind::Active) {
                            $firstActive = false;
                        }

                        continue;
                    }

                    $role = match ($stage->kind) {
                        DealStageKind::Won => DealStageAutomation::Won,
                        DealStageKind::Lost => DealStageAutomation::Lost,
                        DealStageKind::Active => $firstActive ? DealStageAutomation::New : DealStageAutomation::Working,
                    };
                    if ($stage->kind === DealStageKind::Active) {
                        $firstActive = false;
                    }

                    $stage->forceFill(['automation' => $role->value])->save();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('deal_stages', function (Blueprint $table): void {
            $table->dropColumn('automation');
        });
    }
};
