<?php

/**
 * Unit tests for CustomTokenSetController keeping Nextcloud's branding with a theme.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\CustomTokenSetController;
use OCA\Thematiq\Service\BrandingCaptureService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * A theme saved from the editor (a raw upload with `captureTheming`) keeps
 * Nextcloud's branding once it is stored, and deleting a custom theme forgets
 * that branding with it. A file Thematiq exported is stored the same way, with
 * its own tokens, so an exported theme uploaded again comes back the same.
 */
class CustomTokenSetControllerCaptureTest extends TestCase {

	/**
	 * The temp app directory.
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
	 * The request mock.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $request;

	/**
	 * The branding capture mock.
	 *
	 * @var BrandingCaptureService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $capture;

	/**
	 * The design-system manifest mock.
	 *
	 * @var DesignSystemService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $designSystems;

	/**
	 * The theme converter mock.
	 *
	 * @var TokenSetConverterService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $converter;

	/**
	 * The controller under test.
	 *
	 * @var CustomTokenSetController
	 */
	private CustomTokenSetController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-capture-controller-' . uniqid();
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

		$service = new CustomTokenSetService(
			new DirectoryRuntimeFileStore($appManager->getAppPath('thematiq')),
			$config,
			new CustomTokenSetValidator(),
			new ContrastService(),
			new DarkPaletteService(new ContrastService(), new CssParserService(), $appManager, $this->createMock(LoggerInterface::class))
		);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$this->request = $this->createMock(IRequest::class);
		$this->capture = $this->createMock(BrandingCaptureService::class);
		$this->designSystems = $this->createMock(DesignSystemService::class);
		$this->designSystems->method('getDesignSystems')->willReturn(
			[
				'none'     => ['id' => 'none'],
				'nldesign' => ['id' => 'nldesign'],
			]
		);
		$this->converter = $this->createMock(TokenSetConverterService::class);

		$this->controller = new CustomTokenSetController(
			'thematiq',
			$this->request,
			$service,
			new CustomTokenSetValidator(),
			new CssParserService(),
			$l,
			$this->createMock(ThemingAuditService::class),
			$config,
			$this->converter,
			$this->createMock(ThemingService::class),
			$this->designSystems,
			$this->capture
		);
	}//end setUp()

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
	 * Save a theme from the editor: a raw token set, capture asked for or not.
	 *
	 * @param bool $capture Whether `captureTheming` is sent.
	 * @param array<string, mixed> $extra Further request parameters, overriding the defaults.
	 *
	 * @return void
	 */
	private function savingFromTheEditor(bool $capture, array $extra = []): void {
		$params = array_merge(
			[
				'name' => 'OpenWoo',
				'content' => ":root {\n  --nldesign-color-primary: #154273;\n}\n",
				'raw' => true,
				'captureTheming' => $capture,
			],
			$extra
		);
		$this->request->method('getParam')->willReturnCallback(
			fn (string $key, $default = null) => ($params[$key] ?? $default)
		);
		$this->request->method('getUploadedFile')->willReturn(null);
	}//end savingFromTheEditor()

	/**
	 * The new theme gets the branding, and the editor gets it back.
	 */
	public function testASavedThemeCapturesTheBranding(): void {
		$this->savingFromTheEditor(capture: true);
		$this->capture->expects($this->once())
			->method('capture')
			->with('custom-openwoo')
			->willReturn(['captured' => true, 'primary_color' => '#23845c']);

		$response = $this->controller->upload();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('custom-openwoo', $response->getData()['id']);
		$this->assertSame(['captured' => true, 'primary_color' => '#23845c'], $response->getData()['theming']);
	}//end testASavedThemeCapturesTheBranding()

	/**
	 * Without the flag nothing is captured.
	 */
	public function testNoCaptureWithoutTheFlag(): void {
		$this->savingFromTheEditor(capture: false);
		$this->capture->expects($this->never())->method('capture');

		$response = $this->controller->upload();

		$this->assertArrayNotHasKey('theming', $response->getData());
	}//end testNoCaptureWithoutTheFlag()

	/**
	 * Deleting a custom theme forgets the branding it captured.
	 */
	public function testDeletingAThemeForgetsItsBranding(): void {
		$this->savingFromTheEditor(capture: false);
		$this->controller->upload();

		$this->capture->expects($this->once())->method('forget')->with('custom-openwoo');

		$response = $this->controller->delete(id: 'custom-openwoo');

		$this->assertSame(200, $response->getStatus());
	}//end testDeletingAThemeForgetsItsBranding()

	/**
	 * A refused save captures nothing: the branding is only copied once the
	 * set is safely stored, so a rejected one leaves no images behind.
	 */
	public function testARefusedSaveCapturesNothing(): void {
		$this->savingFromTheEditor(capture: true, extra: ['content' => ".header {\n  color: red;\n}\n"]);
		$this->capture->expects($this->never())->method('capture');

		$response = $this->controller->upload();

		$this->assertSame(422, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
		$this->assertArrayNotHasKey(CustomTokenSetService::MANIFEST_KEY, $this->appConfig);
	}//end testARefusedSaveCapturesNothing()

	/**
	 * The design system the editor was looking at is recorded with the set,
	 * so the theme comes back with the layers it was saved on.
	 */
	public function testAShippedDesignSystemIsRecordedWithTheSet(): void {
		$this->savingFromTheEditor(capture: false, extra: ['designSystem' => ' nldesign ']);

		$this->controller->upload();

		$manifest = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertSame('nldesign', $manifest['custom-openwoo']['design_system']);
	}//end testAShippedDesignSystemIsRecordedWithTheSet()

	/**
	 * A claimed design system the manifest does not ship is ignored rather
	 * than trusted: it decides which stylesheet layers every page emits.
	 */
	public function testAnUnknownDesignSystemClaimIsIgnored(): void {
		$this->savingFromTheEditor(capture: false, extra: ['designSystem' => 'evil']);

		$this->controller->upload();

		$manifest = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertArrayNotHasKey('design_system', $manifest['custom-openwoo']);
	}//end testAnUnknownDesignSystemClaimIsIgnored()

	/**
	 * Upload a file off disk, the way the custom token set form does.
	 *
	 * @param string $content The file's content.
	 *
	 * @return void
	 */
	private function uploadingAFile(string $content): void {
		$params = [
			'name'    => 'Round Trip',
			'content' => $content,
		];
		$this->request->method('getParam')->willReturnCallback(
			fn (string $key, $default = null) => ($params[$key] ?? $default)
		);
		$this->request->method('getUploadedFile')->willReturn(null);
	}//end uploadingAFile()

	/**
	 * A file "Export as token set" wrote is stored with its own tokens, on the
	 * design system it names — not converted, which filled every token the set
	 * left out with nldesign fallbacks.
	 */
	public function testAnExportedFileIsStoredWithItsOwnTokens(): void {
		$content = "/* NL Design — custom token set, exported from the component playground. Do not edit manually. */
"
			. "/* thematiq-token-set: design-system=none */
"
			. ":root {
  --nldesign-color-primary: #00679e;
}
";
		$this->uploadingAFile(content: $content);
		$this->converter->expects($this->never())->method('convert');

		$response = $this->controller->upload();

		$this->assertSame(200, $response->getStatus());
		$manifest = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertSame('none', $manifest['custom-round-trip']['design_system']);
		$stored = file_get_contents($this->appDir . '/css/tokens/custom-round-trip.css');
		$this->assertStringContainsString('--nldesign-color-primary: #00679e;', $stored);
		$this->assertStringNotContainsString('thematiq-token-set:', $stored);
	}//end testAnExportedFileIsStoredWithItsOwnTokens()

	/**
	 * A rule hidden between two `url()` values does not reach the stored file.
	 *
	 * The guard and the parser read `/*` inside `url('…')` as a comment and a
	 * browser does not, so storing the upload as sent let this file hide every
	 * page body for every user. Only the parsed declarations are written.
	 */
	public function testARuleHiddenInsideUrlValuesIsNotStored(): void {
		$this->uploadingAFile(
			content: "/* thematiq-token-set: design-system=none */\n"
				. ":root { --nldesign-a: url('/*'); }\n"
				. "body { display: none !important; }\n"
				. ":root { --nldesign-b: url('*/'); }\n"
		);
		$this->converter->expects($this->never())->method('convert');

		$response = $this->controller->upload();

		$this->assertSame(200, $response->getStatus());
		$stored = file_get_contents($this->appDir . '/css/tokens/custom-round-trip.css');
		$this->assertStringNotContainsString('body', $stored);
		$this->assertStringNotContainsString('display', $stored);
		$this->assertSame(1, substr_count($stored, '{'));
	}//end testARuleHiddenInsideUrlValuesIsNotStored()

	/**
	 * A design system the marker names but the manifest does not ship is not
	 * recorded, the same as a claim in the request.
	 */
	public function testAnExportedFileNamingAnUnknownDesignSystemRecordsNone(): void {
		$this->uploadingAFile(
			content: "/* thematiq-token-set: design-system=evil */
:root {
  --nldesign-color-primary: #00679e;
}
"
		);
		$this->converter->expects($this->never())->method('convert');

		$this->controller->upload();

		$manifest = json_decode($this->appConfig[CustomTokenSetService::MANIFEST_KEY], true);
		$this->assertArrayNotHasKey('design_system', $manifest['custom-round-trip']);
	}//end testAnExportedFileNamingAnUnknownDesignSystemRecordsNone()

	/**
	 * A file without the marker is an unknown document and is converted.
	 */
	public function testAFileWithoutTheMarkerIsConverted(): void {
		$this->uploadingAFile(content: ":root {
  --nldesign-color-primary: #00679e;
}
");
		$this->converter->expects($this->once())
			->method('convert')
			->willThrowException(new RuntimeException('converted', 422));

		$response = $this->controller->upload();

		$this->assertSame(422, $response->getStatus());
	}//end testAFileWithoutTheMarkerIsConverted()
}//end class
