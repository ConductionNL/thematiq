<?php

/**
 * Golden test for how CustomTokenSetController stores a converted upload.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\CustomTokenSetController;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionMethod;
use RuntimeException;

/**
 * Stores seeded converter results against recording doubles: a converter that throws (with a
 * status code or not), imports nothing, emits a foreign selector or declarations the real
 * validator refuses, with and without theming, logo, version, import warnings, errors, a skipped
 * report and dark values (allowed and refused); a store that refuses (409, 500, other codes) and
 * a dark-value pin that throws; served CSS present or not. Records the response and every
 * collaborator call (only the arguments passed, by name), case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Controller\CustomTokenSetController
 * @uses   \OCA\Thematiq\Service\CustomTokenSetValidator
 */
class CustomTokenSetStoreConvertedGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/custom-token-set-store-converted.golden.json';

	private const DECLARATIONS = [
		['--nldesign-color-primary' => '#154273', '--nldesign-color-primary-text' => '#ffffff'],
		['--nldesign-color-primary' => 'expression(alert(1))'],
		['--not-a-token' => '1px'],
		[],
	];

	/**
	 * The calls of the current case.
	 *
	 * @var array<int, array<int|string, mixed>>
	 */
	private array $calls = [];

	/**
	 * Every seeded upload has the recorded response and calls.
	 *
	 * @return void
	 */
	public function testStoreConvertedMatchesTheGoldenRecording(): void {
		$actual = [];
		for ($seed = 1; $seed <= 500; $seed++) {
			$actual['seed:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)));
		}

		for ($seed = 1; $seed <= 200; $seed++) {
			$actual['persist:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)), parsed: $this->parsed(random: new Randomizer(new Mt19937(($seed + 10000)))));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(array_keys($expected), array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame($json, $actual[$name], $name);
		}
	}//end testStoreConvertedMatchesTheGoldenRecording()

	/**
	 * One seeded upload, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param array<string, mixed>|null $parsed A validated upload to persist directly, or null to store a converted one.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random, ?array $parsed = null): string {
		$this->calls = [];
		$description = ['  Beschrijving ', '', null][$random->getInt(0, 2)];
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(
			function (string $key, $default = null) use ($description) {
				$this->calls[] = ['IRequest::getParam', $key, $default];
				if ($key === 'description') {
					return $description;
				}

				return $default;
			}
		);

		$controller = new CustomTokenSetController(
			appName: 'thematiq',
			request: $request,
			service: $this->service(random: $random),
			validator: new CustomTokenSetValidator(),
			cssParser: $this->recorder(class: CssParserService::class, methods: ['parseRootBlock' => self::DECLARATIONS[max(0, $random->getInt(-6, 3))]]),
			l: $this->l10n(),
			auditService: $this->recorder(class: ThemingAuditService::class, methods: ['log' => null]),
			config: $this->createMock(IConfig::class),
			converter: $this->converter(random: $random),
			themingService: $this->createMock(ThemingService::class),
			designSystems: $this->createMock(DesignSystemService::class)
		);

		$read = ['content' => '{"color":{}}', 'sourceName' => ['theme.json', null][$random->getInt(0, 1)]];
		$name = ['Gemeente Zuid', 'Stad'][$random->getInt(0, 1)];
		$slug = ['gemeente-zuid', 'stad'][$random->getInt(0, 1)];
		if ($parsed !== null) {
			$response = (new ReflectionMethod(CustomTokenSetController::class, 'persist'))->invoke($controller, $name, $parsed);
		} else {
			$response = (new ReflectionMethod(CustomTokenSetController::class, 'storeConverted'))->invoke($controller, $name, $slug, $read);
		}
		self::assertInstanceOf(JSONResponse::class, $response);

		return (string)json_encode(
			['status' => $response->getStatus(), 'data' => $response->getData(), 'calls' => $this->calls],
			(JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
		);
	}//end runCase()

	/**
	 * A seeded validated upload, each optional key absent, null or set.
	 *
	 * @param Randomizer $random The upload's randomizer.
	 *
	 * @return array<string, mixed>
	 */
	private function parsed(Randomizer $random): array {
		$parsed = ['accepted' => ['--nldesign-color-primary' => '#154273']];
		$values = [
			'version' => '1.0.0', 'importWarnings' => [['path' => 'a', 'message' => 'm']], 'css' => ":root {}\n",
			'theming' => ['primary_color' => '#000000'], 'logoAsset' => ['path' => 'l.svg', 'contents' => '<svg/>'],
			'designSystem' => 'lasuite', 'darkPins' => ['--nldesign-color-primary' => '#99ccff'], 'imported' => 4,
			'skipped' => [['path' => 'x', 'reason' => null]], 'errors' => [['message' => 'e']], 'report' => [['action' => 'mapped']],
			'counts' => ['mapped' => 1], 'inputKind' => 'css', 'reasons' => ['r' => 'reason'],
		];
		foreach ($values as $key => $value) {
			$roll = $random->getInt(0, 2);
			if ($roll === 1) {
				$parsed[$key] = null;
			}

			if ($roll === 2) {
				$parsed[$key] = $value;
			}
		}

		return $parsed;
	}//end parsed()

	/**
	 * A converter that throws, imports nothing or returns a seeded result.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return TokenSetConverterService
	 */
	private function converter(Randomizer $random): TokenSetConverterService {
		$mode = $random->getInt(0, 11);
		$result = [
			'imported' => [3, 12][$random->getInt(0, 1)],
			'css' => [":root {\n\t--nldesign-color-primary: #154273;\n}\n", "body { color: red; }\n"][(int)($random->getInt(0, 7) === 0)],
			'report' => [[], [['action' => 'skipped', 'source' => 'a.b', 'reason' => 'unmapped'], ['action' => 'mapped', 'source' => 'c']], [['action' => 'skipped']]][$random->getInt(0, 2)],
			'counts' => ['mapped' => 3],
			'inputKind' => ['dtcg', 'css'][$random->getInt(0, 1)],
			'errors' => [[], [['path' => 'x', 'message' => 'hard']]][$random->getInt(0, 1)],
		];
		if ($mode === 2) {
			$result['imported'] = 0;
		}

		if ($random->getInt(0, 1) === 0) {
			$result['manifestEntry'] = ['theming' => ['primary_color' => '#154273']];
			if ($random->getInt(0, 1) === 0) {
				$result['manifestEntry']['upstreamVersion'] = ['2.0.0', ''][$random->getInt(0, 1)];
			}
		}

		if ($random->getInt(0, 2) === 0) {
			$result['logoAsset'] = ['path' => 'logo.svg', 'contents' => '<svg/>'];
		}

		if ($random->getInt(0, 1) === 0) {
			$result['importWarnings'] = [[], [['path' => 'a', 'message' => 'deprecated']]][$random->getInt(0, 1)];
		}

		if ($random->getInt(0, 2) === 0) {
			$result['darkDeclarations'] = [[], ['--nldesign-color-primary' => '#99ccff'], ['--nldesign-color-primary' => 'expression(1)']][$random->getInt(0, 2)];
		}

		$converter = $this->recorder(class: TokenSetConverterService::class, methods: ['getReasons' => ['unmapped' => 'No Nextcloud token reads it.']]);
		$converter->method('convert')->willReturnCallback(
			function (...$arguments) use ($mode, $result): array {
				$this->calls[] = ['TokenSetConverterService::convert'] + $this->named(method: [TokenSetConverterService::class, 'convert'], arguments: $arguments);
				if ($mode === 0) {
					throw new RuntimeException('not a theme', 413);
				}

				if ($mode === 1) {
					throw new RuntimeException('not a theme', 7);
				}

				return $result;
			}
		);

		return $converter;
	}//end converter()

	/**
	 * A custom token set service double that stores (or refuses), pins dark values and serves CSS.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return CustomTokenSetService
	 */
	private function service(Randomizer $random): CustomTokenSetService {
		$storeMode = $random->getInt(0, 9);
		$pinThrows = ($random->getInt(0, 5) === 0);
		$service = $this->recorder(class: CustomTokenSetService::class, methods: ['getRawContent' => [":root {}\n", null][$random->getInt(0, 1)]]);
		$service->method('store')->willReturnCallback(
			function (...$arguments) use ($storeMode): array {
				$this->calls[] = ['CustomTokenSetService::store'] + $this->named(method: [CustomTokenSetService::class, 'store'], arguments: $arguments);
				if ($storeMode < 3) {
					throw new RuntimeException('refused', [409, 500, 3][$storeMode]);
				}

				return ['id' => 'custom-gemeente-zuid', 'warnings' => [['pair' => 'a vs b', 'ratio' => 2.5]]];
			}
		);
		$service->method('keepDarkValues')->willReturnCallback(
			function (...$arguments) use ($pinThrows): void {
				$this->calls[] = ['CustomTokenSetService::keepDarkValues'] + $this->named(method: [CustomTokenSetService::class, 'keepDarkValues'], arguments: $arguments);
				if ($pinThrows === true) {
					throw new RuntimeException('pin failed', 507);
				}
			}
		);

		return $service;
	}//end service()

	/**
	 * A translator that fills `%s` placeholders.
	 *
	 * @return IL10N
	 */
	private function l10n(): IL10N {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, $parameters = []): string => vsprintf($text, (array)$parameters)
		);

		return $l10n;
	}//end l10n()

	/**
	 * A double whose listed methods record their arguments by name and return the given value.
	 *
	 * @param class-string $class The class or interface.
	 * @param array<string, mixed> $methods Method => return value.
	 *
	 * @return MockObject
	 */
	private function recorder(string $class, array $methods): MockObject {
		$double = $this->createMock($class);
		foreach ($methods as $method => $value) {
			$double->method($method)->willReturnCallback(
				function (...$arguments) use ($class, $method, $value) {
					$short = substr($class, (strrpos($class, '\\') + 1));
					$this->calls[] = [$short . '::' . $method] + $this->named(method: [$class, $method], arguments: $arguments);
					return $value;
				}
			);
		}

		return $double;
	}//end recorder()

	/**
	 * The arguments a call received, keyed by parameter name.
	 *
	 * @param array{0: class-string, 1: string} $method The called method.
	 * @param array<int|string, mixed> $arguments The received arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function named(array $method, array $arguments): array {
		$parameters = (new ReflectionMethod($method[0], $method[1]))->getParameters();
		$named = [];
		foreach ($arguments as $key => $value) {
			if (is_int($key) === true) {
				$key = $parameters[$key]->getName();
			}

			$named[$key] = $value;
		}

		return $named;
	}//end named()
}//end class
