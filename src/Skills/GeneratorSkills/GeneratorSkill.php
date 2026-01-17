<?php

declare(strict_types=1);

namespace LaraForge\Skills\GeneratorSkills;

use LaraForge\Contracts\GeneratorInterface;
use LaraForge\Project\ProjectContext;
use LaraForge\Skills\Contracts\SkillResultInterface;
use LaraForge\Skills\Skill;
use LaraForge\Skills\SkillResult;

abstract class GeneratorSkill extends Skill
{
    protected ?GeneratorInterface $generator = null;

    abstract protected function createGenerator(): GeneratorInterface;

    public function category(): string
    {
        return 'generator';
    }

    public function tags(): array
    {
        return ['code-generation', 'laravel'];
    }

    protected function getGenerator(): GeneratorInterface
    {
        if (! $this->generator) {
            $this->generator = $this->createGenerator();

            if ($this->laraforge && method_exists($this->generator, 'setLaraForge')) {
                $this->generator->setLaraForge($this->laraforge);
            }
        }

        return $this->generator;
    }

    protected function perform(array $params, ProjectContext $context): SkillResultInterface
    {
        $generator = $this->getGenerator();

        try {
            $generator->validate($params);
        } catch (\Throwable $e) {
            return SkillResult::failure(
                'Validation failed: '.$e->getMessage(),
                metadata: ['validation_error' => $e->getMessage()]
            );
        }

        try {
            $generatedFiles = $generator->generate($params);

            return SkillResult::success(
                output: $generatedFiles,
                artifacts: ['files' => $generatedFiles],
                metadata: [
                    'generator' => $generator->identifier(),
                    'files_count' => count($generatedFiles),
                ]
            );
        } catch (\Throwable $e) {
            return SkillResult::failure(
                'Generation failed: '.$e->getMessage(),
                metadata: ['exception' => $e->getMessage()]
            );
        }
    }
}
