<?php

declare(strict_types=1);

namespace WpPluginInsights\RunnerPhpCompatibility;

use Dotenv\Dotenv;

class Config
{
    public function __construct(
        public readonly string $rabbitMqHost,
        public readonly int $rabbitMqPort,
        public readonly string $rabbitMqUser,
        public readonly string $rabbitMqPassword,
        public readonly string $rabbitMqVhost,
        public readonly string $inputQueue,
        public readonly string $reportExchange,
        public readonly string $runnerName,
        public readonly string $runnerCategory = 'compatibility'
    ) {
    }

    public static function fromEnvironment(): self
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->safeLoad();

        return new self(
            rabbitMqHost: self::env('RABBITMQ_HOST', '127.0.0.1'),
            rabbitMqPort: (int) self::env('RABBITMQ_PORT', '5672'),
            rabbitMqUser: self::env('RABBITMQ_USER', 'guest'),
            rabbitMqPassword: self::env('RABBITMQ_PASSWORD', 'guest'),
            rabbitMqVhost: self::env('RABBITMQ_VHOST', '/'),
            inputQueue: self::env('RABBITMQ_INPUT_QUEUE', 'plugin.analysis.runner-php-compatibility'),
            reportExchange: self::env('RABBITMQ_REPORT_EXCHANGE', 'plugin.analysis.reports'),
            runnerName: self::env('RUNNER_NAME', 'runner-php-compatibility'),
            runnerCategory: self::env('RUNNER_CATEGORY', 'compatibility')
        );
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}
