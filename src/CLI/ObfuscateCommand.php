<?php

declare(strict_types=1);

namespace ISerter\PhpObfuscator\CLI;

use ISerter\PhpObfuscator\Config\ConfigLoader;
use ISerter\PhpObfuscator\Config\Configuration;
use ISerter\PhpObfuscator\FileProcessor\DirectoryProcessor;
use ISerter\PhpObfuscator\FileProcessor\FileProcessor;
use ISerter\PhpObfuscator\Obfuscator\ObfuscationContext;
use ISerter\PhpObfuscator\Obfuscator\Obfuscator;
use ISerter\PhpObfuscator\Parser\ParserFactory;
use ISerter\PhpObfuscator\Printer\ObfuscatedPrinter;
use ISerter\PhpObfuscator\Scrambler\ScramblerFactory;
use ISerter\PhpObfuscator\Transformer\CommentStripper;
use ISerter\PhpObfuscator\Transformer\ControlFlowFlattener;
use ISerter\PhpObfuscator\Transformer\IdentifierScrambler;
use ISerter\PhpObfuscator\Transformer\StatementShuffler;
use ISerter\PhpObfuscator\Transformer\StringEncoder;
use ISerter\PhpObfuscator\Transformer\SymbolCollector;
use ISerter\PhpObfuscator\Transformer\TransformerPipeline;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

final class ObfuscateCommand extends Command
{
    /** @var string|null */
    protected static $defaultName = 'obfuscate';
    /** @var string|null */
    protected static $defaultDescription = 'Obfuscate PHP source code';

    protected function configure(): void
    {
        $this
            ->setName('obfuscate')
            ->addArgument('source', InputArgument::REQUIRED, 'PHP file or directory to obfuscate')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file or directory')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config YAML file')
            ->addOption('clean', null, InputOption::VALUE_NONE, 'Remove output directory contents before processing')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Re-obfuscate all files (ignore timestamps)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be processed without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $source = $input->getArgument('source');
        $outputPath = $input->getOption('output');
        $configFile = $input->getOption('config');
        $clean = $input->getOption('clean');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        if (!$outputPath) {
            $io->error('Output path is required. Use -o or --output.');
            return 2;
        }

        if (!file_exists($source)) {
            $io->error(sprintf('Source path "%s" does not exist.', $source));
            return 2;
        }

        try {
            $configLoader = new ConfigLoader();
            $config = $configLoader->load($configFile);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return 2;
        }

        $scramblerFactory = new ScramblerFactory();
        $scrambler = $scramblerFactory->createScrambler($config->scramblerMode, $config->scramblerMinLength);
        $context = new ObfuscationContext($config, $scrambler);

        $pipeline = $this->createPipeline($config);
        $obfuscator = new Obfuscator(new ParserFactory(), $pipeline, new ObfuscatedPrinter());
        $fileProcessor = new FileProcessor($obfuscator);

        if (is_file($source)) {
            $this->processFile($source, $outputPath, $context, $fileProcessor, $io, $dryRun);
        } else {
            $this->processDirectory($source, $outputPath, $context, $fileProcessor, $io, $dryRun, $force, $clean);
        }

        if ($context->warningRegistry->hasWarnings()) {
            $io->section('Warnings:');
            foreach ($context->warningRegistry->getWarnings() as $file => $messages) {
                foreach ($messages as $message) {
                    $io->warning(sprintf('%s: %s', $file, $message));
                }
            }
        }

        return 0;
    }

    private function createPipeline(Configuration $config): TransformerPipeline
    {
        $pipeline = new TransformerPipeline();
        $pipeline->addTransformer(new SymbolCollector());

        if ($config->stripComments) {
            $pipeline->addTransformer(new CommentStripper());
        }

        if ($config->scrambleIdentifiers) {
            $pipeline->addTransformer(new IdentifierScrambler());
        }

        if ($config->encodeStrings) {
            $pipeline->addTransformer(new StringEncoder());
        }

        if ($config->flattenControlFlow) {
            $pipeline->addTransformer(new ControlFlowFlattener());
        }

        if ($config->shuffleStatements) {
            $pipeline->addTransformer(new StatementShuffler());
        }

        return $pipeline;
    }

    private function processFile(
        string $source,
        string $outputPath,
        ObfuscationContext $context,
        FileProcessor $fileProcessor,
        SymfonyStyle $io,
        bool $dryRun
    ): void {
        if ($dryRun) {
            $io->info(sprintf('[Dry-run] Would obfuscate file: %s -> %s', $source, $outputPath));
            return;
        }

        try {
            $context->currentFilePath = basename($source);
            $fileProcessor->obfuscateFile($source, $outputPath, $context);
            $io->success(sprintf('Obfuscated: %s -> %s', $source, $outputPath));
        } catch (\Exception $e) {
            $io->error(sprintf('Error processing file "%s": %s', $source, $e->getMessage()));
        }
    }

    private function processDirectory(
        string $source,
        string $outputPath,
        ObfuscationContext $context,
        FileProcessor $fileProcessor,
        SymfonyStyle $io,
        bool $dryRun,
        bool $force,
        bool $clean
    ): void {
        if ($dryRun) {
            $finder = new Finder();
            $finder->files()->in($source);
            $count = $finder->count();
            $io->info(sprintf('[Dry-run] Would process %d files in directory: %s', $count, $source));
            return;
        }

        $directoryProcessor = new DirectoryProcessor(
            $fileProcessor,
            new ParserFactory(),
            new SymbolCollector()
        );

        $io->section('Obfuscating directory...');

        $progressBar = null;

        $onProgress = function (int $total, string $message) use ($io, &$progressBar) {
            if ($progressBar === null) {
                $progressBar = new ProgressBar($io, $total * 2); // 2 passes
                $progressBar->start();
            }
            $progressBar->setMessage($message);
            $progressBar->advance();
        };

        try {
            $summary = $directoryProcessor->process($source, $outputPath, $context, $force, $clean, $onProgress);
            if ($progressBar) {
                $progressBar->finish();
                $io->newLine(2);
            }

            $io->table(
                ['Files Processed', 'Files Skipped', 'Errors'],
                [[$summary->getProcessedCount(), $summary->getSkippedCount(), count($summary->getErrors())]]
            );

            if ($summary->hasErrors()) {
                $io->section('Errors encountered:');
                foreach ($summary->getErrors() as $error) {
                    $io->error($error);
                }
            } else {
                $io->success(sprintf('Successfully processed directory: %s -> %s', $source, $outputPath));
            }
        } catch (\Exception $e) {
            if ($progressBar) {
                $progressBar->finish();
                $io->newLine(2);
            }
            $io->error(sprintf('Error processing directory: %s', $e->getMessage()));
        }
    }
}
