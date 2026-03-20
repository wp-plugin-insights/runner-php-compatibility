<?php

declare(strict_types=1);

namespace WpPluginInsights\RunnerPhpCompatibility;

use InvalidArgumentException;
use Throwable;

class JobProcessor
{
    private readonly PhpCompatibilityAnalyzer $analyzer;

    public function __construct(
        private readonly Config $config
    ) {
        $this->analyzer = new PhpCompatibilityAnalyzer();
    }

    /**
     * @return array<string, mixed>
     */
    public function process(string $body): array
    {
        $receivedAt = gmdate(DATE_ATOM);
        $job = $this->parseJob($body);

        return [
            'runner' => $this->config->runnerName,
            'plugin' => $job->plugin,
            'src' => $job->src,
            'report' => $this->analyzer->analyze($job),
            'received_at' => $receivedAt,
            'completed_at' => gmdate(DATE_ATOM),
        ];
    }

    private function parseJob(string $body): Job
    {
        try {
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Message body is not valid JSON.', previous: $exception);
        }

        if (!is_array($payload)) {
            throw new InvalidArgumentException('Message body must decode to a JSON object.');
        }

        return Job::fromArray($payload);
    }
}
