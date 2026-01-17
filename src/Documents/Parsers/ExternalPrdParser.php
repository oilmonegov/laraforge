<?php

declare(strict_types=1);

namespace LaraForge\Documents\Parsers;

use LaraForge\Documents\Contracts\DocumentInterface;
use LaraForge\Documents\Contracts\DocumentParserInterface;
use LaraForge\Documents\ProductRequirements;

class ExternalPrdParser implements DocumentParserInterface
{
    public function type(): string
    {
        return 'prd';
    }

    public function extensions(): array
    {
        return ['md', 'txt', 'markdown'];
    }

    public function parse(string $content, ?string $path = null): DocumentInterface
    {
        $extracted = $this->extractStructure($content);

        $prd = new ProductRequirements(
            title: $extracted['title'] ?? $this->extractTitleFromPath($path) ?? 'Imported PRD',
            version: '1.0',
            status: 'draft',
            featureId: $extracted['feature_id'] ?? null,
        );

        if (! empty($extracted['problem_statement'])) {
            $prd->setProblemStatement($extracted['problem_statement']);
        }

        if (! empty($extracted['target_audience'])) {
            $prd->setTargetAudience($extracted['target_audience']);
        }

        foreach ($extracted['objectives'] as $i => $objective) {
            $prd->addObjective(
                id: $objective['id'] ?? 'OBJ-'.($i + 1),
                description: $objective['description'],
                priority: $objective['priority'] ?? 'medium',
                rationale: $objective['rationale'] ?? null,
            );
        }

        foreach ($extracted['requirements'] as $i => $requirement) {
            $prd->addRequirement(
                id: $requirement['id'] ?? 'REQ-'.($i + 1),
                description: $requirement['description'],
                priority: $requirement['priority'] ?? 'medium',
            );
        }

        foreach ($extracted['user_stories'] as $i => $story) {
            $prd->addUserStory(
                id: $story['id'] ?? 'US-'.($i + 1),
                description: $story['description'],
            );
        }

        foreach ($extracted['constraints'] as $constraint) {
            $prd->addConstraint($constraint);
        }

        foreach ($extracted['assumptions'] as $assumption) {
            $prd->addAssumption($assumption);
        }

        foreach ($extracted['out_of_scope'] as $item) {
            $prd->addOutOfScope($item);
        }

        foreach ($extracted['success_criteria'] as $criterion) {
            $prd->addSuccessCriterion($criterion);
        }

        // Store original content as metadata
        $prd->setMetadata('original_content', $content);
        $prd->setMetadata('original_format', $this->detectFormat($content));
        $prd->setMetadata('imported_at', date('c'));

        if ($path) {
            $prd->setMetadata('source_path', $path);
        }

        return $prd;
    }

    public function parseFile(string $path): DocumentInterface
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$path}");
        }

        return $this->parse($content, $path);
    }

    public function canParse(string $content): bool
    {
        // Can parse any text content
        return true;
    }

    public function validateContent(string $content): array
    {
        $errors = [];

        if (empty(trim($content))) {
            $errors[] = 'Content is empty';
        }

        return $errors;
    }

    /**
     * Extract structured data from unstructured PRD content.
     *
     * @return array{
     *     title: ?string,
     *     feature_id: ?string,
     *     problem_statement: ?string,
     *     target_audience: ?string,
     *     objectives: array<array{id?: string, description: string, priority?: string, rationale?: string}>,
     *     requirements: array<array{id?: string, description: string, priority?: string}>,
     *     user_stories: array<array{id?: string, description: string}>,
     *     constraints: array<string>,
     *     assumptions: array<string>,
     *     out_of_scope: array<string>,
     *     success_criteria: array<string>
     * }
     */
    public function extractStructure(string $content): array
    {
        $result = [
            'title' => null,
            'feature_id' => null,
            'problem_statement' => null,
            'target_audience' => null,
            'objectives' => [],
            'requirements' => [],
            'user_stories' => [],
            'constraints' => [],
            'assumptions' => [],
            'out_of_scope' => [],
            'success_criteria' => [],
        ];

        // Extract title (first h1 or # heading)
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            $result['title'] = trim($matches[1]);
        }

        // Extract sections using heading patterns
        $sections = $this->splitIntoSections($content);

        // If no markdown sections found, try plain text sections
        if (empty($sections)) {
            $sections = $this->splitIntoPlainTextSections($content);
        }

        foreach ($sections as $heading => $body) {
            $normalizedHeading = strtolower(trim($heading));

            // Problem Statement / Overview / Background
            if ($this->matchesSection($normalizedHeading, ['problem', 'overview', 'background', 'context', 'introduction'])) {
                $result['problem_statement'] = $this->cleanSectionContent($body);
            }

            // Target Audience / Users
            if ($this->matchesSection($normalizedHeading, ['target audience', 'users', 'audience', 'user personas', 'stakeholders'])) {
                $result['target_audience'] = $this->cleanSectionContent($body);
            }

            // Objectives / Goals
            if ($this->matchesSection($normalizedHeading, ['objective', 'goal', 'purpose', 'aim', 'key goal'])) {
                $result['objectives'] = $this->extractListItems($body, 'objective');
            }

            // Requirements / Functional Requirements
            if ($this->matchesSection($normalizedHeading, ['requirement', 'functional requirement', 'feature', 'specification'])) {
                $result['requirements'] = $this->extractListItems($body, 'requirement');
            }

            // User Stories
            if ($this->matchesSection($normalizedHeading, ['user stor', 'user scenario', 'use case'])) {
                $result['user_stories'] = $this->extractUserStories($body);
            }

            // Constraints / Limitations
            if ($this->matchesSection($normalizedHeading, ['constraint', 'limitation', 'restriction'])) {
                $result['constraints'] = $this->extractSimpleList($body);
            }

            // Assumptions
            if ($this->matchesSection($normalizedHeading, ['assumption', 'premise'])) {
                $result['assumptions'] = $this->extractSimpleList($body);
            }

            // Out of Scope / Non-Goals
            if ($this->matchesSection($normalizedHeading, ['out of scope', 'non-goal', 'exclusion', 'not included', 'scope exclusion'])) {
                $result['out_of_scope'] = $this->extractSimpleList($body);
            }

            // Success Criteria / Acceptance Criteria / KPIs
            if ($this->matchesSection($normalizedHeading, ['success criter', 'acceptance criter', 'kpi', 'metric', 'measure of success'])) {
                $result['success_criteria'] = $this->extractSimpleList($body);
            }
        }

        // If no objectives found, try to extract from requirements or create a default
        if (empty($result['objectives']) && ! empty($result['requirements'])) {
            $result['objectives'][] = [
                'id' => 'OBJ-1',
                'description' => 'Implement the specified requirements',
                'priority' => 'high',
            ];
        }

        return $result;
    }

    /**
     * Split content into sections based on headings.
     *
     * @return array<string, string>
     */
    private function splitIntoSections(string $content): array
    {
        $sections = [];

        // Match ## or ### headings
        $pattern = '/^(#{2,3})\s+(.+)$/m';
        $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $sections;
        }

        // First part is content before any heading
        $i = 1;
        while ($i < count($parts)) {
            $level = $parts[$i] ?? '';
            $heading = $parts[$i + 1] ?? '';
            $body = $parts[$i + 2] ?? '';

            if (! empty($heading)) {
                $sections[trim($heading)] = trim($body);
            }
            $i += 3;
        }

        return $sections;
    }

    /**
     * Split plain text content into sections (e.g., "Goals:" or "Requirements:").
     *
     * @return array<string, string>
     */
    private function splitIntoPlainTextSections(string $content): array
    {
        $sections = [];

        // Match patterns like "Section Name:" at the start of a line
        $pattern = '/^([A-Za-z][A-Za-z\s]{2,30}):[\s]*$/m';
        $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || count($parts) < 3) {
            return $sections;
        }

        // Skip first part (before any section)
        $i = 1;
        while ($i < count($parts)) {
            $heading = $parts[$i] ?? '';
            $body = $parts[$i + 1] ?? '';

            if (! empty($heading)) {
                $sections[trim($heading)] = trim($body);
            }
            $i += 2;
        }

        return $sections;
    }

    /**
     * Check if heading matches any of the patterns.
     *
     * @param  array<string>  $patterns
     */
    private function matchesSection(string $heading, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($heading, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract list items with optional ID and priority.
     *
     * @return array<array{id?: string, description: string, priority?: string}>
     */
    private function extractListItems(string $content, string $type): array
    {
        $items = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Match list items: - item, * item, 1. item, etc.
            if (preg_match('/^[-*•]\s+(.+)$/', $line, $matches) ||
                preg_match('/^\d+\.\s+(.+)$/', $line, $matches)) {
                $text = trim($matches[1]);

                if (empty($text)) {
                    continue;
                }

                $item = ['description' => $text];

                // Try to extract ID from patterns like [REQ-1], (REQ-1), REQ-1:
                if (preg_match('/^\[?([A-Z]+-\d+)\]?[:\s]+(.+)$/', $text, $idMatch)) {
                    $item['id'] = $idMatch[1];
                    $item['description'] = trim($idMatch[2]);
                }

                // Try to extract priority
                $lowerText = strtolower($text);
                if (str_contains($lowerText, '(high)') || str_contains($lowerText, '[high]') || str_contains($lowerText, 'priority: high')) {
                    $item['priority'] = 'high';
                    $item['description'] = preg_replace('/\s*[\[(]?high[\])]?\s*/i', ' ', $item['description']);
                } elseif (str_contains($lowerText, '(low)') || str_contains($lowerText, '[low]') || str_contains($lowerText, 'priority: low')) {
                    $item['priority'] = 'low';
                    $item['description'] = preg_replace('/\s*[\[(]?low[\])]?\s*/i', ' ', $item['description']);
                } elseif (str_contains($lowerText, '(critical)') || str_contains($lowerText, '[critical]')) {
                    $item['priority'] = 'critical';
                    $item['description'] = preg_replace('/\s*[\[(]?critical[\])]?\s*/i', ' ', $item['description']);
                }

                $item['description'] = trim($item['description'] ?? '');

                if (! empty($item['description'])) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Extract user stories (As a... I want... So that...).
     *
     * @return array<array{id?: string, description: string}>
     */
    private function extractUserStories(string $content): array
    {
        $stories = [];

        // Match "As a [role], I want [feature] so that [benefit]"
        $pattern = '/As\s+a[n]?\s+(.+?),?\s+I\s+want\s+(.+?)(?:\s+so\s+that\s+(.+?))?(?:\.|$)/i';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $i => $match) {
                $stories[] = [
                    'id' => 'US-'.($i + 1),
                    'description' => trim($match[0]),
                ];
            }
        }

        // Also check for regular list items
        $listItems = $this->extractListItems($content, 'story');
        foreach ($listItems as $item) {
            // Avoid duplicates
            $isDuplicate = false;
            foreach ($stories as $story) {
                if (str_contains($story['description'], $item['description']) ||
                    str_contains($item['description'], $story['description'])) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (! $isDuplicate) {
                $stories[] = $item;
            }
        }

        return $stories;
    }

    /**
     * Extract simple string list.
     *
     * @return array<string>
     */
    private function extractSimpleList(string $content): array
    {
        $items = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^[-*•]\s+(.+)$/', $line, $matches) ||
                preg_match('/^\d+\.\s+(.+)$/', $line, $matches)) {
                $text = trim($matches[1]);
                if (! empty($text)) {
                    $items[] = $text;
                }
            }
        }

        return $items;
    }

    /**
     * Clean section content by removing excessive whitespace.
     */
    private function cleanSectionContent(string $content): string
    {
        // Remove list markers for paragraph content
        $content = preg_replace('/^[-*•]\s+/m', '', $content);

        // Collapse multiple newlines
        $content = preg_replace('/\n{3,}/', "\n\n", $content ?? '');

        return trim($content ?? '');
    }

    /**
     * Detect the format of the content.
     */
    private function detectFormat(string $content): string
    {
        if (preg_match('/^#\s+/', $content)) {
            return 'markdown';
        }

        if (str_contains($content, '---') && preg_match('/^[a-z_]+:/m', $content)) {
            return 'yaml';
        }

        if (str_starts_with(trim($content), '{')) {
            return 'json';
        }

        return 'text';
    }

    /**
     * Extract title from file path.
     */
    private function extractTitleFromPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $filename = pathinfo($path, PATHINFO_FILENAME);

        // Remove common prefixes/suffixes
        $filename = preg_replace('/[-_]?prd[-_]?/i', '', $filename);
        $filename = preg_replace('/[-_]/', ' ', $filename ?? '');

        return ucwords(trim($filename ?? ''));
    }
}
