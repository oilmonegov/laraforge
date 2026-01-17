<?php

declare(strict_types=1);

namespace LaraForge\Criteria;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection of acceptance criteria for a feature.
 *
 * @implements IteratorAggregate<int, AcceptanceCriterion>
 */
final class AcceptanceCriteria implements Countable, IteratorAggregate
{
    /**
     * @param  array<AcceptanceCriterion>  $criteria
     */
    public function __construct(
        public readonly string $feature,
        private array $criteria = [],
    ) {}

    /**
     * Create from array data.
     *
     * @param  array{feature: string, criteria: array<array{id: string, description: string, assertions?: array<string>}>}  $data
     */
    public static function fromArray(array $data): self
    {
        $criteria = array_map(
            fn (array $item) => AcceptanceCriterion::fromArray($item),
            $data['criteria'],
        );

        return new self(
            feature: $data['feature'],
            criteria: $criteria,
        );
    }

    /**
     * Convert to array.
     *
     * @return array{feature: string, criteria: array<array{id: string, description: string, assertions: array<string>}>}
     */
    public function toArray(): array
    {
        return [
            'feature' => $this->feature,
            'criteria' => array_map(fn (AcceptanceCriterion $c) => $c->toArray(), $this->criteria),
        ];
    }

    /**
     * Add a criterion.
     */
    public function add(AcceptanceCriterion $criterion): void
    {
        $this->criteria[] = $criterion;
    }

    /**
     * Get all criteria.
     *
     * @return array<AcceptanceCriterion>
     */
    public function all(): array
    {
        return $this->criteria;
    }

    /**
     * Get a criterion by ID.
     */
    public function get(string $id): ?AcceptanceCriterion
    {
        foreach ($this->criteria as $criterion) {
            if ($criterion->id === $id) {
                return $criterion;
            }
        }

        return null;
    }

    /**
     * Check if a criterion exists by ID.
     */
    public function has(string $id): bool
    {
        return $this->get($id) !== null;
    }

    /**
     * Get all criterion IDs.
     *
     * @return array<string>
     */
    public function ids(): array
    {
        return array_map(fn (AcceptanceCriterion $c) => $c->id, $this->criteria);
    }

    public function count(): int
    {
        return count($this->criteria);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return Traversable<int, AcceptanceCriterion>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->criteria);
    }
}
