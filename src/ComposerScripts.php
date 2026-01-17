<?php

declare(strict_types=1);

namespace LaraForge;

use LaraForge\Generators\GitHooksGenerator;

/**
 * Composer script handlers for LaraForge.
 *
 * Add these scripts to your composer.json:
 *
 * "scripts": {
 *     "post-install-cmd": [
 *         "LaraForge\\ComposerScripts::postInstall"
 *     ],
 *     "post-update-cmd": [
 *         "LaraForge\\ComposerScripts::postUpdate"
 *     ]
 * }
 */
final class ComposerScripts
{
    /**
     * Handle post-install-cmd event.
     *
     * @param  \Composer\Script\Event  $event
     */
    public static function postInstall(mixed $event): void
    {
        self::installHooks($event);
    }

    /**
     * Handle post-update-cmd event.
     *
     * @param  \Composer\Script\Event  $event
     */
    public static function postUpdate(mixed $event): void
    {
        self::installHooks($event);
    }

    /**
     * Install git hooks if configured.
     *
     * @param  \Composer\Script\Event  $event
     */
    public static function installHooks(mixed $event): void
    {
        $io = $event->getIO();
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $projectDir = dirname((string) $vendorDir);

        // Check if we're in a git repository
        if (! is_dir($projectDir.'/.git')) {
            return;
        }

        // Check for laraforge configuration
        $configFile = self::findConfigFile($projectDir);
        if ($configFile === null) {
            return;
        }

        // Check if hooks auto-install is enabled
        $config = self::loadConfig($configFile);
        $autoInstall = $config['hooks']['auto_install'] ?? false;

        if (! $autoInstall) {
            return;
        }

        $io->write('<info>LaraForge:</info> Installing git hooks...');

        try {
            $laraforge = new LaraForge($projectDir);
            $generator = new GitHooksGenerator($laraforge);

            $hooksConfig = $config['hooks'] ?? [];
            $hooks = $hooksConfig['enabled'] ?? ['pre-commit', 'commit-msg'];
            $directory = $hooksConfig['directory'] ?? '.githooks';

            $generatedFiles = $generator->generate([
                'hooks' => $hooks,
                'directory' => $directory,
                'configure_git' => true,
            ]);

            foreach ($generatedFiles as $file) {
                $relativePath = str_replace($projectDir.'/', '', $file);
                $io->write("  - Created: {$relativePath}");
            }

            $io->write('<info>LaraForge:</info> Git hooks installed successfully!');
        } catch (\Throwable $e) {
            $io->writeError('<error>LaraForge:</error> Failed to install hooks: '.$e->getMessage());
        }
    }

    private static function findConfigFile(string $projectDir): ?string
    {
        $configFiles = [
            $projectDir.'/laraforge.yaml',
            $projectDir.'/laraforge.yml',
            $projectDir.'/.laraforge/config.yaml',
            $projectDir.'/.laraforge/config.yml',
        ];

        foreach ($configFiles as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(string $configFile): array
    {
        $extension = pathinfo($configFile, PATHINFO_EXTENSION);

        if (in_array($extension, ['yaml', 'yml'], true)) {
            return \Symfony\Component\Yaml\Yaml::parseFile($configFile) ?? [];
        }

        if ($extension === 'json') {
            $content = file_get_contents($configFile);

            return json_decode($content ?: '{}', true) ?? [];
        }

        return [];
    }
}
