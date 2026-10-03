<?php

/**
 * Unit tests for the environment marker.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/environment-marker/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\EnvironmentMarkerService;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The marker reads `thematiq.environment` from config.php, labels every
 * non-production value, flags a typo instead of hiding it, and fails open.
 */
class EnvironmentMarkerServiceTest extends TestCase {

	/**
	 * The system config mock.
	 *
	 * @var IConfig&MockObject
	 */
	private $config;

	/**
	 * The initial state mock.
	 *
	 * @var IInitialState&MockObject
	 */
	private $initialState;

	/**
	 * The logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * In-memory app config.
	 *
	 * @var array<string, string>
	 */
	private array $app = [];

	/**
	 * Set up mocks before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->app[$key] ?? $default));
		$this->config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->app[$key] = (string)$value;
			}
		);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}//end setUp()

	/**
	 * Build the service with a declared value, counting asset emission.
	 *
	 * @param string $value The `thematiq.environment` value in config.php.
	 *
	 * @return EnvironmentMarkerService&MockObject The service, with emitAssets() stubbed.
	 */
	private function service(string $value): EnvironmentMarkerService {
		$this->config->method('getSystemValueString')
			->with('thematiq.environment', '')
			->willReturn($value);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text) => $text);

		return $this->getMockBuilder(EnvironmentMarkerService::class)
			->setConstructorArgs([$this->config, $this->initialState, $l10n, $this->logger])
			->onlyMethods(['emitAssets'])
			->getMock();
	}//end service()

	/**
	 * Each non-production value maps to its label, short label and style.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
	 */
	public static function environmentProvider(): array {
		return [
			'development' => ['development', 'Development environment', '[Development]', 'development'],
			'test' => ['test', 'Test environment', '[Test]', 'test'],
			'acceptance' => ['acceptance', 'Acceptance environment', '[Acceptance]', 'acceptance'],
			'upper case and spaces' => [' Test ', 'Test environment', '[Test]', 'test'],
		];
	}//end environmentProvider()

	/**
	 * A declared non-production environment resolves to a label.
	 *
	 * @dataProvider environmentProvider
	 *
	 * @param string $value The declared value.
	 * @param string $label The expected label.
	 * @param string $short The expected title prefix.
	 * @param string $style The expected style key.
	 */
	public function testNonProductionValuesResolveToALabel(string $value, string $label, string $short, string $style): void {
		$marker = $this->service($value)->resolve();

		$this->assertNotNull($marker);
		$this->assertSame($label, $marker['label']);
		$this->assertSame($short, $marker['short']);
		$this->assertSame($style, $marker['style']);
	}//end testNonProductionValuesResolveToALabel()

	/**
	 * Production and an unset value render nothing.
	 */
	public function testProductionAndUnsetResolveToNothing(): void {
		$this->assertNull($this->service('production')->resolve());
		$this->setUp();
		$this->assertNull($this->service('')->resolve());
	}//end testProductionAndUnsetResolveToNothing()

	/**
	 * A typo is labelled unknown, styled as non-production, and logged.
	 */
	public function testAnUnknownValueIsShownAndLogged(): void {
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('{value}'), $this->callback(fn (array $ctx) => ($ctx['value'] ?? null) === 'tset'));

		$marker = $this->service('tset')->resolve();

		$this->assertNotNull($marker);
		$this->assertSame('Unknown environment', $marker['label']);
		$this->assertSame('test', $marker['style']);
	}//end testAnUnknownValueIsShownAndLogged()

	/**
	 * A typo is logged once, not on every render, and a different typo is logged again.
	 */
	public function testAnUnknownValueIsLoggedOnce(): void {
		$this->logger->expects($this->once())->method('warning');

		$this->service('tset')->resolve();
		$this->service('tset')->resolve();

		// A new request with another typo: the app config note stays.
		$this->setUp();
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->anything(), $this->callback(fn (array $ctx) => ($ctx['value'] ?? null) === 'acceptatie'));

		$this->service('acceptatie')->resolve();
		$this->service('acceptatie')->resolve();
	}//end testAnUnknownValueIsLoggedOnce()

	/**
	 * inject() emits the assets and the initial state for a test server.
	 */
	public function testInjectEmitsAssetsAndStateOffProduction(): void {
		$service = $this->service('test');
		$service->expects($this->once())->method('emitAssets');
		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->with('environment', $this->callback(fn (array $state) => $state['label'] === 'Test environment' && $state['short'] === '[Test]'));

		$service->inject();
	}//end testInjectEmitsAssetsAndStateOffProduction()

	/**
	 * inject() does nothing on production.
	 */
	public function testInjectDoesNothingOnProduction(): void {
		$service = $this->service('production');
		$service->expects($this->never())->method('emitAssets');
		$this->initialState->expects($this->never())->method('provideInitialState');

		$service->inject();
	}//end testInjectDoesNothingOnProduction()

	/**
	 * A failure renders no marker, logs a warning and never escapes.
	 */
	public function testInjectFailsOpen(): void {
		$this->config->method('getSystemValueString')->willThrowException(new RuntimeException('boom'));
		$l10n = $this->createMock(IL10N::class);
		$service = new EnvironmentMarkerService($this->config, $this->initialState, $l10n, $this->logger);
		$this->logger->expects($this->once())->method('warning');
		$this->initialState->expects($this->never())->method('provideInitialState');

		$service->inject();
	}//end testInjectFailsOpen()

	/**
	 * Each fixed stripe colour and its label reach 4.5:1, and the stylesheet
	 * carries exactly the colours the service declares. The stripe does not
	 * change with the colour scheme, so one pair covers light and dark mode.
	 */
	public function testStripeColoursReachAaAndMatchTheStylesheet(): void {
		$contrast = new ContrastService();
		$css = (string)file_get_contents(dirname(__DIR__, 3) . '/css/environment-marker.css');

		foreach (EnvironmentMarkerService::STYLES as $style => $pair) {
			$ratio = $contrast->measure(foreground: $pair['text'], background: $pair['background']);
			$this->assertNotNull($ratio);
			$this->assertGreaterThanOrEqual(4.5, $ratio, "$style: label on stripe is below 4.5:1.");

			$this->assertMatchesRegularExpression(
				'/\.thematiq-env-marker--' . $style . '\s*\{[^}]*background-color:\s*' . preg_quote($pair['background'], '/') . ';[^}]*color:\s*' . preg_quote($pair['text'], '/') . ';/i',
				$css,
				"$style: css/environment-marker.css must use the service's colours."
			);
		}
	}//end testStripeColoursReachAaAndMatchTheStylesheet()
}//end class
