<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\TriggerLog;
use Relova\Models\TriggerRule;

/**
 * Self-service UI for trigger rules.
 *
 * A trigger rule watches one field of a mapping's synced snapshot and, when the
 * value crosses the condition, fires `RelovaTriggerMatched` — which the host app
 * turns into a work order. Before this screen existed, rules could only be
 * inserted directly into the database.
 *
 * The rule's `value` column is jsonb. This component stores:
 *   - scalar operators  → {"v": <value>}   (TriggerRuleEngine::unwrapScalar reads it)
 *   - list operators     → ["a", "b", ...]
 *   - "changed"          → null
 */
#[Layout('components.layouts.app')]
class TriggerRuleManager extends Component
{
    public string $tenantId = '';

    /** Premises this manager is scoped to (auto-set from the authenticated user). */
    public ?int $premisesId = null;

    public bool $showForm = false;

    public bool $editing = false;

    public ?string $editingUid = null;

    // ── Form fields ─────────────────────────────────────────────────────────

    public string $mappingUid = '';

    public string $name = '';

    public string $description = '';

    public string $targetField = '';

    public string $operator = 'above_threshold';

    /** Single value for scalar operators (eq, gt, contains, …). */
    public string $valueScalar = '';

    /** Repeatable values for the list operators (in, not_in). */
    public array $valueList = [''];

    public int $cooldownMinutes = 1440;

    public string $priority = 'normal';

    public string $woTitleTemplate = '';

    public string $woDescriptionTemplate = '';

    public bool $active = true;

    // ── Static option sets ─────────────────────────────────────────────────

    /** operator => needs no value | scalar | list */
    public const OPERATORS = [
        'changed' => 'none',
        'eq' => 'scalar',
        'neq' => 'scalar',
        'gt' => 'scalar',
        'gte' => 'scalar',
        'lt' => 'scalar',
        'lte' => 'scalar',
        'above_threshold' => 'scalar',
        'below_threshold' => 'scalar',
        'contains' => 'scalar',
        'in' => 'list',
        'not_in' => 'list',
    ];

    /** @var array<int, string> */
    public array $priorities = ['low', 'normal', 'high', 'critical'];

    // ──────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        if (function_exists('tenant') && tenant()) {
            $this->tenantId = (string) tenant('id');
        } elseif (app()->bound('relova.current_tenant')) {
            $this->tenantId = (string) app('relova.current_tenant');
        } else {
            $this->tenantId = '';
        }

        $this->premisesId = Auth::user()?->premises_id;
    }

    /** The value input mode for the currently-selected operator. */
    public function getValueModeProperty(): string
    {
        return self::OPERATORS[$this->operator] ?? 'scalar';
    }

    /**
     * Suggested target fields for the field datalist — the display fields and
     * mapped remote columns of the selected mapping.
     *
     * @return array<int, string>
     */
    public function getFieldSuggestionsProperty(): array
    {
        if ($this->mappingUid === '') {
            return [];
        }

        $mapping = ConnectorModuleMapping::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $this->mappingUid)
            ->first();

        if ($mapping === null) {
            return [];
        }

        return collect($mapping->display_fields ?? [])
            ->merge(array_values($mapping->field_mappings ?? []))
            ->map(fn ($v) => (string) $v)
            ->filter(fn (string $v) => $v !== '' && ! str_contains($v, '.'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    // ── Repeatable list-value rows ────────────────────────────────────────

    public function addValueRow(): void
    {
        $this->valueList[] = '';
    }

    public function removeValueRow(int $index): void
    {
        unset($this->valueList[$index]);
        $this->valueList = array_values($this->valueList);

        if ($this->valueList === []) {
            $this->valueList = [''];
        }
    }

    // ── CRUD ─────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(string $uid): void
    {
        $rule = $this->query()->where('uid', $uid)->firstOrFail();

        $this->resetForm();

        $this->editing = true;
        $this->editingUid = $rule->uid;
        $this->mappingUid = (string) optional($rule->mapping)->uid;
        $this->name = (string) $rule->name;
        $this->description = (string) ($rule->description ?? '');
        $this->targetField = (string) $rule->target_field;
        $this->operator = (string) $rule->operator;
        $this->cooldownMinutes = (int) $rule->cooldown_minutes;
        $this->priority = (string) ($rule->priority ?: 'normal');
        $this->woTitleTemplate = (string) ($rule->wo_title_template ?? '');
        $this->woDescriptionTemplate = (string) ($rule->wo_description_template ?? '');
        $this->active = (bool) $rule->active;

        $mode = self::OPERATORS[$this->operator] ?? 'scalar';

        if ($mode === 'list') {
            $list = is_array($rule->value) ? array_map('strval', $rule->value) : [];
            $this->valueList = $list === [] ? [''] : $list;
        } elseif ($mode === 'scalar') {
            $this->valueScalar = $this->stringifyScalar($rule->value);
        }

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'mappingUid' => 'required|string',
            'name' => 'required|string|max:160',
            'description' => 'nullable|string|max:2000',
            'targetField' => 'required|string|max:128',
            'operator' => 'required|string|in:'.implode(',', array_keys(self::OPERATORS)),
            'cooldownMinutes' => 'required|integer|min:0|max:43200',
            'priority' => 'required|string|in:'.implode(',', $this->priorities),
            'woTitleTemplate' => 'nullable|string|max:255',
            'woDescriptionTemplate' => 'nullable|string|max:2000',
            'active' => 'boolean',
        ]);

        $mapping = ConnectorModuleMapping::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $this->mappingUid)
            ->firstOrFail();

        $mode = self::OPERATORS[$this->operator];

        if ($mode === 'none') {
            $value = null;
        } elseif ($mode === 'list') {
            $value = array_values(array_filter(
                array_map(fn ($v) => $this->castScalar(trim((string) $v)), $this->valueList),
                fn ($v) => $v !== '' && $v !== null,
            ));

            if ($value === []) {
                $this->addError('valueList', __('relova::ui.trigger_value_list_required'));

                return;
            }
        } else {
            $raw = trim($this->valueScalar);

            if ($raw === '') {
                $this->addError('valueScalar', __('relova::ui.trigger_value_required'));

                return;
            }

            $value = ['v' => $this->castScalar($raw)];
        }

        $payload = [
            'tenant_id' => $this->tenantId,
            'mapping_id' => $mapping->id,
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
            'target_field' => $this->targetField,
            'operator' => $this->operator,
            'value' => $value,
            'cooldown_minutes' => $this->cooldownMinutes,
            'priority' => $this->priority,
            'wo_title_template' => $this->woTitleTemplate !== '' ? $this->woTitleTemplate : null,
            'wo_description_template' => $this->woDescriptionTemplate !== '' ? $this->woDescriptionTemplate : null,
            'active' => $this->active,
        ];

        if ($this->editing && $this->editingUid !== null) {
            $this->query()->where('uid', $this->editingUid)->firstOrFail()->update($payload);
        } else {
            TriggerRule::create($payload);
        }

        $this->closeForm();
    }

    public function toggle(string $uid): void
    {
        $rule = $this->query()->where('uid', $uid)->firstOrFail();
        $rule->update(['active' => ! $rule->active]);
    }

    public function delete(string $uid): void
    {
        $this->query()->where('uid', $uid)->delete();
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Base query — trigger rules for this tenant, restricted to mappings that
     * belong to the current premises (or global mappings).
     */
    private function query(): Builder
    {
        $prefix = config('relova.table_prefix', 'relova_');

        return TriggerRule::query()
            ->where($prefix.'trigger_rules.tenant_id', $this->tenantId)
            ->when($this->premisesId !== null, function ($q) use ($prefix) {
                $q->whereExists(function ($sub) use ($prefix) {
                    $sub->selectRaw('1')
                        ->from($prefix.'connector_module_mappings as m')
                        ->whereColumn('m.id', $prefix.'trigger_rules.mapping_id')
                        ->where(function ($w) {
                            $w->where('m.premises_id', $this->premisesId)
                                ->orWhereNull('m.premises_id');
                        });
                });
            });
    }

    private function castScalar(string $raw): string|int|float
    {
        if ($raw === '') {
            return '';
        }

        if (filter_var($raw, FILTER_VALIDATE_INT) !== false) {
            return (int) $raw;
        }

        if (is_numeric($raw)) {
            return (float) $raw;
        }

        return $raw;
    }

    private function stringifyScalar(mixed $value): string
    {
        if (is_array($value)) {
            if (array_key_exists('v', $value)) {
                $value = $value['v'];
            } elseif (count($value) === 1 && array_is_list($value)) {
                $value = $value[0];
            } else {
                return '';
            }
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function resetForm(): void
    {
        $this->editing = false;
        $this->editingUid = null;
        $this->mappingUid = '';
        $this->name = '';
        $this->description = '';
        $this->targetField = '';
        $this->operator = 'above_threshold';
        $this->valueScalar = '';
        $this->valueList = [''];
        $this->cooldownMinutes = 1440;
        $this->priority = 'normal';
        $this->woTitleTemplate = '';
        $this->woDescriptionTemplate = '';
        $this->active = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $mappings = ConnectorModuleMapping::query()
            ->where('tenant_id', $this->tenantId)
            ->when($this->premisesId !== null, fn ($q) => $q->where(function ($w) {
                $w->where('premises_id', $this->premisesId)->orWhereNull('premises_id');
            }))
            ->orderBy('module_key')
            ->get(['id', 'uid', 'module_key', 'remote_table']);

        $rules = $this->query()
            ->with('mapping:id,uid,module_key,remote_table')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $recentLog = TriggerLog::query()
            ->where('tenant_id', $this->tenantId)
            ->whereIn('rule_id', $rules->pluck('id'))
            ->orderByDesc('triggered_at')
            ->limit(20)
            ->get();

        return view('relova::livewire.trigger-rule-manager', [
            'mappings' => $mappings,
            'rules' => $rules,
            'recentLog' => $recentLog,
        ]);
    }
}
