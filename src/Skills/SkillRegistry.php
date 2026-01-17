<?php

declare(strict_types=1);

namespace LaraForge\Skills;

use LaraForge\Contracts\LaraForgeInterface;
use LaraForge\Skills\Contracts\SkillInterface;

class SkillRegistry
{
    /**
     * @var array<string, SkillInterface>
     */
    private array $skills = [];

    public function __construct(
        private readonly ?LaraForgeInterface $laraforge = null,
    ) {}

    public function register(SkillInterface $skill): void
    {
        if ($skill instanceof Skill && $this->laraforge) {
            $skill->setLaraForge($this->laraforge);
        }

        $this->skills[$skill->identifier()] = $skill;
    }

    public function get(string $identifier): ?SkillInterface
    {
        return $this->skills[$identifier] ?? null;
    }

    public function has(string $identifier): bool
    {
        return isset($this->skills[$identifier]);
    }

    /**
     * @return array<string, SkillInterface>
     */
    public function all(): array
    {
        return $this->skills;
    }

    /**
     * Get skills by category.
     *
     * @return array<string, SkillInterface>
     */
    public function byCategory(string $category): array
    {
        return array_filter(
            $this->skills,
            fn (SkillInterface $skill) => $skill->category() === $category
        );
    }

    /**
     * Get skills by tag.
     *
     * @return array<string, SkillInterface>
     */
    public function byTag(string $tag): array
    {
        return array_filter(
            $this->skills,
            fn (SkillInterface $skill) => in_array($tag, $skill->tags(), true)
        );
    }

    /**
     * Get all categories.
     *
     * @return array<string>
     */
    public function categories(): array
    {
        $categories = [];
        foreach ($this->skills as $skill) {
            $categories[$skill->category()] = true;
        }

        return array_keys($categories);
    }

    /**
     * Get all tags.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        $tags = [];
        foreach ($this->skills as $skill) {
            foreach ($skill->tags() as $tag) {
                $tags[$tag] = true;
            }
        }

        return array_keys($tags);
    }

    /**
     * Search skills by name or description.
     *
     * @return array<string, SkillInterface>
     */
    public function search(string $query): array
    {
        $query = strtolower($query);

        return array_filter(
            $this->skills,
            function (SkillInterface $skill) use ($query) {
                return str_contains(strtolower($skill->name()), $query)
                    || str_contains(strtolower($skill->description()), $query)
                    || str_contains(strtolower($skill->identifier()), $query);
            }
        );
    }

    /**
     * Get skill metadata for all registered skills.
     *
     * @return array<string, array{identifier: string, name: string, description: string, category: string, tags: array}>
     */
    public function metadata(): array
    {
        $metadata = [];
        foreach ($this->skills as $identifier => $skill) {
            $metadata[$identifier] = [
                'identifier' => $skill->identifier(),
                'name' => $skill->name(),
                'description' => $skill->description(),
                'category' => $skill->category(),
                'tags' => $skill->tags(),
                'parameters' => $skill->parameters(),
            ];
        }

        return $metadata;
    }

    public function remove(string $identifier): void
    {
        unset($this->skills[$identifier]);
    }

    public function count(): int
    {
        return count($this->skills);
    }
}
