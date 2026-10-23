<?php

namespace App\Services\Operations;

use App\Enums\Permission;
use App\Enums\Priority;
use App\Models\Estate;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Safety and facility inspection checklists. Completing an inspection with failed items can
 * raise a single maintenance request listing every failure.
 */
class InspectionService
{
    public const RESULTS = ['pass', 'fail', 'na'];

    public function __construct(private AuditLogger $audit, private MaintenanceService $maintenance) {}

    /**
     * @param  list<string>  $items
     */
    public function createTemplate(Estate $estate, User $actor, string $name, string $category, array $items): InspectionTemplate
    {
        $items = array_values(array_unique(array_filter(array_map('trim', $items))));

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one checklist item.']);
        }

        $template = InspectionTemplate::create(['estate_id' => $estate->id, 'name' => $name, 'category' => $category, 'items' => $items]);
        $this->audit->record('inspection_template.created', $template, ['items' => count($items)], $estate, $actor);

        return $template;
    }

    public function start(InspectionTemplate $template, User $inspector, ?string $location): Inspection
    {
        $this->authorize($template->estate, $inspector);

        if (! $template->is_active) {
            throw ValidationException::withMessages(['template' => 'This checklist is no longer in use.']);
        }

        return Inspection::create([
            'estate_id' => $template->estate_id,
            'inspection_template_id' => $template->id,
            'title' => $template->name,
            'location' => $location,
            'inspector_id' => $inspector->id,
            'results' => collect($template->items)->map(fn (string $item) => ['item' => $item, 'result' => null, 'note' => null])->all(),
        ]);
    }

    /**
     * @param  array<int, array{result?: ?string, note?: ?string}>  $answers  Indexed like the checklist items.
     */
    public function complete(Inspection $inspection, User $inspector, array $answers, ?string $notes, bool $raiseMaintenance): Inspection
    {
        $this->authorize($inspection->estate, $inspector);

        return DB::transaction(function () use ($inspection, $inspector, $answers, $notes, $raiseMaintenance) {
            $locked = Inspection::whereKey($inspection->id)->lockForUpdate()->firstOrFail();

            if ($locked->isComplete()) {
                throw ValidationException::withMessages(['inspection' => 'This inspection has already been completed.']);
            }

            $results = [];
            foreach ($locked->results as $index => $row) {
                $result = $answers[$index]['result'] ?? null;

                if (! in_array($result, self::RESULTS, true)) {
                    throw ValidationException::withMessages(["answers.{$index}.result" => "Record a result for “{$row['item']}”."]);
                }

                $results[] = ['item' => $row['item'], 'result' => $result, 'note' => isset($answers[$index]['note']) ? mb_substr(trim((string) $answers[$index]['note']), 0, 300) ?: null : null];
            }

            $failed = array_values(array_filter($results, fn (array $r) => $r['result'] === 'fail'));

            $locked->update([
                'results' => $results,
                'failed_count' => count($failed),
                'notes' => $notes,
                'status' => 'completed',
                'completed_at' => now(),
                'inspector_id' => $inspector->id,
            ]);

            if ($failed && $raiseMaintenance) {
                $this->raiseMaintenance($locked, $inspector, $failed);
            }

            $this->audit->record('inspection.completed', $locked, ['failed' => count($failed)], $locked->estate_id, $inspector);

            return $locked;
        });
    }

    /**
     * @param  list<array{item: string, result: string, note: ?string}>  $failed
     */
    private function raiseMaintenance(Inspection $inspection, User $inspector, array $failed): MaintenanceRequest
    {
        $lines = collect($failed)->map(fn (array $r) => '• '.$r['item'].($r['note'] ? " — {$r['note']}" : ''))->implode("\n");

        return $this->maintenance->submit($inspector, $inspection->estate, [
            'category' => 'common_area',
            'location' => $inspection->location ?: $inspection->title,
            'title' => "Inspection follow-up: {$inspection->title}",
            'description' => "Failed items from inspection #{$inspection->id} on ".now()->toDateString().":\n{$lines}",
            'priority' => count($failed) > 2 ? Priority::High->value : Priority::Normal->value,
        ]);
    }

    private function authorize(Estate $estate, User $user): void
    {
        abort_unless($user->hasEstatePermission($estate, Permission::InspectionsPerform), 403);
    }
}
