<?php

declare(strict_types=1);

namespace LaraForge\Criteria;

/**
 * Result of criteria validation against test coverage.
 */
final readonly class ValidationResult
{
    /**
     * @param  array<string>  $coveredIds  Criterion IDs that are covered by tests
     * @param  array<string>  $missingIds  Criterion IDs that are missing test coverage
     */
    public function __construct(
        public array $coveredIds,
        public array $missingIds,
        public AcceptanceCriteria $criteria,
    ) {}

    /**
     * Check if all criteria are covered.
     */
    public function isFullyCovered(): bool
    {
        return empty($this->missingIds);
    }

    /**
     * Get coverage percentage.
     */
    public function coveragePercentage(): float
    {
        $total = count($this->coveredIds) + count($this->missingIds);

        if ($total === 0) {
            return 100.0;
        }

        return (count($this->coveredIds) / $total) * 100;
    }

    /**
     * Get covered criteria.
     *
     * @return array<AcceptanceCriterion>
     */
    public function coveredCriteria(): array
    {
        return array_filter(
            $this->criteria->all(),
            fn (AcceptanceCriterion $c) => in_array($c->id, $this->coveredIds, true),
        );
    }

    /**
     * Get missing criteria.
     *
     * @return array<AcceptanceCriterion>
     */
    public function missingCriteria(): array
    {
        return array_filter(
            $this->criteria->all(),
            fn (AcceptanceCriterion $c) => in_array($c->id, $this->missingIds, true),
        );
    }

    /**
     * Create a result indicating full coverage.
     */
    public static function fullyCovered(AcceptanceCriteria $criteria): self
    {
        return new self(
            coveredIds: $criteria->ids(),
            missingIds: [],
            criteria: $criteria,
        );
    }

    /**
     * Create a result indicating no coverage.
     */
    public static function noCoverage(AcceptanceCriteria $criteria): self
    {
        return new self(
            coveredIds: [],
            missingIds: $criteria->ids(),
            criteria: $criteria,
        );
    }
}
