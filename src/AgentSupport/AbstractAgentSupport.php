<?php

declare(strict_types=1);

namespace LaraForge\AgentSupport;

use LaraForge\AgentSupport\Contracts\AgentSupportInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Abstract Agent Support
 *
 * Base class providing common functionality for agent supports.
 */
abstract class AbstractAgentSupport implements AgentSupportInterface
{
    protected Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem;
    }

    public function isInstalled(string $projectPath): bool
    {
        // Check for root files
        foreach ($this->getRootFiles() as $file) {
            if ($this->filesystem->exists($projectPath.'/'.$file)) {
                return true;
            }
        }

        // Check for agent directory
        $agentDir = $this->getAgentDirectory($projectPath);

        return $this->filesystem->exists($agentDir);
    }

    public function priority(): int
    {
        return 50; // Default priority
    }

    /**
     * Get the agent-specific directory under .laraforge/agents/
     */
    protected function getAgentDirectory(string $projectPath): string
    {
        return $projectPath.'/.laraforge/agents/'.$this->identifier();
    }

    /**
     * Get the shared documentation directory.
     */
    protected function getDocsDirectory(string $projectPath): string
    {
        return $projectPath.'/.laraforge/docs';
    }

    /**
     * Ensure the agent directory exists.
     */
    protected function ensureAgentDirectory(string $projectPath): string
    {
        $dir = $this->getAgentDirectory($projectPath);
        $this->filesystem->mkdir($dir);

        return $dir;
    }

    /**
     * Load project context from .laraforge/config.yaml
     *
     * @return array<string, mixed>
     */
    protected function loadProjectContext(string $projectPath): array
    {
        $configPath = $projectPath.'/.laraforge/config.yaml';

        if (! $this->filesystem->exists($configPath)) {
            return [
                'project' => ['name' => basename($projectPath), 'description' => ''],
                'framework' => 'laravel',
            ];
        }

        $content = file_get_contents($configPath);

        /** @var array<string, mixed> */
        return $content !== false ? (Yaml::parse($content) ?? []) : [];
    }

    /**
     * Get paths to shared documentation files.
     *
     * @return array<string, string|null>
     */
    protected function getDocumentationPaths(string $projectPath): array
    {
        $docsDir = $this->getDocsDirectory($projectPath);

        $docs = [
            'prd' => null,
            'frd' => null,
            'tech_spec' => null,
            'acceptance_criteria' => null,
            'tech_stack' => null,
            'lessons_learned' => null,
        ];

        $patterns = [
            'prd' => ['prd.md', 'prd.yaml', 'PRD.md'],
            'frd' => ['frd.yaml', 'frd.md', 'FRD.yaml'],
            'tech_spec' => ['tech-spec.md', 'technical-spec.md', 'spec.md'],
            'acceptance_criteria' => ['acceptance-criteria.yaml', 'criteria.yaml'],
            'tech_stack' => ['tech-stack.yaml', 'stack.yaml'],
            'lessons_learned' => ['lessons-learned.md', 'retrospective.md'],
        ];

        foreach ($patterns as $key => $files) {
            foreach ($files as $file) {
                $path = $docsDir.'/'.$file;
                if ($this->filesystem->exists($path)) {
                    $docs[$key] = '.laraforge/docs/'.$file;
                    break;
                }
            }
        }

        // Also check root .laraforge directory
        $rootLaraforge = $projectPath.'/.laraforge';
        if ($docs['prd'] === null && $this->filesystem->exists($rootLaraforge.'/PRD.md')) {
            $docs['prd'] = '.laraforge/PRD.md';
        }

        return $docs;
    }

    /**
     * Get all criteria files.
     *
     * @return array<string>
     */
    protected function getCriteriaFiles(string $projectPath): array
    {
        $criteriaDir = $projectPath.'/.laraforge/criteria';
        $files = [];

        if ($this->filesystem->exists($criteriaDir)) {
            foreach (glob($criteriaDir.'/*.yaml') ?: [] as $file) {
                $files[] = '.laraforge/criteria/'.basename($file);
            }
        }

        return $files;
    }

    /**
     * Write a file and track it.
     *
     * @param  array<string>  $createdFiles
     */
    protected function writeFile(string $path, string $content, array &$createdFiles): void
    {
        $dir = dirname($path);
        if (! $this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $this->filesystem->dumpFile($path, $content);
        $createdFiles[] = $path;
    }

    /**
     * Remove a file if it exists.
     *
     * @param  array<string>  $removedFiles
     */
    protected function removeFile(string $path, array &$removedFiles): void
    {
        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
            $removedFiles[] = $path;
        }
    }

    /**
     * Format a list of documentation references for the agent config.
     *
     * @param  array<string, string|null>  $docs
     */
    protected function formatDocReferences(array $docs): string
    {
        $lines = [];

        foreach ($docs as $type => $path) {
            if ($path !== null) {
                $label = str_replace('_', ' ', ucfirst($type));
                $lines[] = "- **{$label}**: [{$path}]({$path})";
            }
        }

        return empty($lines) ? 'No documentation files found yet.' : implode("\n", $lines);
    }

    /**
     * Get the current date in ISO format.
     */
    protected function currentDate(): string
    {
        return date('Y-m-d');
    }

    /**
     * Get the current timestamp in ISO format.
     */
    protected function currentTimestamp(): string
    {
        return date('c');
    }
}
