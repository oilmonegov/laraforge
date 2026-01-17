<?php

declare(strict_types=1);

namespace LaraForge\Documents;

use LaraForge\Documents\Contracts\DocumentInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Manages FRD as a directory structure with multiple markdown files.
 *
 * Structure:
 * .laraforge/docs/<frd-name>/
 * ├── overview.md           # Starting point, high-level summary
 * ├── requirements.md       # Detailed requirements
 * ├── acceptance-criteria.md
 * ├── design/
 * │   ├── architecture.md
 * │   ├── pseudocode.md
 * │   └── data-flows.md
 * ├── contracts/
 * │   └── test-contracts.md
 * ├── metadata.yaml         # Machine-readable metadata
 * └── index.md              # Auto-generated table of contents
 */
class FrdDirectory implements DocumentInterface
{
    private Filesystem $filesystem;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $dirPath,
        private string $title,
        private string $version = '1.0',
        private string $status = 'draft',
        private ?string $featureId = null,
        private array $metadata = [],
    ) {
        $this->filesystem = new Filesystem;
    }

    public static function create(string $basePath, string $name, string $title): self
    {
        $slug = self::slugify($name);
        $dirPath = rtrim($basePath, '/').'/'.$slug;

        $frd = new self(
            dirPath: $dirPath,
            title: $title,
            featureId: $slug,
        );

        $frd->initialize();

        return $frd;
    }

    public static function load(string $dirPath): ?self
    {
        $filesystem = new Filesystem;

        if (! $filesystem->exists($dirPath)) {
            return null;
        }

        $metadataPath = $dirPath.'/metadata.yaml';
        if (! $filesystem->exists($metadataPath)) {
            return null;
        }

        $metadata = Yaml::parseFile($metadataPath);

        return new self(
            dirPath: $dirPath,
            title: $metadata['title'] ?? basename($dirPath),
            version: $metadata['version'] ?? '1.0',
            status: $metadata['status'] ?? 'draft',
            featureId: $metadata['feature_id'] ?? null,
            metadata: $metadata['metadata'] ?? [],
        );
    }

    public function initialize(): void
    {
        // Create directory structure
        $dirs = [
            $this->dirPath,
            $this->dirPath.'/design',
            $this->dirPath.'/contracts',
        ];

        foreach ($dirs as $dir) {
            if (! $this->filesystem->exists($dir)) {
                $this->filesystem->mkdir($dir, 0755);
            }
        }

        // Create initial files
        $this->createOverview();
        $this->createRequirements();
        $this->createAcceptanceCriteria();
        $this->createDesignFiles();
        $this->createContractsFiles();
        $this->saveMetadata();
        $this->updateIndex();
    }

    public function type(): string
    {
        return 'frd-directory';
    }

    public function title(): string
    {
        return $this->title;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function featureId(): ?string
    {
        return $this->featureId;
    }

    public function content(): array
    {
        return [
            'overview' => $this->readFile('overview.md'),
            'requirements' => $this->readFile('requirements.md'),
            'acceptance_criteria' => $this->readFile('acceptance-criteria.md'),
            'design' => [
                'architecture' => $this->readFile('design/architecture.md'),
                'pseudocode' => $this->readFile('design/pseudocode.md'),
                'data_flows' => $this->readFile('design/data-flows.md'),
            ],
            'contracts' => [
                'test_contracts' => $this->readFile('contracts/test-contracts.md'),
            ],
        ];
    }

    public function rawContent(): string
    {
        return $this->readFile('overview.md') ?? '';
    }

    public function path(): ?string
    {
        return $this->dirPath;
    }

    public function isValid(): bool
    {
        return $this->filesystem->exists($this->dirPath.'/overview.md')
            && $this->filesystem->exists($this->dirPath.'/metadata.yaml');
    }

    public function validationErrors(): array
    {
        $errors = [];

        if (! $this->filesystem->exists($this->dirPath.'/overview.md')) {
            $errors[] = 'Missing overview.md';
        }

        if (! $this->filesystem->exists($this->dirPath.'/metadata.yaml')) {
            $errors[] = 'Missing metadata.yaml';
        }

        return $errors;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'title' => $this->title,
            'version' => $this->version,
            'status' => $this->status,
            'feature_id' => $this->featureId,
            'path' => $this->dirPath,
            'files' => $this->listFiles(),
            'metadata' => $this->metadata,
        ];
    }

    public function serialize(): string
    {
        return Yaml::dump($this->toArray(), 10, 2);
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->saveMetadata();
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
        $this->saveMetadata();
    }

    /**
     * Write content to a specific file.
     */
    public function writeFile(string $relativePath, string $content): void
    {
        $fullPath = $this->dirPath.'/'.$relativePath;
        $dir = dirname($fullPath);

        if (! $this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir, 0755);
        }

        $this->filesystem->dumpFile($fullPath, $content);
        $this->updateIndex();
    }

    /**
     * Read content from a specific file.
     */
    public function readFile(string $relativePath): ?string
    {
        $fullPath = $this->dirPath.'/'.$relativePath;

        if (! $this->filesystem->exists($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath) ?: null;
    }

    /**
     * List all files in the FRD directory.
     *
     * @return array<string>
     */
    public function listFiles(): array
    {
        if (! $this->filesystem->exists($this->dirPath)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dirPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = str_replace($this->dirPath.'/', '', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Get the overview content (starting point).
     */
    public function getOverview(): ?string
    {
        return $this->readFile('overview.md');
    }

    /**
     * Update the overview content.
     */
    public function setOverview(string $content): void
    {
        $this->writeFile('overview.md', $content);
    }

    /**
     * Get the requirements content.
     */
    public function getRequirements(): ?string
    {
        return $this->readFile('requirements.md');
    }

    /**
     * Set the requirements content.
     */
    public function setRequirements(string $content): void
    {
        $this->writeFile('requirements.md', $content);
    }

    /**
     * Get pseudocode content.
     */
    public function getPseudocode(): ?string
    {
        return $this->readFile('design/pseudocode.md');
    }

    /**
     * Set pseudocode content.
     */
    public function setPseudocode(string $content): void
    {
        $this->writeFile('design/pseudocode.md', $content);
    }

    /**
     * Get test contracts content.
     */
    public function getTestContracts(): ?string
    {
        return $this->readFile('contracts/test-contracts.md');
    }

    /**
     * Set test contracts content.
     */
    public function setTestContracts(string $content): void
    {
        $this->writeFile('contracts/test-contracts.md', $content);
    }

    private function createOverview(): void
    {
        $content = <<<MD
# {$this->title}

> **Status:** {$this->status} | **Version:** {$this->version}

## Summary

<!-- Brief summary of what this feature does -->

## Quick Links

- [Requirements](requirements.md)
- [Acceptance Criteria](acceptance-criteria.md)
- [Architecture](design/architecture.md)
- [Pseudocode](design/pseudocode.md)
- [Test Contracts](contracts/test-contracts.md)

## Context

<!-- Why this feature is needed, business context -->

## Goals

<!-- Primary goals this feature should achieve -->
1.
2.
3.

## Non-Goals

<!-- What this feature explicitly does NOT cover -->
-

---

*Last updated: {$this->timestamp()}*
MD;

        $this->writeFile('overview.md', $content);
    }

    private function createRequirements(): void
    {
        $content = <<<'MD'
# Requirements

## Functional Requirements

### REQ-001: [Requirement Title]

- **Priority:** High
- **Description:** [Detailed description]
- **Rationale:** [Why this is needed]

### REQ-002: [Requirement Title]

- **Priority:** Medium
- **Description:** [Detailed description]
- **Rationale:** [Why this is needed]

## Non-Functional Requirements

### NFR-001: Performance

- **Description:** [Performance requirements]
- **Metrics:** [How to measure]

### NFR-002: Security

- **Description:** [Security requirements]
- **Compliance:** [Any compliance requirements]

## Dependencies

- [List external dependencies]

## Constraints

- [List technical or business constraints]
MD;

        $this->writeFile('requirements.md', $content);
    }

    private function createAcceptanceCriteria(): void
    {
        $content = <<<'MD'
# Acceptance Criteria

## Scenario 1: [Happy Path]

**Given** [preconditions]
**When** [action]
**Then** [expected outcome]

### Verification

- [ ] [Specific check]
- [ ] [Specific check]

## Scenario 2: [Error Case]

**Given** [preconditions]
**When** [action with error]
**Then** [error handling outcome]

### Verification

- [ ] [Specific check]
- [ ] [Specific check]

## Scenario 3: [Edge Case]

**Given** [edge case preconditions]
**When** [action]
**Then** [expected outcome]

### Verification

- [ ] [Specific check]

## Global Invariants

These conditions must ALWAYS hold true:

- [ ] [Invariant 1]
- [ ] [Invariant 2]
MD;

        $this->writeFile('acceptance-criteria.md', $content);
    }

    private function createDesignFiles(): void
    {
        $architecture = <<<'MD'
# Architecture

## Overview

<!-- High-level architecture description -->

## Components

### Component 1: [Name]

- **Purpose:** [What it does]
- **Interfaces:** [Public APIs]
- **Dependencies:** [What it depends on]

### Component 2: [Name]

- **Purpose:** [What it does]
- **Interfaces:** [Public APIs]
- **Dependencies:** [What it depends on]

## Diagrams

<!-- Include or reference architecture diagrams -->

## Architectural Decisions

### ADR-001: [Decision Title]

- **Context:** [Why this decision was needed]
- **Options Considered:**
  1. [Option A]
  2. [Option B]
- **Decision:** [What was decided]
- **Rationale:** [Why this option was chosen]
- **Consequences:** [What this means going forward]
MD;

        $this->writeFile('design/architecture.md', $architecture);

        $pseudocode = <<<'MD'
# Pseudocode

## Main Flow

```
FUNCTION main_feature():
    // Step 1: [Description]
    result = step_one()

    // Step 2: [Description]
    IF result.success:
        step_two(result.data)
    ELSE:
        handle_error(result.error)

    // Step 3: [Description]
    RETURN finalize()
```

## Step Details

### step_one()

```
FUNCTION step_one():
    // Detailed implementation pseudocode
    validate_input()
    process_data()
    RETURN result
```

### step_two()

```
FUNCTION step_two(data):
    // Detailed implementation pseudocode
    transform(data)
    persist(data)
```

## Error Handling

```
FUNCTION handle_error(error):
    log_error(error)
    notify_if_critical(error)
    RETURN graceful_fallback()
```

## Stepwise Refinement

### Level 1 (High-level steps)
1. Receive and validate input
2. Process the request
3. Persist changes
4. Return response

### Level 2 (More detail)
1. Receive and validate input
   - Extract data from request
   - Validate format
   - Check authorization
2. Process the request
   - Apply business rules
   - Transform data
   - Validate result
3. Persist changes
   - Begin transaction
   - Write to database
   - Commit or rollback
4. Return response
   - Format response
   - Include metadata
   - Send to client
MD;

        $this->writeFile('design/pseudocode.md', $pseudocode);

        $dataFlows = <<<'MD'
# Data Flows

## Overview

<!-- Describe how data flows through the system -->

## Flow 1: [Name]

```
[Source] → [Process] → [Destination]
```

### Details

- **Input:** [What data comes in]
- **Transformation:** [What happens to it]
- **Output:** [What data goes out]

## Flow 2: [Name]

```
[Source] → [Process A] → [Process B] → [Destination]
```

### Details

- **Input:** [What data comes in]
- **Transformation:** [What happens to it]
- **Output:** [What data goes out]

## Data Structures

### Structure 1

```
{
  "field1": "type",
  "field2": "type"
}
```

### Structure 2

```
{
  "field1": "type",
  "nested": {
    "field2": "type"
  }
}
```
MD;

        $this->writeFile('design/data-flows.md', $dataFlows);
    }

    private function createContractsFiles(): void
    {
        $content = <<<'MD'
# Test Contracts

> These are specifications, not implementations. Tests should be written to satisfy these contracts.

## Contract 1: [Feature Name] - Happy Path

- **Type:** Feature
- **Priority:** High

### Preconditions

- [System is in valid state]
- [Required data exists]

### Action

```
Method: POST
Endpoint: /api/resource
Payload:
  field1: value1
  field2: value2
```

### Expectations

- **Status:** 201
- **Response contains:** [expected fields]
- **Database:** [expected state changes]
- **Side effects:** [emails, events, etc.]

### Invariants

- [Things that must always be true]

---

## Contract 2: [Feature Name] - Error Case

- **Type:** Feature
- **Priority:** High

### Preconditions

- [Invalid state or data]

### Action

```
Method: POST
Endpoint: /api/resource
Payload:
  field1: invalid_value
```

### Expectations

- **Status:** 422
- **Response contains:** error message
- **Database:** no changes
- **Side effects:** none

---

## Contract 3: [Unit Test] - Business Logic

- **Type:** Unit
- **Priority:** Medium

### Given

[Component is instantiated with dependencies]

### When

[Method is called with specific input]

### Then

- [Expected return value]
- [Expected state changes]
MD;

        $this->writeFile('contracts/test-contracts.md', $content);
    }

    private function saveMetadata(): void
    {
        $data = [
            'title' => $this->title,
            'version' => $this->version,
            'status' => $this->status,
            'feature_id' => $this->featureId,
            'created_at' => $this->metadata['created_at'] ?? $this->timestamp(),
            'updated_at' => $this->timestamp(),
            'metadata' => $this->metadata,
        ];

        $this->filesystem->dumpFile(
            $this->dirPath.'/metadata.yaml',
            Yaml::dump($data, 4, 2)
        );
    }

    private function updateIndex(): void
    {
        $files = $this->listFiles();
        $index = "# Index\n\n";
        $index .= "> Auto-generated table of contents\n\n";

        $grouped = [];
        foreach ($files as $file) {
            if ($file === 'index.md' || $file === 'metadata.yaml') {
                continue;
            }

            $parts = explode('/', $file);
            $section = count($parts) > 1 ? $parts[0] : 'root';
            $grouped[$section][] = $file;
        }

        foreach ($grouped as $section => $sectionFiles) {
            if ($section !== 'root') {
                $index .= '## '.ucfirst($section)."\n\n";
            }

            foreach ($sectionFiles as $file) {
                $name = basename($file, '.md');
                $name = str_replace('-', ' ', $name);
                $name = ucwords($name);
                $index .= "- [{$name}]({$file})\n";
            }
            $index .= "\n";
        }

        $this->filesystem->dumpFile($this->dirPath.'/index.md', $index);
    }

    private function timestamp(): string
    {
        return (new \DateTimeImmutable)->format('Y-m-d H:i:s');
    }

    private static function slugify(string $text): string
    {
        $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text) ?? '';
        $text = preg_replace('/\s+/', '-', trim($text)) ?? '';

        return strtolower($text);
    }
}
