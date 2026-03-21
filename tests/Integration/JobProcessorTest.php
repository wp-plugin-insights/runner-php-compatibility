<?php

declare(strict_types=1);

namespace WpPluginInsights\RunnerPhpCompatibility\Tests\Integration;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WpPluginInsights\RunnerPhpCompatibility\Config;
use WpPluginInsights\RunnerPhpCompatibility\JobProcessor;
use WpPluginInsights\RunnerPhpCompatibility\PhpCompatibilityAnalyzer;

class JobProcessorTest extends TestCase
{
    #[DataProvider('fixtureProvider')]
    public function testDetectsExpectedMinimumPhpVersion(string $fixtureName, string $expectedVersion): void
    {
        $processor = new JobProcessor($this->makeConfig());
        $fixturePath = dirname(__DIR__) . '/fixtures/' . $fixtureName;

        $payload = json_encode([
            'plugin' => $fixtureName,
            'version' => '1.0.0',
            'source' => 'local',
            'src' => $fixturePath,
        ], JSON_THROW_ON_ERROR);

        $result = $processor->process($payload);

        self::assertSame('runner-php-compatibility', $result['runner']);
        self::assertSame($fixtureName, $result['plugin']);
        self::assertSame($fixturePath, $result['src']);
        self::assertSame($expectedVersion, $result['report']['metrics']['detected_min_php']);
        self::assertSame('ok', $result['report']['status']);
        self::assertContains($expectedVersion, $result['report']['metrics']['tested_versions']);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function fixtureProvider(): iterable
    {
        yield 'works on all supported versions' => ['min-php-5.6', '5.6'];
        yield 'breaks 5.6 only' => ['min-php-7.0', '7.0'];
        yield 'breaks 7.0 only' => ['min-php-7.1', '7.1'];
        yield 'breaks 7.1 only' => ['min-php-7.2', '7.2'];
        yield 'breaks 7.2 only' => ['min-php-7.3', '7.3'];
        yield 'breaks 7.3 only' => ['min-php-7.4', '7.4'];
        yield 'breaks 7.4 only' => ['min-php-8.0', '8.0'];
        yield 'breaks 8.0 only' => ['min-php-8.1', '8.1'];
        yield 'breaks 8.1 only' => ['min-php-8.2', '8.2'];
        yield 'breaks 8.2 only' => ['min-php-8.3', '8.3'];
        yield 'breaks 8.3 only' => ['min-php-8.4', '8.4'];
        yield 'breaks 8.4 only' => ['min-php-8.5', '8.5'];
        yield 'ignores commented out 8.4 syntax' => ['commented-php-8.4', '5.6'];
        yield 'ignores docblock 8.4 syntax' => ['docblock-php-8.4', '5.6'];
        yield 'ignores string 8.4 syntax' => ['string-php-8.4', '5.6'];
        yield 'highest requirement wins across multiple files' => ['multi-file-mixed', '8.1'];
        yield 'highest requirement wins in same file' => ['mixed-features-same-file', '8.3'];
    }

    public function testSupportedVersionsListIncludesPhp85(): void
    {
        self::assertSame(
            ['5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'],
            PhpCompatibilityAnalyzer::supportedVersions()
        );
    }

    public function testRejectsInvalidJson(): void
    {
        $processor = new JobProcessor($this->makeConfig());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message body is not valid JSON.');

        $processor->process('{invalid json');
    }

    public function testRejectsMissingPlugin(): void
    {
        $processor = new JobProcessor($this->makeConfig());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "plugin" field.');

        $processor->process(json_encode([
            'version' => '1.0.0',
            'source' => 'local',
            'src' => '/tmp/plugin',
        ], JSON_THROW_ON_ERROR));
    }

    public function testRejectsMissingSourceWhenOnlyVersionIsProvided(): void
    {
        $processor = new JobProcessor($this->makeConfig());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "source" field.');

        $processor->process(json_encode([
            'plugin' => 'akismet',
            'version' => '1.0.0',
        ], JSON_THROW_ON_ERROR));
    }

    public function testRejectsMissingSource(): void
    {
        $processor = new JobProcessor($this->makeConfig());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "source" field.');

        $processor->process(json_encode([
            'plugin' => 'akismet',
            'version' => '1.0.0',
            'src' => '/tmp/plugin',
        ], JSON_THROW_ON_ERROR));
    }

    public function testRejectsMissingSrc(): void
    {
        $processor = new JobProcessor($this->makeConfig());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "src" field.');

        $processor->process(json_encode([
            'plugin' => 'akismet',
            'version' => '1.0.0',
            'source' => 'local',
        ], JSON_THROW_ON_ERROR));
    }

    public function testRejectsMissingDirectory(): void
    {
        $processor = new JobProcessor($this->makeConfig());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plugin source path does not exist: /tmp/does-not-exist');

        $processor->process(json_encode([
            'plugin' => 'missing-plugin',
            'version' => '1.0.0',
            'source' => 'local',
            'src' => '/tmp/does-not-exist',
        ], JSON_THROW_ON_ERROR));
    }

    public function testReportEnvelopeContainsExpectedKeys(): void
    {
        $processor = new JobProcessor($this->makeConfig());
        $fixturePath = dirname(__DIR__) . '/fixtures/min-php-7.4';

        $result = $processor->process(json_encode([
            'plugin' => 'min-php-7.4',
            'version' => '1.0.0',
            'source' => 'local',
            'src' => $fixturePath,
        ], JSON_THROW_ON_ERROR));

        self::assertArrayHasKey('runner', $result);
        self::assertArrayHasKey('plugin', $result);
        self::assertArrayHasKey('src', $result);
        self::assertArrayHasKey('report', $result);
        self::assertArrayHasKey('received_at', $result);
        self::assertArrayHasKey('completed_at', $result);
        self::assertArrayHasKey('status', $result['report']);
        self::assertArrayHasKey('score', $result['report']);
        self::assertArrayHasKey('metrics', $result['report']);
        self::assertArrayHasKey('issues', $result['report']);
        self::assertArrayHasKey('details', $result['report']);
        self::assertArrayHasKey('detected_min_php', $result['report']['metrics']);
        self::assertArrayHasKey('declared_min_php', $result['report']['metrics']);
        self::assertArrayHasKey('summary', $result['report']['metrics']);
    }

    public function testGradeIsAPlusWhenReadmeMatchesDetectedVersion(): void
    {
        $result = $this->processFixture('readme-txt-match');

        self::assertSame('7.4', $result['report']['metrics']['detected_min_php']);
        self::assertSame('7.4', $result['report']['metrics']['declared_min_php']);
        self::assertSame('A+', $result['report']['score']['grade']);
    }

    public function testReadmeTxtTakesPrecedenceOverReadmeMd(): void
    {
        $result = $this->processFixture('readme-txt-precedence');

        self::assertSame('7.4', $result['report']['metrics']['declared_min_php']);
        self::assertStringEndsWith('/readme.txt', $result['report']['metrics']['declared_min_php_source']);
        self::assertSame('A+', $result['report']['score']['grade']);
    }

    public function testReadmeMdIsUsedWhenReadmeTxtIsMissing(): void
    {
        $result = $this->processFixture('readme-md-fallback');

        self::assertSame('8.0', $result['report']['metrics']['declared_min_php']);
        self::assertStringEndsWith('/readme.md', $result['report']['metrics']['declared_min_php_source']);
        self::assertSame('A+', $result['report']['score']['grade']);
    }

    public function testGradeIsFWhenDeclaredVersionIsLowerThanDetectedVersion(): void
    {
        $result = $this->processFixture('readme-declared-too-low');

        self::assertSame('7.4', $result['report']['metrics']['declared_min_php']);
        self::assertSame('8.0', $result['report']['metrics']['detected_min_php']);
        self::assertSame('F', $result['report']['score']['grade']);
    }

    public function testNestedReadmeIsIgnored(): void
    {
        $result = $this->processFixture('nested-readme-ignored');

        self::assertNull($result['report']['metrics']['declared_min_php']);
        self::assertNull($result['report']['metrics']['declared_min_php_source']);
        self::assertSame('C', $result['report']['score']['grade']);
    }

    private function makeConfig(): Config
    {
        return new Config(
            rabbitMqHost: '127.0.0.1',
            rabbitMqPort: 5672,
            rabbitMqUser: 'guest',
            rabbitMqPassword: 'guest',
            rabbitMqVhost: '/',
            inputQueue: 'plugin.analysis.runner-php-compatibility',
            reportExchange: 'plugin.analysis.reports',
            runnerName: 'runner-php-compatibility'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function processFixture(string $fixtureName): array
    {
        $processor = new JobProcessor($this->makeConfig());
        $fixturePath = dirname(__DIR__) . '/fixtures/' . $fixtureName;

        return $processor->process(json_encode([
            'plugin' => $fixtureName,
            'version' => '1.0.0',
            'source' => 'local',
            'src' => $fixturePath,
        ], JSON_THROW_ON_ERROR));
    }
}
