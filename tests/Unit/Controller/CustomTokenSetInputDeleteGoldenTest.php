<?php

/**
 * Golden test for reading an upload's input and deleting a custom token set.
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
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\CustomTokenSetController;
use OCA\Thematiq\Service\BrandingCaptureService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomSetRecords;
use OCA\Thematiq\Service\CustomSetSources;
use OCA\Thematiq\Service\CustomSetUploadPipeline;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\MultiBrandImportService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Uploads seeded input (a file that is absent, too large, unreadable, named or unnamed, or pasted
 * content that is empty, too large, named or unnamed) up to the converter, and deletes seeded
 * sets (not a custom id, not found, the active set or another, with served CSS or none, with and
 * without the branding capture and multi-brand services). Records the response and every
 * collaborator call in order, case by case. Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Controller\CustomTokenSetController
 * @uses   \OCA\Thematiq\Service\CustomSetRecords
 * @uses   \OCA\Thematiq\Service\CustomSetSources
 * @uses   \OCA\Thematiq\Service\CustomSetUploadPipeline
 */
class CustomTokenSetInputDeleteGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/custom-token-set-input-delete.golden.json';

	/**
	 * The temporary upload of the current case.
	 *
	 * @var string
	 */
	private string $upload = '';

	/**
	 * Remove the temporary upload.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ($this->upload !== '' && is_file($this->upload) === true) {
			unlink($this->upload);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Every seeded upload and delete answers as recorded.
	 *
	 * @return void
	 */
	public function testInputAndDeleteMatchTheGoldenRecording(): void {
		$this->upload = (string)tempnam(sys_get_temp_dir(), 'thematiq-set-input-');
		$actual = [];
		for ($seed = 1; $seed <= 300; $seed++) {
			$actual['seed:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testInputAndDeleteMatchTheGoldenRecording()

	/**
	 * One seeded upload or delete, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$calls = [];
		$params = [
			'name' => 'Gemeente Zuid',
			'content' => ['', '   ', ':root{--a:1}', str_repeat('x', (CustomTokenSetValidator::MAX_SIZE + 1))][$random->getInt(0, 3)],
			'sourceName' => ['', '  ', ' theme.json '][$random->getInt(0, 2)],
		];
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			static function (string $key, $default = null) use (&$calls, $params) {
				$calls[] = ['getParam', $key];
				return ($params[$key] ?? $default);
			}
		);
		$request->method('getUploadedFile')->willReturn($this->uploadedFile(random: $random));

		$active = ['nextcloud', 'custom-zuid'][$random->getInt(0, 1)];
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (...$arguments) use (&$calls, $active): string {
				$calls[] = ['getAppValue', $arguments];
				return $active;
			}
		);
		$config->method('deleteAppValue')->willReturnCallback(
			static function (...$arguments) use (&$calls): void {
				$calls[] = ['deleteAppValue', $arguments];
			}
		);

		$controller = new CustomTokenSetController(
			appName: 'thematiq',
			request: $request,
			pipeline: new CustomSetUploadPipeline(
				service: $this->service(random: $random, calls: $calls),
				validator: $this->createMock(originalClassName: CustomTokenSetValidator::class),
				cssParser: $this->createMock(originalClassName: CssParserService::class),
				converter: $this->converter(calls: $calls)
			),
			records: new CustomSetRecords(
				l: $this->l10n(),
				auditService: $this->recorder(class: ThemingAuditService::class, method: 'log', calls: $calls),
				config: $config,
				themingService: $this->recorder(class: ThemingService::class, method: 'resetToDefaults', calls: $calls)
			),
			sources: new CustomSetSources(
				designSystems: $this->createMock(originalClassName: DesignSystemService::class),
				brandingCapture: [null, $this->recorder(class: BrandingCaptureService::class, method: 'forget', calls: $calls)][$random->getInt(0, 1)],
				multiBrand: [null, $this->multiBrand(calls: $calls)][$random->getInt(0, 1)]
			)
		);

		if ($random->getInt(0, 1) === 0) {
			$response = $controller->upload();
		} else {
			$response = $controller->delete(id: ['custom-zuid', 'custom-gone', 'nextcloud'][$random->getInt(0, 2)]);
		}

		return (string)json_encode(['status' => $response->getStatus(), 'data' => $response->getData(), 'calls' => $calls], JSON_UNESCAPED_SLASHES);
	}//end runCase()

	/**
	 * A seeded upload entry, as Nextcloud hands it over.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, mixed>|null
	 */
	private function uploadedFile(Randomizer $random): ?array {
		file_put_contents($this->upload, '{"color":{"primary":{"$value":"#154273"}}}');
		$kind = $random->getInt(0, 7);
		if ($kind === 0) {
			return null;
		}

		if ($kind === 1) {
			return ['name' => 'nope.json'];
		}

		if ($kind < 4) {
			return [];
		}

		$file = ['tmp_name' => $this->upload, 'name' => ['', ' tokens.json ', 'brand.json'][$random->getInt(0, 2)], 'size' => 40];
		if ($kind === 4) {
			$file['size'] = (CustomTokenSetValidator::MAX_SIZE + 1);
		}

		if ($kind === 5) {
			unset($file['name']);
		}

		return $file;
	}//end uploadedFile()

	/**
	 * The custom set store double.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param array<int, mixed> $calls The call log.
	 *
	 * @return CustomTokenSetService
	 */
	private function service(Randomizer $random, array &$calls): CustomTokenSetService {
		$served = [null, ':root{--a:1}'][$random->getInt(0, 1)];
		$service = $this->createMock(originalClassName: CustomTokenSetService::class);
		$service->method('slugify')->willReturn('zuid');
		$service->method('isCustomId')->willReturnCallback(static fn (string $id): bool => str_starts_with($id, 'custom-'));
		$service->method('getRawContent')->willReturnCallback(
			static function (string $id) use (&$calls, $served): ?string {
				$calls[] = ['getRawContent', $id];
				return $served;
			}
		);
		$service->method('delete')->willReturnCallback(
			static function (string $id) use (&$calls): bool {
				$calls[] = ['delete', $id];
				return ($id !== 'custom-gone');
			}
		);

		return $service;
	}//end service()

	/**
	 * A converter double that records its arguments and stops the upload there.
	 *
	 * @param array<int, mixed> $calls The call log.
	 *
	 * @return TokenSetConverterService
	 */
	private function converter(array &$calls): TokenSetConverterService {
		$converter = $this->createMock(originalClassName: TokenSetConverterService::class);
		$converter->method('convert')->willReturnCallback(
			static function (...$arguments) use (&$calls): array {
				$calls[] = ['convert', $arguments];
				throw new RuntimeException('stopped after reading the input');
			}
		);

		return $converter;
	}//end converter()

	/**
	 * A multi-brand double that finds no brands and records what it is asked.
	 *
	 * @param array<int, mixed> $calls The call log.
	 *
	 * @return MultiBrandImportService
	 */
	private function multiBrand(array &$calls): MultiBrandImportService {
		$multiBrand = $this->createMock(originalClassName: MultiBrandImportService::class);
		$multiBrand->method('brands')->willReturnCallback(
			static function (string $content) use (&$calls): array {
				$calls[] = ['brands', strlen($content)];
				return [];
			}
		);
		$multiBrand->method('forgetSet')->willReturnCallback(
			static function (string $id) use (&$calls): void {
				$calls[] = ['forgetSet', $id];
			}
		);

		return $multiBrand;
	}//end multiBrand()

	/**
	 * A double whose one method records its arguments.
	 *
	 * @param class-string $class The class.
	 * @param string $method The method.
	 * @param array<int, mixed> $calls The call log.
	 *
	 * @return mixed
	 */
	private function recorder(string $class, string $method, array &$calls): mixed {
		$double = $this->createMock(originalClassName: $class);
		$double->method($method)->willReturnCallback(
			static function (...$arguments) use (&$calls, $method) {
				$calls[] = [$method, $arguments];
				return [];
			}
		);

		return $double;
	}//end recorder()

	/**
	 * A translator that returns the text.
	 *
	 * @return IL10N
	 */
	private function l10n(): IL10N {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		return $l10n;
	}//end l10n()
}//end class
