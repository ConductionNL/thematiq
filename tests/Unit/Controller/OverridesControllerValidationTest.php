<?php

/**
 * Unit tests for what OverridesController::setOverrides() accepts and reports.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-the-server-checks-each-value-against-its-token-type
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\OverridesController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\OverridesStorage;
use OCA\Thematiq\Service\OverridesThemingSync;
use OCA\Thematiq\Service\OverridesWrites;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Tests\Unit\Support\FileList;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Thematiq#694: a save with an unknown token name or an unsafe value answered
 * 200 while the token was dropped, the audit entry listed tokens that never
 * reached the file, and a write failure returned the absolute file path.
 *
 * The overrides service here is the REAL CustomOverridesService writing to a
 * temporary app directory, so what the controller reports is compared with
 * what actually lands in custom-overrides.css.
 */
class OverridesControllerValidationTest extends TestCase {

	/**
	 * Temporary app root holding css/custom-overrides.css.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The real overrides service.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overridesService;

	/**
	 * The mocked audit service.
	 *
	 * @var ThemingAuditService&\PHPUnit\Framework\MockObject\MockObject
	 */
	private ThemingAuditService $auditService;

	/**
	 * The mocked request.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private IRequest $request;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-694-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css', 0777, true);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$parser = new CssParserService();
		$darkPalette = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: $parser,
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$this->overridesService = new CustomOverridesService(
			storage: new OverridesStorage(
				store: new DirectoryRuntimeFileStore(root: $appManager->getAppPath('thematiq')),
				cssParser: $parser,
				darkPalette: $darkPalette
			)
		);
		$this->overridesService->write(tokens: ['--color-primary' => '#000000']);

		$this->auditService = $this->createMock(originalClassName: ThemingAuditService::class);
		$this->request = $this->createMock(originalClassName: IRequest::class);
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (FileList::glob(pattern: $this->appDir . '/css/*') as $file) {
			unlink($file);
		}

		rmdir($this->appDir . '/css');
		rmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Build the controller around a given overrides service.
	 *
	 * @param CustomOverridesService $service The overrides service.
	 *
	 * @return OverridesController
	 */
	private function controller(CustomOverridesService $service): OverridesController {
		return new OverridesController(
			appName: 'thematiq',
			request: $this->request,
			writes: new OverridesWrites(
				overridesService: $service,
				cssParser: new CssParserService(),
				auditService: $this->auditService
			),
			themingSync: new OverridesThemingSync(
				config: $this->createMock(originalClassName: IConfig::class),
				themingService: $this->createMock(originalClassName: ThemingService::class)
			)
		);
	}//end controller()

	/**
	 * An unknown token name is refused with 400 naming it, and nothing is written.
	 *
	 * @return void
	 */
	public function testUnknownTokenIs400(): void {
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--color-primary' => '#112233', '--not-a-registry-token' => '#445566']]
		);
		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller(service: $this->overridesService)->setOverrides();

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertStringContainsString(needle: '--not-a-registry-token', haystack: json_encode($response->getData()));
		$this->assertSame(expected: ['--color-primary' => '#000000'], actual: $this->overridesService->read());
	}//end testUnknownTokenIs400()

	/**
	 * A value the writer would drop (a CSS injection attempt) is refused with 400
	 * naming the token, and nothing is written.
	 *
	 * @return void
	 */
	public function testWrongValueIs400(): void {
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--color-primary' => 'red; } body { display: none']]
		);
		$this->auditService->expects($this->never())->method('log');

		$response = $this->controller(service: $this->overridesService)->setOverrides();

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertStringContainsString(needle: '--color-primary', haystack: json_encode($response->getData()));
		$this->assertSame(expected: ['--color-primary' => '#000000'], actual: $this->overridesService->read());
	}//end testWrongValueIs400()

	/**
	 * A value that is not a string is refused with 400 instead of a type error.
	 *
	 * @return void
	 */
	public function testNonStringValueIs400(): void {
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--color-primary' => ['#112233']]]
		);

		$response = $this->controller(service: $this->overridesService)->setOverrides();

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: ['--color-primary' => '#000000'], actual: $this->overridesService->read());
	}//end testNonStringValueIs400()

	/**
	 * A valid save reports the count that reached the file, and the audit entry
	 * records exactly what was written.
	 *
	 * @return void
	 */
	public function testValidSaveReportsAndAuditsWhatWasWritten(): void {
		$this->request->method('getParams')->willReturn(
			['overrides' => ['--color-primary' => '#112233', '--color-primary-element' => '#445566']]
		);
		$this->auditService->expects($this->once())
			->method('log')
			->with(
				'overrides_written',
				[
					'old' => ['--color-primary' => '#000000'],
					'new' => ['--color-primary' => '#112233', '--color-primary-element' => '#445566'],
				]
			);

		$response = $this->controller(service: $this->overridesService)->setOverrides();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 2, actual: $response->getData()['written']);
		$this->assertSame(
			expected: ['--color-primary' => '#112233', '--color-primary-element' => '#445566'],
			actual: $this->overridesService->read()
		);
	}//end testValidSaveReportsAndAuditsWhatWasWritten()

	/**
	 * A write failure answers 500 with a generic message, never the file path
	 * the service puts in its exception text.
	 *
	 * @return void
	 */
	public function testWriteFailureHidesPath(): void {
		$failing = $this->getMockBuilder(className: CustomOverridesService::class)
			->disableOriginalConstructor()
			->onlyMethods(['read', 'write', 'findRejected'])
			->getMock();
		$failing->method('read')->willReturn([]);
		$failing->method('findRejected')->willReturn([]);
		$failing->method('write')->willThrowException(
			new \RuntimeException('Could not write /var/www/html/custom_apps/thematiq/css/custom-overrides.css.tmp.')
		);
		$this->request->method('getParams')->willReturn(['overrides' => ['--color-primary' => '#112233']]);

		$response = $this->controller(service: $failing)->setOverrides();

		$this->assertSame(expected: 500, actual: $response->getStatus());
		$this->assertStringNotContainsString(needle: '/var/www', haystack: json_encode($response->getData()));
		$this->assertStringNotContainsString(needle: 'custom-overrides.css', haystack: json_encode($response->getData()));
	}//end testWriteFailureHidesPath()
	/**
	 * Every refusal of an upload, with its status and message, and what passes on to the parser.
	 *
	 * @return void
	 */
	public function testEachUploadRefusal(): void {
		$css = (string)tempnam(sys_get_temp_dir(), 'thematiq-upload-');
		file_put_contents($css, ":root {\n  --nldesign-color-primary: #154273;\n}\n");
		$png = (string)tempnam(sys_get_temp_dir(), 'thematiq-upload-');
		file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
		$empty = (string)tempnam(sys_get_temp_dir(), 'thematiq-upload-');
		file_put_contents($empty, '/* nothing */');

		$cases = [
			'no file' => [null, 400, 'No file uploaded'],
			'no temp name' => [['name' => 'a.css', 'size' => 1], 400, 'No file uploaded'],
			'one byte too large' => [['tmp_name' => $css, 'name' => 'a.css', 'size' => (256 * 1024 + 1)], 413, 'File exceeds the 256 KB size limit'],
			'no name' => [['tmp_name' => $css, 'size' => 1], 415, 'Only .css files are accepted'],
			'not css by its name' => [['tmp_name' => $css, 'name' => 'a.css.txt', 'size' => 1], 415, 'Only .css files are accepted'],
			'an image named css' => [['tmp_name' => $png, 'name' => 'a.css', 'size' => 1], 415, 'File does not appear to be a CSS file'],
			'no declarations' => [['tmp_name' => $empty, 'name' => 'A.CSS', 'size' => (256 * 1024)], 400, 'No CSS custom property declarations found in the uploaded file'],
		];
		$answers = [];
		foreach ($cases as $name => [$file, $status, $error]) {
			$request = $this->createMock(originalClassName: IRequest::class);
			$request->method('getUploadedFile')->willReturn($file);
			$this->request = $request;
			$response = $this->controller(service: $this->overridesService)->importOverrides();
			$answers[$name] = [$response->getStatus(), ($response->getData()['error'] ?? null)];
			$this->assertSame(expected: [$status, $error], actual: $answers[$name], message: $name);
		}

		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getUploadedFile')->willReturn(['tmp_name' => $css, 'name' => 'a.Css', 'size' => 10]);
		$this->request = $request;
		$this->assertSame(expected: 200, actual: $this->controller(service: $this->overridesService)->importOverrides()->getStatus());

		unlink($css);
		unlink($png);
		unlink($empty);
	}//end testEachUploadRefusal()
}//end class
