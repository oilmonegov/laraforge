<?php

declare(strict_types=1);

namespace LaraForge\Commands;

use LaraForge\Project\ProgressTracker;
use LaraForge\Project\ProjectState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'status',
    description: 'Show project and feature status',
)]
class StatusCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('feature', 'f', InputOption::VALUE_OPTIONAL, 'Show specific feature')
            ->addOption('board', 'b', InputOption::VALUE_NONE, 'Show kanban-style board')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workingDir = $this->laraforge->workingDirectory();
        $state = ProjectState::load($workingDir);

        if (! $state) {
            warning('No LaraForge project found. Run `laraforge init` or `laraforge feature:start` first.');

            return self::FAILURE;
        }

        if ($input->getOption('json')) {
            $output->writeln(json_encode($state->toArray(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $tracker = new ProgressTracker($state);

        // Project summary
        $output->writeln('');
        info("Project: {$state->name()} v{$state->version()}");

        // Show board view
        if ($input->getOption('board')) {
            $this->showBoard($output, $tracker);

            return self::SUCCESS;
        }

        // Show specific feature
        $featureId = $input->getOption('feature');
        if ($featureId) {
            $this->showFeature($output, $state, $featureId);

            return self::SUCCESS;
        }

        // Show overall progress
        $progress = $tracker->projectProgress();
        $output->writeln('');
        $output->writeln("<comment>Overall Progress:</comment> {$progress['average_progress']}%");
        $output->writeln("<comment>Total Features:</comment> {$progress['total_features']}");

        // Status breakdown
        $output->writeln('');
        $output->writeln('<comment>By Status:</comment>');
        foreach ($progress['by_status'] as $status => $count) {
            if ($count > 0) {
                $output->writeln("  {$status}: {$count}");
            }
        }

        // Feature list
        $features = $tracker->featureSummary();
        if (! empty($features)) {
            $output->writeln('');
            $output->writeln('<comment>Features:</comment>');

            $table = new Table($output);
            $table->setHeaders(['ID', 'Title', 'Status', 'Phase', 'Progress', 'Assignee']);

            foreach ($features as $f) {
                $table->addRow([
                    substr($f['id'], 0, 15),
                    substr($f['title'], 0, 30),
                    $f['status'],
                    $f['phase'],
                    $f['progress'].'%',
                    $f['assignee'] ?? '-',
                ]);
            }

            $table->render();
        }

        // Current feature
        $current = $state->currentFeature();
        if ($current) {
            $output->writeln('');
            $output->writeln("<comment>Current Feature:</comment> {$current->title()} ({$current->phase()})");
        }

        // Needs attention
        $attention = $tracker->needsAttention();
        if (! empty($attention)) {
            $output->writeln('');
            warning('Needs Attention:');
            foreach ($attention as $item) {
                $output->writeln("  - {$item['feature']->title()}: {$item['reason']}");
            }
        }

        return self::SUCCESS;
    }

    private function showBoard(OutputInterface $output, ProgressTracker $tracker): void
    {
        $board = $tracker->statusBoard();

        foreach ($board as $column => $items) {
            $output->writeln('');
            $output->writeln('<comment>'.strtoupper($column).' ('.count($items).')</comment>');
            $output->writeln(str_repeat('-', 40));

            if (empty($items)) {
                $output->writeln('  (empty)');

                continue;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    // Backlog item
                    $output->writeln("  □ {$item['title']}");
                } else {
                    // Feature
                    $output->writeln("  ■ {$item->title()} ({$item->progress()}%)");
                }
            }
        }
    }

    private function showFeature(OutputInterface $output, ProjectState $state, string $featureId): void
    {
        $feature = $state->feature($featureId);

        if (! $feature) {
            // Try partial match
            foreach ($state->features() as $f) {
                if (str_contains($f->id(), $featureId) || str_contains(strtolower($f->title()), strtolower($featureId))) {
                    $feature = $f;
                    break;
                }
            }
        }

        if (! $feature) {
            $output->writeln("<error>Feature not found: {$featureId}</error>");

            return;
        }

        $output->writeln('');
        $output->writeln("<info>Feature: {$feature->title()}</info>");
        $output->writeln('');
        $output->writeln("<comment>ID:</comment> {$feature->id()}");
        $output->writeln("<comment>Status:</comment> {$feature->status()}");
        $output->writeln("<comment>Phase:</comment> {$feature->phase()}");
        $output->writeln("<comment>Progress:</comment> {$feature->progress()}%");
        $output->writeln("<comment>Priority:</comment> {$feature->priority()}");

        if ($feature->branch()) {
            $output->writeln("<comment>Branch:</comment> {$feature->branch()}");
        }

        if ($feature->assignee()) {
            $output->writeln("<comment>Assignee:</comment> {$feature->assignee()}");
        }

        if ($feature->description()) {
            $output->writeln('');
            $output->writeln('<comment>Description:</comment>');
            $output->writeln($feature->description());
        }

        $docs = $feature->documents();
        if (! empty($docs)) {
            $output->writeln('');
            $output->writeln('<comment>Documents:</comment>');
            foreach ($docs as $type => $path) {
                $output->writeln("  {$type}: {$path}");
            }
        }

        if (! empty($feature->tags())) {
            $output->writeln('');
            $output->writeln('<comment>Tags:</comment> '.implode(', ', $feature->tags()));
        }
    }
}
