<?php

declare(strict_types=1);

namespace WpPluginInsights\RunnerPhpCompatibility;

use InvalidArgumentException;
use RuntimeException;

class PhpCompatibilityAnalyzer
{
    private const REPORT_LIMIT = 25;
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

        $testedVersions = [];
        $lastFailure = null;

        foreach (self::supportedVersions() as $version) {
            $testedVersions[] = $version;

            $result = $this->runPhpcs($job->src, $version);
            $phpcsFindings = $this->extractFindings($result);

            if (($result['totals']['errors'] ?? 0) === 0
                && ($result['totals']['warnings'] ?? 0) === 0) {
                return [
                    'score' => [
                        'grade' => 'A+',
                    ],
                    'metrics' => [
                        'detected_min_php' => $version,
                        'tested_versions' => $testedVersions,
                        'based_on_version_scan' => true,
                        'summary' => sprintf('Lowest required PHP version: %s', $version),
                    ],                    
                ];
            }

            $lastFailure = [
                'version' => $version,
                'findings' => $phpcsFindings,
            ];
        }

        return [
            'status' => 'no-supported-version-detected',
            'detected_min_php' => null,
            'tested_versions' => $testedVersions,
            'summary' => sprintf(
                'Compatibility findings remained for all tested versions up to %s',
                end($testedVersions)
            ),
            'findings' => $lastFailure['findings'] ?? [],
            'based_on_version_scan' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runPhpcs(string $src, string $version): array
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
            $version . '-' . $version,
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
}
