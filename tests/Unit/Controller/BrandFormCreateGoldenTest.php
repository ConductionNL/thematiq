<?php

/**
 * Golden test for BrandFormController::create().
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

use InvalidArgumentException;
use OCA\Thematiq\Controller\BrandFormController;
use OCA\Thematiq\Service\AuditAndTranslation;
use OCA\Thematiq\Service\BrandFormService;
use OCA\Thematiq\Service\BrandFormSources;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Creates seeded token sets from the brand form: names given or blank, colours the service
 * refuses, logos absent, too large, of a refused type, not an image, or accepted, and a store that
 * refuses (409, 500, another code). Records the response and every call to the service, the
 * store and the audit log (only the arguments passed, by name), case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Controller\BrandFormController
 * @uses   \OCA\Thematiq\Service\AuditAndTranslation
 * @uses   \OCA\Thematiq\Service\BrandFormSources
 * @uses   \OCA\Thematiq\Service\NewCustomTokenSet
 */
class BrandFormCreateGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/brand-form-create.golden.json';

	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	/**
	 * The temporary upload file of the current case.
	 *
	 * @var string
	 */
	private string $upload = '';

	/**
	 * Remove the temporary upload file.
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
	 * Every seeded form posts to the recorded response and calls.
	 *
	 * @return void
	 */
	public function testCreateMatchesTheGoldenRecording(): void {
		$this->upload = (string)tempnam(sys_get_temp_dir(), 'thematiq-brand-form-');
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
	}//end testCreateMatchesTheGoldenRecording()

	/**
	 * One seeded form, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$calls = [];
		$params = [
			'name' => ['Gemeente Zuid', '   ', ''][max(0, $random->getInt(-9, 2))],
			'primary' => ['#c8102e', 'red'][(int)($random->getInt(0, 5) === 0)],
			'background' => '#ffffff',
			'description' => [' Huisstijl ', ''][$random->getInt(0, 1)],
		];
		if ($random->getInt(0, 1) === 0) {
			$params['action'] = '#004494';
		}

		$file = $this->uploadedFile(random: $random);
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(
			static function (string $key, $default = null) use (&$calls, $params) {
				$calls[] = ['getParam', $key, $default];
				return ($params[$key] ?? $default);
			}
		);
		$request->method('getUploadedFile')->willReturn($file);

		$throws = ($params['primary'] === 'red');
		$brandForm = $this->createMock(originalClassName: BrandFormService::class);
		$brandForm->method('derive')->willReturnCallback(
			static function (...$arguments) use (&$calls, $throws): array {
				$calls[] = ['derive', $arguments];
				if ($throws === true) {
					throw new InvalidArgumentException('not a hex colour');
				}

				return [
					'declarations' => ['--nldesign-color-primary' => '#c8102e', '--nldesign-color-nav-background' => '#1a1a1a', '--nldesign-color-text' => '#000'],
					'textOnPrimary' => '#ffffff',
					'textRatio' => 5.9,
					'uiRatio' => 3.1,
					'textOnAction' => '#ffffff',
					'actionTextRatio' => 7.2,
				];
			}
		);

		$storeMode = $random->getInt(0, 7);
		$store = $this->createMock(originalClassName: CustomTokenSetService::class);
		$store->method('store')->willReturnCallback(
			static function (...$arguments) use (&$calls, $storeMode): array {
				$calls[] = ['store', array_values(get_object_vars($arguments[0]))];
				if ($storeMode < 3) {
					throw new RuntimeException('refused', [409, 500, 3][$storeMode]);
				}

				return ['id' => 'custom-gemeente-zuid', 'warnings' => [['pair' => 'a vs b', 'ratio' => 2.9]]];
			}
		);

		$apps = $this->createMock(originalClassName: IAppManager::class);
		$apps->method('getAppPath')->willReturn('/srv/thematiq');
		$audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$audit->method('log')->willReturnCallback(
			static function (...$arguments) use (&$calls): void {
				$calls[] = ['log', $arguments];
			}
		);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		$controller = new BrandFormController(
			appName: 'thematiq',
			request: $request,
			sources: new BrandFormSources(
				brandForm: $brandForm,
				store: $store,
				apps: $apps
			),
			answers: new AuditAndTranslation(
				audit: $audit,
				l: $l10n
			)
		);
		$response = $controller->create();

		return (string)json_encode(
			['status' => $response->getStatus(), 'data' => $response->getData(), 'calls' => $calls],
			(JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
		);
	}//end runCase()

	/**
	 * A seeded logo upload: none, too large, a refused type, not an image, an SVG or a PNG.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, mixed>|null
	 */
	private function uploadedFile(Randomizer $random): ?array {
		$kind = [0, 0, 0, 1, 2, 3, 4, 5, 5, 6, 6][$random->getInt(0, 10)];
		$files = [
			null,
			['name' => 'logo.png', 'tmp_name' => ''],
			['name' => 'logo.svg', 'tmp_name' => $this->upload, 'size' => (512 * 1024 + 1), 'contents' => '<svg/>'],
			['name' => 'logo.exe', 'tmp_name' => $this->upload, 'size' => 10, 'contents' => 'MZ'],
			['name' => 'logo.svg', 'tmp_name' => $this->upload, 'size' => 10, 'contents' => 'not an svg'],
			['name' => 'Logo.SVG', 'tmp_name' => $this->upload, 'size' => 30, 'contents' => '<svg xmlns="http://www.w3.org/2000/svg"/>'],
			['name' => 'logo.png', 'tmp_name' => $this->upload, 'size' => 70, 'contents' => (string)base64_decode(self::PNG)],
		];
		$file = $files[$kind];
		if (is_array($file) === true && isset($file['contents']) === true) {
			file_put_contents($this->upload, $file['contents']);
			unset($file['contents']);
		}

		return $file;
	}//end uploadedFile()
}//end class
