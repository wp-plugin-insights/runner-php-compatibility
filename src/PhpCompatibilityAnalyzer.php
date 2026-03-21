<?php

declare(strict_types=1);

namespace WpPluginInsights\RunnerPhpCompatibility;

use InvalidArgumentException;
use RuntimeException;

class PhpCompatibilityAnalyzer
{
    private const REPORT_LIMIT = 25;
    private const README_CANDIDATES = [
        'readme.txt',
        'readme.md',
    ];
    private const VERSION_CANDIDATES = [
        '5.6',
        '7.0',
        '7.1',
        '7.2',
        '7.3',
        '7.4',
        '8.0',
        '8.1',
        '8.2',
        '8.3',
        '8.4',
        '8.5',
    ];

    /**
     * @return list<string>
     */
    public static function supportedVersions(): array
    {
        return self::VERSION_CANDIDATES;
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(Job $job): array
    {
        if (!is_dir($job->src)) {
            throw new InvalidArgumentException(sprintf('Plugin source path does not exist: %s', $job->src));
        }

        $declaredRequirement = $this->detectDeclaredPhpRequirement($job->src);
        $testedVersions = self::supportedVersions();
        $result = $this->runPhpcs($job->src, $testedVersions[0] . '-' . end($testedVersions));
        $findings = $this->extractFindings($result);
        $detectedVersion = $this->determineDetectedMinimumPhpVersion($findings);
        $score = $this->buildScore($declaredRequirement, $detectedVersion);

        return [
            'status' => 'ok',
            'score' => [
                'grade' => $score['grade'],
                'reasoning' => $score['reasoning'],
            ],
            'metrics' => [
                'detected_min_php' => $detectedVersion,
                'declared_min_php' => $declaredRequirement['version'],
                'declared_min_php_source' => $declaredRequirement['path'],
                'tested_versions' => $testedVersions,
                'based_on_version_scan' => false,
                'summary' => sprintf('Lowest required PHP version: %s', $detectedVersion),
            ],
            'issues' => [],
            'details' => [
                'declared_requirement' => $declaredRequirement,
                'blocking_findings' => $findings,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runPhpcs(string $src, string $versionConstraint): array
    {
        $phpcs = dirname(__DIR__) . '/vendor/bin/phpcs';

        if (!is_file($phpcs)) {
            throw new RuntimeException('Missing phpcs binary. Run: composer install');
        }

        $command = [
            $phpcs,
            '--standard=' . dirname(__DIR__) . '/phpcs/WPPluginInsightsCompatibility/ruleset.xml',
            '--runtime-set',
            'testVersion',
            $versionConstraint,
            '--report=json',
            '--extensions=php',
            $src,
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to launch phpcs.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($stdout === '') {
            throw new RuntimeException(trim($stderr) !== '' ? trim($stderr) : 'phpcs produced no output.');
        }

        $report = json_decode($stdout, true);

        if (!is_array($report)) {
            throw new RuntimeException('Failed to decode phpcs JSON output.');
        }

        if (!in_array($exitCode, [0, 1, 2], true)) {
            throw new RuntimeException(trim($stderr) !== '' ? trim($stderr) : 'phpcs failed unexpectedly.');
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<int, array<string, mixed>>
     */
    private function extractFindings(array $report): array
    {
        $findings = [];
        $files = $report['files'] ?? [];

        if (!is_array($files)) {
            return [];
        }

        foreach ($files as $file => $details) {
            if (!is_array($details)) {
                continue;
            }

            $messages = $details['messages'] ?? [];

            if (!is_array($messages)) {
                continue;
            }

            foreach ($messages as $message) {
                if (!is_array($message)) {
                    continue;
                }

                $findings[] = [
                    'file' => $file,
                    'line' => $message['line'] ?? null,
                    'column' => $message['column'] ?? null,
                    'type' => $message['type'] ?? null,
                    'source' => $message['source'] ?? null,
                    'message' => $message['message'] ?? null,
                    'required_version' => $this->extractRequiredVersion($message),
                ];

            }
        }

        return $this->limitFindings($findings);
    }

    /**
     * @param list<array<string, mixed>> $findings
     * @return list<array<string, mixed>>
     */
    private function limitFindings(array $findings): array
    {
        return array_slice($findings, 0, self::REPORT_LIMIT);
    }

    /**
     * @param list<array<string, mixed>> $findings
     */
    private function determineDetectedMinimumPhpVersion(array $findings): string
    {
        $detectedVersion = self::supportedVersions()[0];

        foreach ($findings as $finding) {
            $requiredVersion = $finding['required_version'] ?? null;

            if (!is_string($requiredVersion)) {
                continue;
            }

            if (version_compare($requiredVersion, $detectedVersion, '>')) {
                $detectedVersion = $requiredVersion;
            }
        }

        return $detectedVersion;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function extractRequiredVersion(array $message): ?string
    {
        $text = (string) ($message['message'] ?? '');

        if (preg_match('/PHP(?: version)? ([0-9.]+) or earlier/i', $text, $matches) !== 1) {
            return null;
        }

        return $this->nextSupportedVersion($matches[1]);
    }

    private function nextSupportedVersion(string $version): ?string
    {
        $supportedVersions = self::supportedVersions();
        $index = array_search($version, $supportedVersions, true);

        if ($index === false) {
            foreach ($supportedVersions as $supportedVersion) {
                if (version_compare($supportedVersion, $version, '>')) {
                    return $supportedVersion;
                }
            }

            return end($supportedVersions) ?: null;
        }

        return $supportedVersions[$index + 1] ?? $supportedVersions[$index] ?? null;
    }

    /**
     * @return array{path: ?string, format: ?string, version: ?string}
     */
    private function detectDeclaredPhpRequirement(string $src): array
    {
        foreach (self::README_CANDIDATES as $readmeFileName) {
            $path = $src . '/' . $readmeFileName;

            if (!is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException(sprintf('Failed to read readme file: %s', $path));
            }

            return [
                'path' => $path,
                'format' => pathinfo($path, PATHINFO_EXTENSION),
                'version' => $this->extractRequiresPhpVersion($contents),
            ];
        }

        return [
            'path' => null,
            'format' => null,
            'version' => null,
        ];
    }

    private function extractRequiresPhpVersion(string $contents): ?string
    {
        if (!preg_match('/^\\s*Requires PHP:\\s*(.+)$/mi', $contents, $matches)) {
            return null;
        }

        $value = trim($matches[1]);

        if (!preg_match('/\\d+(?:\\.\\d+)+/', $value, $versionMatch)) {
            return null;
        }

        return $versionMatch[0];
    }

    /**
     * @param array{path: ?string, format: ?string, version: ?string} $declaredRequirement
     * @return array{grade: string, reasoning: string}
     */
    private function buildScore(array $declaredRequirement, string $detectedVersion): array
    {
        $declaredVersion = $declaredRequirement['version'];

        if ($declaredVersion === null) {
            if ($declaredRequirement['path'] === null) {
                return [
                    'grade' => 'C',
                    'reasoning' => sprintf(
                        'No root-level readme.txt or readme.md declares a Requires PHP value, so the detected minimum PHP version %s cannot be compared against a plugin-declared requirement.',
                        $detectedVersion
                    ),
                ];
            }

            return [
                'grade' => 'C',
                'reasoning' => sprintf(
                    'The plugin readme at %s does not declare a Requires PHP value, so the detected minimum PHP version %s cannot be compared against a plugin-declared requirement.',
                    $declaredRequirement['path'],
                    $detectedVersion
                ),
            ];
        }

        $comparison = version_compare($declaredVersion, $detectedVersion);

        if ($comparison === 0) {
            return [
                'grade' => 'A+',
                'reasoning' => sprintf(
                    'The plugin declares Requires PHP %s and the code scan also detects %s as the lowest required PHP version.',
                    $declaredVersion,
                    $detectedVersion
                ),
            ];
        }

        if ($comparison > 0) {
            return [
                'grade' => 'A',
                'reasoning' => sprintf(
                    'The plugin declares Requires PHP %s while the code scan detects %s as the lowest required PHP version. The declaration is conservative but stricter than necessary.',
                    $declaredVersion,
                    $detectedVersion
                ),
            ];
        }

        return [
            'grade' => 'F',
            'reasoning' => sprintf(
                'The plugin declares Requires PHP %s, but the code scan detects %s as the lowest required PHP version. The declared requirement is lower than the code actually needs.',
                $declaredVersion,
                $detectedVersion
            ),
        ];
    }
}
