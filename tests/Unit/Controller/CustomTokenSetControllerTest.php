<?php

/**
 * Unit tests for CustomTokenSetController.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\CustomTokenSetController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the custom token set upload controller's DTCG response
 * shape: structured `errors`/`importWarnings`/`version`, additive to the
 * pre-existing `imported`/`skipped`/`warnings` (contrast) shape, and the
 * zero-yield 422 with full diagnostics.
 *
 * Uses a real {@see CustomTokenSetService} against a temp app directory
 * (mirroring {@see \OCA\Thematiq\Tests\Unit\Service\CustomTokenSetServiceTest})
 * so the response is exercised end-to-end through storage, not mocked away.
 */
class CustomTokenSetControllerTest extends TestCase {

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * In-memory appconfig store: key => value.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The controller under test.
	 *
	 * @var CustomTokenSetController
	 */
	private CustomTokenSetController $controller;

	/**
	 * The request mock, reconfigured per test via {@see self::mockRequest()}.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $request;

	/**
	 * The service under exercise (real instance, temp-dir backed).
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $service;

	/**
	 * Set up a temp app dir + mocked config/request before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-controller-test-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = $value;
			}
		);

		$this->service = new CustomTokenSetService(
			$appManager,
			$config,
			new CustomTokenSetValidator(),
			new ContrastService(),
			new DarkPaletteService(
				new ContrastService(),
				new CssParserService(),
				$appManager,
				$this->createMock(LoggerInterface::class)
			)
		);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(
			function (string $text, $parameters = []) {
				if (empty($parameters) === true) {
					return $text;
				}

				return vsprintf($text, $parameters);
			}
		);

		// The converter reads the mapping table and the two vocabulary
		// stylesheets out of the app directory, which for it must be the REPO,
		// not the temp dir the service writes into — hence a second app-manager
		// mock. Everything the converter writes still goes through $this->service
		// and lands in the temp dir.
		$repoAppManager = $this->createMock(IAppManager::class);
		$repoAppManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$converter = new TokenSetConverterService(
			$repoAppManager,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);

		$this->request = $this->createMock(IRequest::class);
		$this->controller = new CustomTokenSetController(
			'nldesign',
			$this->request,
			$this->service,
			new CustomTokenSetValidator(),
			new CssParserService(),
			$l,
			$this->createMock(ThemingAuditService::class),
			$config,
			$converter,
			$this->createMock(ThemingService::class)
		);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 */
	protected function tearDown(): void {
		$this->rrmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Recursively remove a directory tree.
	 *
	 * @param string $dir The directory to remove.
	 *
	 * @return void
	 */
	private function rrmdir(string $dir): void {
		if (is_dir($dir) === false) {
			return;
		}

		foreach (scandir($dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $dir . '/' . $entry;
			if (is_dir($path) === true) {
				$this->rrmdir($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}//end rrmdir()

	/**
	 * Configure the request mock for one upload call: `name` param and an
	 * uploaded file with the given filename/content.
	 *
	 * @param string $name The token-set display name param.
	 * @param string $filename The uploaded filename (drives extension routing).
	 * @param string $content The raw uploaded file content.
	 *
	 * @return void
	 */
	private function mockUpload(string $name, string $filename, string $content): void {
		$tmpFile = (sys_get_temp_dir() . '/nldesign-upload-' . uniqid());
		file_put_contents($tmpFile, $content);

		$this->request->method('getParam')->willReturnCallback(
			fn (string $key, $default = null) => ($key === 'name' ? $name : $default)
		);
		$this->request->method('getUploadedFile')->willReturn(
			[
				'tmp_name' => $tmpFile,
				'name' => $filename,
				'size' => strlen($content),
			]
		);
	}//end mockUpload()

	/**
	 * A well-formed DTCG upload returns the additive `errors`/`importWarnings`/
	 * `version` fields alongside the pre-existing `imported`/`skipped`/`warnings`.
	 */
	public function testUploadDtcgReturnsStructuredDiagnosticsAndVersion(): void {
		$document = [
			'$version' => '2.3.1',
			'color' => [
				'primary' => [
					'$type' => 'color',
					'$value' => '#154273',
					'$deprecated' => 'Use color.brand.primary instead',
				],
			],
			'shadow' => ['elevation-1' => ['$type' => 'shadow', '$value' => '0 1px 2px']],
		];

		$this->mockUpload(name: 'Gemeente Voorbeeld', filename: 'theme.tokens.json', content: json_encode($document));

		$response = $this->controller->upload();
		$this->assertInstanceOf(JSONResponse::class, $response);

		$data = $response->getData();
		$this->assertSame(200, $response->getStatus());
		$this->assertSame('custom-gemeente-voorbeeld', $data['id']);
		$this->assertSame(1, $data['imported']);
		$this->assertSame('2.3.1', $data['version']);
		$this->assertCount(1, $data['importWarnings']);
		$this->assertSame('color.primary', $data['importWarnings'][0]['path']);

		$skippedPaths = array_column($data['skipped'], 'path');
		$this->assertContains('shadow.elevation-1', $skippedPaths);

		// Version + import warnings round-trip through the manifest/list.
		$listed = $this->service->list();
		$this->assertSame('2.3.1', $listed[0]['version']);
		$this->assertNotEmpty($listed[0]['importWarnings']);
	}//end testUploadDtcgReturnsStructuredDiagnosticsAndVersion()

	/**
	 * A zero-yield DTCG document (well-formed, but nothing maps) is rejected
	 * with HTTP 422 carrying the full structured diagnostics.
	 */
	public function testUploadZeroYieldDtcgReturns422WithDiagnostics(): void {
		$document = [
			'shadow' => ['elevation-1' => ['$type' => 'shadow', '$value' => '0 1px 2px']],
			'spacing' => ['unit' => ['$type' => 'dimension', '$value' => ['value' => 4, 'unit' => 'px']]],
		];

		$this->mockUpload(name: 'Empty Set', filename: 'empty.tokens.json', content: json_encode($document));

		$response = $this->controller->upload();
		$this->assertSame(422, $response->getStatus());

		$data = $response->getData();
		$this->assertArrayHasKey('error', $data);
		$this->assertSame(0, $data['imported']);
		$this->assertCount(2, $data['skipped']);
		$this->assertSame([], $data['errors']);

		// Nothing was persisted.
		$this->assertSame([], $this->service->list());
	}//end testUploadZeroYieldDtcgReturns422WithDiagnostics()

	/**
	 * A malformed JSON upload is rejected with HTTP 422 and no file is written.
	 */
	public function testUploadMalformedJsonReturns422(): void {
		$this->mockUpload(name: 'Broken', filename: 'broken.tokens.json', content: '{ "color": { "primary": {');

		$response = $this->controller->upload();
		$this->assertSame(422, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
		$this->assertSame([], $this->service->list());
	}//end testUploadMalformedJsonReturns422()

	/**
	 * An alias-cycle diagnostic surfaces in the response `errors` with the
	 * full cycle path, while the valid sibling token still imports.
	 */
	public function testUploadWithAliasCycleStillImportsValidTokenAndReportsError(): void {
		$document = [
			'a' => ['x' => ['$type' => 'color', '$value' => '{b.y}']],
			'b' => ['y' => ['$type' => 'color', '$value' => '{a.x}']],
			'color' => ['primary' => ['$type' => 'color', '$value' => '#154273']],
		];

		$this->mockUpload(name: 'Cycle Set', filename: 'cycle.tokens.json', content: json_encode($document));

		$response = $this->controller->upload();
		$this->assertSame(200, $response->getStatus());

		$data = $response->getData();
		$errorPaths = array_column($data['errors'], 'reason', 'path');
		$this->assertSame('alias-cycle', ($errorPaths['a.x'] ?? null));
	}//end testUploadWithAliasCycleStillImportsValidTokenAndReportsError()

	/**
	 * A CSS upload's response is unaffected by the DTCG additive fields —
	 * no `errors`/`importWarnings`/`version` keys are present.
	 */
	public function testUploadCssResponseOmitsDtcgFields(): void {
		$this->mockUpload(
			name: 'Css Set',
			filename: 'theme.css',
			content: ":root {\n  --nldesign-color-primary: #007bc7;\n}\n"
		);

		$response = $this->controller->upload();
		$this->assertSame(200, $response->getStatus());

		$data = $response->getData();
		$this->assertArrayNotHasKey('errors', $data);
		$this->assertArrayNotHasKey('importWarnings', $data);
		$this->assertArrayNotHasKey('version', $data);
	}//end testUploadCssResponseOmitsDtcgFields()

	/**
	 * A semicolon-smuggled value mapped from a DTCG JSON token is rejected by
	 * the same isForbiddenValue() gate the CSS path uses — the injection is
	 * not limited to the CSS upload format.
	 */
	public function testJsonUploadWithSemicolonSmugglingIsRejected(): void {
		$document = [
			'color' => [
				'primary' => [
					'$type' => 'color',
					'$value' => 'red; --nldesign-evil: url(javascript:alert(1))',
				],
			],
		];

		$this->mockUpload(name: 'Gemeente Voorbeeld', filename: 'theme.tokens.json', content: json_encode($document));

		$response = $this->controller->upload();

		$this->assertSame(422, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
	}//end testJsonUploadWithSemicolonSmugglingIsRejected()

	/**
	 * A comment-marker-smuggled value mapped from a DTCG JSON token is
	 * rejected the same way.
	 */
	public function testJsonUploadWithCommentMarkerSmugglingIsRejected(): void {
		$document = [
			'color' => [
				'primary' => [
					'$type' => 'color',
					'$value' => 'red */ .evil { color: red',
				],
			],
		];

		$this->mockUpload(name: 'Gemeente Voorbeeld', filename: 'theme.tokens.json', content: json_encode($document));

		$response = $this->controller->upload();

		$this->assertSame(422, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
	}//end testJsonUploadWithCommentMarkerSmugglingIsRejected()

	/**
	 * A legitimate mapped value (no injection characters) is accepted and
	 * stored — no regression for benign W3C Design Tokens uploads.
	 */
	public function testJsonUploadWithCleanValueIsAccepted(): void {
		$document = [
			'color' => [
				'primary' => [
					'$type' => 'color',
					'$value' => '#154273',
				],
			],
		];

		$this->mockUpload(name: 'Gemeente Voorbeeld', filename: 'theme.tokens.json', content: json_encode($document));

		$response = $this->controller->upload();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('custom-gemeente-voorbeeld', $response->getData()['id']);
		$this->assertSame(1, $response->getData()['imported']);
	}//end testJsonUploadWithCleanValueIsAccepted()
}//end class
