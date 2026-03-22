<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Livewire\Component;
use Relova\Models\CustomFieldDefinition;

/**
 * CRUD component for managing custom field definitions per entity type.
 *
 * Usage: @livewire('relova-custom-field-manager', ['entityType' => 'machine'])
 */
class CustomFieldManager extends Component
{
    public string $entityType = '';

    /** @var \Illuminate\Database\Eloquent\Collection<int, CustomFieldDefinition> */
    public $definitions = [];

    // Form state
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $label = '';

    public string $field_type = 'text';

    public bool $is_required = false;

    public ?int $min_value = null;

    public ?int $max_value = null;

    public ?int $min_length = null;

    public ?int $max_length = null;

    public ?string $regex_pattern = null;

    public int $sort_order = 0;

    public bool $is_active = true;

    public function mount(string $entityType): void
    {
        $this->entityType = $entityType;
        $this->loadDefinitions();
    }

    public function loadDefinitions(): void
    {
        $this->definitions = CustomFieldDefinition::where('entity_type', $this->entityType)
            ->orderBy('sort_order')
            ->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->sort_order = ($this->definitions->max('sort_order') ?? 0) + 1;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $definition = CustomFieldDefinition::findOrFail($id);

        $this->editingId = $definition->id;
        $this->name = $definition->name;
        $this->label = $definition->label;
        $this->field_type = $definition->field_type;
        $this->is_required = $definition->is_required;
        $this->min_value = $definition->min_value;
        $this->max_value = $definition->max_value;
        $this->min_length = $definition->min_length;
        $this->max_length = $definition->max_length;
        $this->regex_pattern = $definition->regex_pattern;
        $this->sort_order = $definition->sort_order;
        $this->is_active = $definition->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'entity_type' => $this->entityType,
            'name' => $this->name,
            'label' => $this->label,
            'field_type' => $this->field_type,
            'is_required' => $this->is_required,
            'min_value' => $this->min_value,
            'max_value' => $this->max_value,
            'min_length' => $this->min_length,
            'max_length' => $this->max_length,
            'regex_pattern' => $this->regex_pattern,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            CustomFieldDefinition::findOrFail($this->editingId)->update($data);
        } else {
            CustomFieldDefinition::create($data);
        }

        $this->resetForm();
        $this->loadDefinitions();
    }

    public function delete(int $id): void
    {
        CustomFieldDefinition::findOrFail($id)->delete();
        $this->loadDefinitions();
    }

    public function restore(int $id): void
    {
        CustomFieldDefinition::withTrashed()->findOrFail($id)->restore();
        $this->loadDefinitions();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    protected function rules(): array
    {
        $uniqueRule = 'unique:'
            .config('relova.table_prefix', 'relova_').'custom_field_definitions,name,'
            .($this->editingId ?? 'NULL')
            .',id,entity_type,'.$this->entityType;

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/', $uniqueRule],
            'label' => 'required|string|max:255',
            'field_type' => 'required|in:'.implode(',', CustomFieldDefinition::fieldTypes()),
            'is_required' => 'boolean',
            'min_value' => 'nullable|integer',
            'max_value' => 'nullable|integer|gte:min_value',
            'min_length' => 'nullable|integer|min:0',
            'max_length' => 'nullable|integer|gte:min_length',
            'regex_pattern' => 'nullable|string|max:500',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.regex' => __('relova::relova.custom_fields.name_format'),
            'name.unique' => __('relova::relova.custom_fields.name_unique'),
        ];
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->label = '';
        $this->field_type = 'text';
        $this->is_required = false;
        $this->min_value = null;
        $this->max_value = null;
        $this->min_length = null;
        $this->max_length = null;
        $this->regex_pattern = null;
        $this->sort_order = 0;
        $this->is_active = true;
        $this->showForm = false;
        $this->resetValidation();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('relova::livewire.custom-field-manager');
    }
}
