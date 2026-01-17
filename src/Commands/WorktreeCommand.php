<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Project\ProjectState;
use LaraForge\Worktree\WorktreeManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'worktree',
    description: 'Manage git worktrees for parallel agent work',
)]
class WorktreeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list, create, merge, remove, cleanup', 'list')
            ->addOption('feature', 'f', InputOption::VALUE_OPTIONAL, 'Feature ID')
            ->addOption('agent', 'a', InputOption::VALUE_OPTIONAL, 'Agent ID')
            ->addOption('session', 's', InputOption::VALUE_OPTIONAL, 'Session ID')
            ->addOption('target', 't', InputOption::VALUE_OPTIONAL, 'Target branch for merge');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $state = ProjectState::load($workingDir);

        if (! $state) {
            warning('No LaraForge project found.');

            return self::FAILURE;
        }

        $worktreesDir = $workingDir.'/.laraforge/worktrees';
        $manager = new WorktreeManager($workingDir, $worktreesDir);

        $action = $input->getArgument('action');

        return match ($action) {
            'list' => $this->listWorktrees($output, $manager),
            'create' => $this->createWorktree($input, $output, $manager, $state),
            'merge' => $this->mergeWorktree($input, $output, $manager),
            'remove', 'abandon' => $this->removeWorktree($input, $output, $manager),
            'cleanup' => $this->cleanupWorktrees($output, $manager),
            default => $this->showHelp($output),
        };
    }

    private function listWorktrees(OutputInterface $output, WorktreeManager $manager): int
    {
        $sessions = $manager->activeSessions();

        if (empty($sessions)) {
            info('No active worktree sessions.');
            $output->writeln('');
            $output->writeln('Create one with: <info>laraforge worktree create --feature=<id> --agent=<id></info>');

            return self::SUCCESS;
        }

        $output->writeln('');
        info('Active Worktree Sessions:');

        $table = new Table($output);
        $table->setHeaders(['Session ID', 'Feature', 'Agent', 'Branch', 'Status', 'Path']);

        foreach ($sessions as $session) {
            $table->addRow([
                substr($session->id(), 0, 20),
                $session->featureId(),
                $session->agentId(),
                $session->branch(),
                $session->status(),
                $session->path(),
            ]);
        }

        $table->render();

        // Also show git worktrees
        $output->writeln('');
        $output->writeln('<comment>All Git Worktrees:</comment>');
        $worktrees = $manager->listWorktrees();
        foreach ($worktrees as $wt) {
            $output->writeln("  {$wt['path']} -> {$wt['branch']}");
        }

        return self::SUCCESS;
    }

    private function createWorktree(
        InputInterface $input,
        OutputInterface $output,
        WorktreeManager $manager,
        ProjectState $state,
    ): int {
        $featureId = $input->getOption('feature');
        $agentId = $input->getOption('agent');

        if (! $featureId) {
            $current = $state->currentFeature();
            if ($current) {
                $featureId = $current->id();
            } else {
                $featureId = text(
                    label: 'Feature ID',
                    required: true,
                );
            }
        }

        if (! $agentId) {
            $agentId = text(
                label: 'Agent ID',
                placeholder: 'dev-1',
                required: true,
            );
        }

        try {
            $session = $manager->createSession($featureId, $agentId);

            info("Created worktree session: {$session->id()}");
            $output->writeln('');
            $output->writeln("<comment>Path:</comment> {$session->path()}");
            $output->writeln("<comment>Branch:</comment> {$session->branch()}");
            $output->writeln('');
            $output->writeln("Work in the worktree directory: <info>cd {$session->path()}</info>");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to create worktree: {$e->getMessage()}</error>");

            return self::FAILURE;
        }
    }

    private function mergeWorktree(
        InputInterface $input,
        OutputInterface $output,
        WorktreeManager $manager,
    ): int {
        $sessionId = $input->getOption('session');
        $targetBranch = $input->getOption('target');

        if (! $sessionId) {
            $sessionId = text(
                label: 'Session ID to merge',
                required: true,
            );
        }

        $session = $manager->getSession($sessionId);
        if (! $session) {
            $output->writeln("<error>Session not found: {$sessionId}</error>");

            return self::FAILURE;
        }

        // Complete the session first
        $manager->completeSession($sessionId);

        // Merge it
        $result = $manager->mergeSession($sessionId, $targetBranch);

        if ($result->isSuccess()) {
            info("Successfully merged session {$sessionId}");
            $output->writeln("<comment>Commit:</comment> {$result->commitHash()}");
            $output->writeln("<comment>Target:</comment> {$result->targetBranch()}");

            return self::SUCCESS;
        }

        $output->writeln("<error>Merge failed: {$result->error()}</error>");

        if ($result->hasConflicts()) {
            $output->writeln('');
            warning('Conflicts detected:');
            foreach ($result->conflicts() as $conflict) {
                $output->writeln("  - {$conflict->filePath()}: {$conflict->description()}");
            }
        }

        return self::FAILURE;
    }

    private function removeWorktree(
        InputInterface $input,
        OutputInterface $output,
        WorktreeManager $manager,
    ): int {
        $sessionId = $input->getOption('session');

        if (! $sessionId) {
            $sessionId = text(
                label: 'Session ID to remove/abandon',
                required: true,
            );
        }

        $manager->abandonSession($sessionId);
        info("Abandoned session: {$sessionId}");

        return self::SUCCESS;
    }

    private function cleanupWorktrees(OutputInterface $output, WorktreeManager $manager): int
    {
        $cleaned = $manager->cleanup(7);

        if ($cleaned > 0) {
            info("Cleaned up {$cleaned} stale worktree(s)");
        } else {
            info('No stale worktrees to clean up');
        }

        return self::SUCCESS;
    }

    private function showHelp(OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<comment>Worktree Commands:</comment>');
        $output->writeln('');
        $output->writeln('  <info>laraforge worktree list</info>                    List all active sessions');
        $output->writeln('  <info>laraforge worktree create -f <feature> -a <agent></info>  Create new worktree');
        $output->writeln('  <info>laraforge worktree merge -s <session></info>      Merge session back');
        $output->writeln('  <info>laraforge worktree remove -s <session></info>     Abandon session');
        $output->writeln('  <info>laraforge worktree cleanup</info>                 Clean stale worktrees');

        return self::SUCCESS;
    }
}
