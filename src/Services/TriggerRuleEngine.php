<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Events\RelovaTriggerMatched;
use Relova\Models\TriggerLog;
use Relova\Models\TriggerRule;
use Relova\Models\VirtualEntityReference;

/**
 * Evaluates declarative trigger rules against a VirtualEntityReference snapshot
 * change. Determinism first: pure operator-based comparisons. No LLM cost.
 *
 * For each match it:
 *  1. Records a `matched` row in trigger_log
 *  2. Updates last_triggered_at on the rule (so cooldown applies next pass)
 *  3. Fires `RelovaTriggerMatched` so the host app's listener can react
 *     (e.g. the host's CreateRelovaWorkOrder listener calls
 *     WorkOrderGenerationService with source='relova').
 *
 * The engine itself never creates WOs — it stays free of host-app coupling.
 */
class TriggerRuleEngine
{
    /**
     * @param  array<string,mixed>  $previous
     * @param  array<string,mixed>  $current
     * @return array<int,TriggerRule>
     */
    public function evaluate(
        VirtualEntityReference $ref,
        array $previous,
        array $current,
    ): array {
        $mapping = $ref->mapping_id;
        if ($mapping === null) {
            return [];
        }

        $rules = TriggerRule::query()
            ->where('mapping_id', $mapping)
            ->where('tenant_id', $ref->tenant_id)
            ->where('active', true)
            ->get();

        $matched = [];

        foreach ($rules as $rule) {
            if ($rule->inCooldown()) {
                $this->log($rule, $ref, 'skipped', 'cooldown_active', [
                    'previous' => $previous,
                    'current' => $current,
                ]);

                continue;
            }

            if (! $this->matches($rule, $previous, $current)) {
                continue;
            }

            $matched[] = $rule;
            $rule->forceFill(['last_triggered_at' => now()])->save();

            $this->log($rule, $ref, 'matched', null, [
                'previous_value' => $previous[$rule->target_field] ?? null,
                'current_value' => $current[$rule->target_field] ?? null,
                'operator' => $rule->operator,
                'rule_value' => $rule->value,
            ]);

            event(new RelovaTriggerMatched($ref, $rule, $current));
        }

        return $matched;
    }

    /**
     * @param  array<string,mixed>  $previous
     * @param  array<string,mixed>  $current
     */
    public function matches(TriggerRule $rule, array $previous, array $current): bool
    {
        $field = $rule->target_field;
        $newValue = $current[$field] ?? null;
        $oldValue = $previous[$field] ?? null;

        // value column may be a scalar wrapped in an array (jsonb cast).
        $ruleValue = $this->unwrapScalar($rule->value);

        return match ($rule->operator) {
            'eq' => $newValue == $ruleValue,
            'neq' => $newValue != $ruleValue,
            'gt' => is_numeric($newValue) && is_numeric($ruleValue) && $newValue > $ruleValue,
            'gte' => is_numeric($newValue) && is_numeric($ruleValue) && $newValue >= $ruleValue,
            'lt' => is_numeric($newValue) && is_numeric($ruleValue) && $newValue < $ruleValue,
            'lte' => is_numeric($newValue) && is_numeric($ruleValue) && $newValue <= $ruleValue,
            'above_threshold' => is_numeric($newValue) && is_numeric($ruleValue) && $newValue > $ruleValue,
            'below_threshold' => is_numeric($newValue) && is_numeric($ruleValue) && $newValue < $ruleValue,
            'in' => is_array($rule->value) && in_array($newValue, $rule->value, false),
            'not_in' => is_array($rule->value) && ! in_array($newValue, $rule->value, false),
            'contains' => is_string($newValue) && is_string($ruleValue) && str_contains($newValue, $ruleValue),
            'changed' => $oldValue !== $newValue,
            default => false,
        };
    }

    /**
     * jsonb columns hold arrays; unwrap a single-element array to its value
     * for scalar comparisons. Returns null for empty / non-array values
     * unchanged.
     */
    protected function unwrapScalar(mixed $value): mixed
    {
        if (is_array($value)) {
            // ['v' => 80] convention OR plain [80]
            if (array_key_exists('v', $value)) {
                return $value['v'];
            }
            if (count($value) === 1 && array_is_list($value)) {
                return $value[0];
            }
        }

        return $value;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    protected function log(
        TriggerRule $rule,
        VirtualEntityReference $ref,
        string $outcome,
        ?string $reason,
        array $payload,
    ): void {
        TriggerLog::create([
            'tenant_id' => $rule->tenant_id,
            'rule_id' => $rule->id,
            'virtual_entity_reference_id' => $ref->id,
            'payload' => $payload,
            'outcome' => $outcome,
            'rejection_reason' => $reason,
            'triggered_at' => now(),
        ]);
    }
}
