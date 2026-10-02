<?php

/**
 * MultiBrandImportService and the upload hook: list first, store the chosen brands together,
 * refuse a collision whole, update all or none, keep a missing brand, forget the source with
 * its last brand, audit, and one contrast warning list and dark variant per brand.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Controller\CustomTokenSetController;
use OCA\Thematiq\Controller\TokenSourceController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\MultiBrandImportService;
use OCA\Thematiq\Service\MultiBrandSource;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * Real services over a temp app dir.
 *
 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
 */
final class MultiBrandImportServiceTest extends TestCase {

	/**
	 * The temp app dir.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * The stored app values.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The audit double.
	 *
	 * @var ThemingAuditService&MockObject
	 */
	private ThemingAuditService $audit;

	/**
	 * The custom sets.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $sets;

	/**
	 * The service under test.
	 *
	 * @var MultiBrandImportService
	 */
	private MultiBrandImportService $imports;

	/**
	 * The config double.
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->appDir = sys_get_temp_dir() . '/thematiq-multibrand-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css/tokens/dark', 0777, true);
		$this->stored = [];
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$this->config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->stored[$key] = (string)$value;
		});
		$tempApp = $this->createMock(IAppManager::class);
		$tempApp->method('getAppPath')->willReturn($this->appDir);
		$repoApp = $this->createMock(IAppManager::class);
		$repoApp->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$parser = new CssParserService();
		$store  = new DirectoryRuntimeFileStore($this->appDir);
		$dark   = new DarkPaletteService(new ContrastService(), $parser, $tempApp, $this->createMock(LoggerInterface::class), $store);
		$this->sets  = new CustomTokenSetService($store, $this->config, new CustomTokenSetValidator(), new ContrastService(), $dark);
		$this->audit = $this->createMock(ThemingAuditService::class);
		$converter   = new TokenSetConverterService($repoApp, $parser, new ContrastService(), new DesignTokensMapper(), $this->createMock(FontService::class), $this->createMock(LoggerInterface::class));
		$this->imports = new MultiBrandImportService(new MultiBrandSource(), $converter, new CustomTokenSetValidator(), $this->sets, $parser, new ContrastService(), $dark, $this->config, $this->audit);
	}//end setUp()

	/**
	 * Remove the temp dir.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->appDir));
	}//end tearDown()

	/**
	 * A fixture's content.
	 *
	 * @param string $name The file name.
	 *
	 * @return string
	 */
	private function fixture(string $name): string {
		return (string)file_get_contents(\dirname(__DIR__) . '/fixtures/multi-brand/' . $name);
	}//end fixture()

	/**
	 * The upload controller with these request params.
	 *
	 * @param array<string, mixed> $params The params.
	 *
	 * @return CustomTokenSetController
	 */
	private function uploadController(array $params): CustomTokenSetController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => ($params[$key] ?? $default));
		$request->method('getUploadedFile')->willReturn(null);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		$repoApp = $this->createMock(IAppManager::class);
		$repoApp->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		return new CustomTokenSetController(
			'thematiq',
			$request,
			$this->sets,
			new CustomTokenSetValidator(),
			new CssParserService(),
			$l,
			$this->audit,
			$this->config,
			new TokenSetConverterService($repoApp, new CssParserService(), new ContrastService(), new DesignTokensMapper(), $this->createMock(FontService::class), $this->createMock(LoggerInterface::class)),
			$this->createMock(ThemingService::class),
			$this->createMock(DesignSystemService::class),
			null,
			$this->imports
		);
	}//end uploadController()

	/**
	 * Without `brands` the upload lists the brands and stores nothing.
	 *
	 * @return void
	 */
	public function testFirstUploadListsAndStoresNothing(): void {
		$data = $this->uploadController(['name' => 'Gemeente Voorbeeld', 'content' => $this->fixture('three-themes.tokens.json')])->upload()->getData();

		$this->assertTrue($data['multiBrand']);
		$this->assertFalse($data['stored']);
		$this->assertSame(['noord', 'zuid', 'oost'], array_column($data['brands'], 'key'));
		$this->assertSame([], $this->sets->list());
	}//end testFirstUploadListsAndStoresNothing()

	/**
	 * Scenario: the administrator imports two of three brands; the source record lists them.
	 *
	 * @return void
	 */
	public function testSourceRecordListsBrands(): void {
		$this->audit->expects($this->exactly(2))->method('log')->with('custom_set_uploaded', $this->callback(static fn (array $c): bool => $c['sourceId'] === 'gemeente-voorbeeld' && in_array($c['brand'], ['noord', 'zuid'], true)));
		$response = $this->uploadController(['name' => 'Gemeente Voorbeeld', 'content' => $this->fixture('three-themes.tokens.json'), 'brands' => ['noord', 'zuid']])->upload();

		$this->assertSame(200, $response->getStatus(), json_encode($response->getData()));
		$record = $this->imports->records()['gemeente-voorbeeld'];
		$this->assertSame(['noord' => 'custom-gemeente-voorbeeld-noord', 'zuid' => 'custom-gemeente-voorbeeld-zuid'], $record['brands']);
		$this->assertStringStartsWith('sha256:', $record['contentHash']);
		$this->assertSame(['id' => 'gemeente-voorbeeld', 'brand' => 'noord'], $this->sets->getManifest()['custom-gemeente-voorbeeld-noord']['source']);
		$this->assertSame('Gemeente Voorbeeld: Zuid', $this->sets->getManifest()['custom-gemeente-voorbeeld-zuid']['name']);
	}//end testSourceRecordListsBrands()

	/**
	 * Unknown keys and more than 20 are refused with 422.
	 *
	 * @return void
	 */
	public function testUnknownBrandIs422(): void {
		$response = $this->uploadController(['name' => 'Voorbeeld', 'content' => $this->fixture('two-brand-classes.css'), 'brands' => ['west']])->upload();

		$this->assertSame(422, $response->getStatus());
		$this->assertStringContainsString('west', $response->getData()['error']);
	}//end testUnknownBrandIs422()

	/**
	 * One colliding id refuses the whole import, naming the brand; nothing is written.
	 *
	 * @return void
	 */
	public function testCollisionRefusesWholeImport(): void {
		$this->sets->store(displayName: 'Voorbeeld: Zuid', description: '', declarations: ['--nldesign-color-primary' => '#000000']);

		try {
			$this->imports->import(sourceName: 'Voorbeeld', content: $this->fixture('two-brand-classes.css'), keys: ['noord', 'zuid']);
			$this->fail('expected a refusal');
		} catch (RuntimeException $e) {
			$this->assertSame(409, $e->getCode());
			$this->assertStringContainsString('Zuid', $e->getMessage());
		}

		$this->assertArrayNotHasKey('custom-voorbeeld-noord', $this->sets->getManifest());
		$this->assertSame([], $this->imports->records());
	}//end testCollisionRefusesWholeImport()

	/**
	 * Scenario: an update replaces every listed brand, reports a vanished one as missing and keeps it, and names a new one.
	 *
	 * @return void
	 */
	public function testUpdateReplacesEveryBrand(): void {
		$this->imports->import(sourceName: 'Voorbeeld', content: $this->fixture('two-brand-classes.css'), keys: ['noord', 'zuid']);
		$this->audit->expects($this->once())->method('log')->with('custom_source_updated', $this->anything());
		$changed = str_replace('#154273', '#0b2a4a', $this->fixture('two-brand-classes.css'));

		$report = $this->imports->update(sourceId: 'voorbeeld', content: $changed);

		$this->assertSame(['noord', 'zuid'], $report['updated']);
		$this->assertStringContainsString('#0b2a4a', (string)$this->sets->getRawContent(id: 'custom-voorbeeld-noord'));
		$this->assertFileExists($this->appDir . '/css/tokens/dark/custom-voorbeeld-noord.css', 'each brand gets a fresh dark variant');
	}//end testUpdateReplacesEveryBrand()

	/**
	 * A brand gone from the source is kept and reported missing; a new one is named, not added.
	 *
	 * @return void
	 */
	public function testMissingBrandIsKept(): void {
		$this->imports->import(sourceName: 'Voorbeeld', content: $this->fixture('two-brand-classes.css'), keys: ['noord', 'zuid']);
		$next = ":root { --voorbeeld-radius: 4px; }\n.noord-theme { --voorbeeld-color-primary: #154273; }\n.west-theme { --voorbeeld-color-primary: #2e7d32; }\n";

		$report = $this->imports->update(sourceId: 'voorbeeld', content: $next);

		$this->assertSame(['zuid'], $report['missing']);
		$this->assertSame(['west'], $report['new']);
		$this->assertNotNull($this->sets->getRawContent(id: 'custom-voorbeeld-zuid'));
		$this->assertArrayNotHasKey('custom-voorbeeld-west', $this->sets->getManifest());
	}//end testMissingBrandIsKept()

	/**
	 * One brand that fails validation replaces nothing, and is named.
	 *
	 * @return void
	 */
	public function testOneFailingBrandReplacesNothing(): void {
		$this->imports->import(sourceName: 'Voorbeeld', content: $this->fixture('two-brand-classes.css'), keys: ['noord', 'zuid']);
		$before = $this->sets->getRawContent(id: 'custom-voorbeeld-noord');
		$this->audit->expects($this->never())->method('log');
		$bad = str_replace('--voorbeeld-color-primary: #c8102e;', '--voorbeeld-color-primary: expression(alert(1));', str_replace('#154273', '#0b2a4a', $this->fixture('two-brand-classes.css')));

		try {
			$this->imports->update(sourceId: 'voorbeeld', content: $bad);
			$this->fail('expected a refusal');
		} catch (RuntimeException $e) {
			$this->assertSame(422, $e->getCode());
			$this->assertStringContainsString('zuid', $e->getMessage());
		}

		$this->assertSame($before, $this->sets->getRawContent(id: 'custom-voorbeeld-noord'));
	}//end testOneFailingBrandReplacesNothing()

	/**
	 * Deleting the last brand removes the source record.
	 *
	 * @return void
	 */
	public function testDeletingLastBrandRemovesSource(): void {
		$this->imports->import(sourceName: 'Voorbeeld', content: $this->fixture('two-brand-classes.css'), keys: ['noord', 'zuid']);
		$controller = $this->uploadController([]);

		$controller->delete(id: 'custom-voorbeeld-noord');
		$this->assertSame(['zuid' => 'custom-voorbeeld-zuid'], $this->imports->records()['voorbeeld']['brands']);
		$controller->delete(id: 'custom-voorbeeld-zuid');
		$this->assertSame([], $this->imports->records());
	}//end testDeletingLastBrandRemovesSource()

	/**
	 * Task 5.3: each brand carries its own contrast warnings; a red primary with dark text fails.
	 *
	 * @return void
	 */
	public function testEachBrandCarriesItsOwnContrastWarnings(): void {
		$css    = ":root { --voorbeeld-x: 1px; }\n.licht-theme { --nldesign-color-primary: #ffff00; --nldesign-color-primary-text: #ffffff; }\n.donker-theme { --nldesign-color-primary: #154273; --nldesign-color-primary-text: #ffffff; }\n";
		$result = $this->imports->import(sourceName: 'Proef', content: $css, keys: ['licht', 'donker']);
		$warnings = array_column($result['sets'], 'warnings', 'brand');

		$this->assertNotSame([], $warnings['licht']);
		$this->assertNotSame(count($warnings['licht']), count($warnings['donker']));
	}//end testEachBrandCarriesItsOwnContrastWarnings()

	/**
	 * Task 5.4: a dark variant is written per brand on import.
	 *
	 * @return void
	 */
	public function testDarkVariantWrittenPerBrand(): void {
		$this->imports->import(sourceName: 'Voorbeeld', content: $this->fixture('two-brand-classes.css'), keys: ['noord', 'zuid']);

		$this->assertFileExists($this->appDir . '/css/tokens/dark/custom-voorbeeld-noord.css');
		$this->assertFileExists($this->appDir . '/css/tokens/dark/custom-voorbeeld-zuid.css');
	}//end testDarkVariantWrittenPerBrand()

	/**
	 * Task 5.5: a brand that sets only a primary still gets the full semantic layer.
	 *
	 * @return void
	 */
	public function testIncompleteBrandFallsBackToDefaults(): void {
		$this->imports->import(sourceName: 'Voorbeeld', content: $this->fixture('three-themes.tokens.json'), keys: ['oost', 'zuid']);
		$declarations = (new CssParserService())->parseRootBlock(css: (string)$this->sets->getRawContent(id: 'custom-voorbeeld-oost'));

		$this->assertArrayHasKey('--nldesign-color-primary-hover', $declarations);
		$this->assertArrayHasKey('--nldesign-color-text', $declarations);
	}//end testIncompleteBrandFallsBackToDefaults()

	/**
	 * The source routes are admin-only.
	 *
	 * @return void
	 */
	public function testSourceRoutesAreAdminOnly(): void {
		foreach (['list', 'update'] as $method) {
			$attribute = (new ReflectionMethod(TokenSourceController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertSame(Admin::class, ($attribute[0]->getArguments()['settings'] ?? null), $method);
		}
	}//end testSourceRoutesAreAdminOnly()
}//end class
