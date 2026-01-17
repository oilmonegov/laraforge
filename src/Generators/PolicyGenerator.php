<?php

declare(strict_types=1);

namespace LaraForge\Generators;

use LaraForge\Support\Generator;
use LaraForge\Support\TddAwareGenerator;

final class PolicyGenerator extends Generator
{
    use TddAwareGenerator;

    public const STANDARD_ABILITIES = [
        'viewAny' => [
            'description' => 'view any models',
            'noModel' => true,
        ],
        'view' => [
            'description' => 'view the model',
            'noModel' => false,
        ],
        'create' => [
            'description' => 'create models',
            'noModel' => true,
        ],
        'update' => [
            'description' => 'update the model',
            'noModel' => false,
        ],
        'delete' => [
            'description' => 'delete the model',
            'noModel' => false,
        ],
        'restore' => [
            'description' => 'restore the model',
            'noModel' => false,
        ],
        'forceDelete' => [
            'description' => 'permanently delete the model',
            'noModel' => false,
        ],
    ];

    public const DEFAULT_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete'];

    public function identifier(): string
    {
        return 'policy';
    }

    public function name(): string
    {
        return 'Policy';
    }

    public function description(): string
    {
        return 'Generates Laravel Policy classes for authorization';
    }

    public function supportsTdd(): bool
    {
        return true;
    }

    public function options(): array
    {
        return [
            'model' => [
                'type' => 'string',
                'description' => 'The model name',
                'required' => true,
            ],
            'abilities' => [
                'type' => 'array',
                'description' => 'Abilities to include (viewAny, view, create, update, delete, restore, forceDelete)',
                'required' => false,
                'default' => self::DEFAULT_ABILITIES,
            ],
            'user_model' => [
                'type' => 'string',
                'description' => 'The user model class',
                'required' => false,
                'default' => 'User',
            ],
            'style' => [
                'type' => 'string',
                'description' => 'Implementation style: "regular" or "tdd"',
                'required' => false,
                'default' => 'regular',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    public function generate(array $options = []): array
    {
        $model = $this->studly($options['model']);
        $abilities = $options['abilities'] ?? self::DEFAULT_ABILITIES;
        $userModel = $this->studly($options['user_model'] ?? 'User');

        if ($this->isTddMode($options)) {
            return $this->generateWithTdd($options);
        }

        return $this->generatePolicy($model, $abilities, $userModel);
    }

    /**
     * Generate policy class.
     *
     * @param  array<string>  $abilities
     * @return array<string>
     */
    private function generatePolicy(string $model, array $abilities, string $userModel): array
    {
        $className = "{$model}Policy";
        $modelVariable = $this->camel($model);

        $abilityData = $this->buildAbilityData($abilities, $model, $userModel, $modelVariable);

        // Split abilities into those with and without model parameters
        $abilitiesNoModel = array_filter($abilityData, fn ($a) => $a['noModel']);
        $abilitiesWithModel = array_filter($abilityData, fn ($a) => ! $a['noModel']);

        $content = $this->renderStub('policy', [
            'className' => $className,
            'modelClass' => $model,
            'modelVariable' => $modelVariable,
            'userClass' => $userModel,
            'abilitiesNoModel' => array_values($abilitiesNoModel),
            'abilitiesWithModel' => array_values($abilitiesWithModel),
        ]);

        $path = $this->writeFile("app/Policies/{$className}.php", $content);

        return [$path];
    }

    /**
     * Build ability data for template.
     *
     * @param  array<string>  $abilities
     * @return array<array<string, mixed>>
     */
    private function buildAbilityData(
        array $abilities,
        string $model,
        string $userModel,
        string $modelVariable,
    ): array {
        $abilityData = [];

        foreach ($abilities as $ability) {
            $abilityConfig = self::STANDARD_ABILITIES[$ability] ?? null;

            if ($abilityConfig !== null) {
                $noModel = $abilityConfig['noModel'];
                $abilityData[] = [
                    'name' => $ability,
                    'description' => $abilityConfig['description'],
                    'noModel' => $noModel,
                    'hasModel' => ! $noModel,
                    'modelClass' => $model,
                    'modelVariable' => $modelVariable,
                    'userClass' => $userModel,
                ];
            } else {
                // Custom ability - assume it requires the model
                $abilityData[] = [
                    'name' => $ability,
                    'description' => "{$ability} the model",
                    'noModel' => false,
                    'hasModel' => true,
                    'modelClass' => $model,
                    'modelVariable' => $modelVariable,
                    'userClass' => $userModel,
                ];
            }
        }

        return $abilityData;
    }

    /**
     * Generate test files for TDD mode.
     *
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    protected function generateTests(array $options): array
    {
        $model = $this->studly($options['model']);
        $abilities = $options['abilities'] ?? self::DEFAULT_ABILITIES;
        $userModel = $this->studly($options['user_model'] ?? 'User');

        $className = "{$model}Policy";
        $modelVariable = $this->camel($model);

        $abilityData = $this->buildAbilityData($abilities, $model, $userModel, $modelVariable);

        // Split abilities into those with and without model parameters
        $abilitiesNoModel = array_filter($abilityData, fn ($a) => $a['noModel']);
        $abilitiesWithModel = array_filter($abilityData, fn ($a) => ! $a['noModel']);

        $content = $this->renderStub('policy-test', [
            'className' => $className,
            'modelClass' => $model,
            'modelVariable' => $modelVariable,
            'userClass' => $userModel,
            'abilitiesNoModel' => array_values($abilitiesNoModel),
            'abilitiesWithModel' => array_values($abilitiesWithModel),
        ]);

        $path = $this->writeFile("tests/Unit/Policies/{$className}Test.php", $content);

        return [$path];
    }

    /**
     * Generate implementation files for TDD mode.
     *
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    protected function generateImplementation(array $options): array
    {
        $model = $this->studly($options['model']);
        $abilities = $options['abilities'] ?? self::DEFAULT_ABILITIES;
        $userModel = $this->studly($options['user_model'] ?? 'User');

        return $this->generatePolicy($model, $abilities, $userModel);
    }
}
