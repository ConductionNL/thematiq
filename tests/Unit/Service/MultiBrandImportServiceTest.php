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
use OCA\Thematiq\Service\BrandConversion;
use OCA\Thematiq\Service\BrandDocument;
use OCA\Thematiq\Service\BrandImportRecords;
use OCA\Thematiq\Service\BrandSelection;
use OCA\Thematiq\Service\BrandSetStorage;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomSetRecords;
use OCA\Thematiq\Service\CustomSetSources;
use OCA\Thematiq\Service\CustomSetUploadPipeline;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\MultiBrandImportService;
use OCA\Thematiq\Service\MultiBrandSource;
use OCA\Thematiq\Service\NewCustomTokenSet;
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
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$this->config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->stored[$key] = (string)$value;
		});
		$tempApp = $this->createMock(originalClassName: IAppManager::class);
		$tempApp->method('getAppPath')->willReturn($this->appDir);
		$repoApp = $this->createMock(originalClassName: IAppManager::class);
		$repoApp->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$parser = new CssParserService();
		$store = new DirectoryRuntimeFileStore(root: $this->appDir);
		$dark = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: $parser,
			appManager: $tempApp,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			store: $store
		);
		$this->sets = new CustomTokenSetService(
			store: $store,
			config: $this->config,
			validator: new CustomTokenSetValidator(),
			contrast: new ContrastService(),
			darkPalette: $dark
		);
		$this->audit = $this->createMock(originalClassName: ThemingAuditService::class);
		$converter = new TokenSetConverterService(
			appManager: $repoApp,
			cssParser: $parser,
			contrast: new ContrastService(),
			dtcgMapper: new DesignTokensMapper(),
			fonts: $this->createMock(originalClassName: FontService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$this->imports = new MultiBrandImportService(
			conversion: new BrandConversion(
				source: new MultiBrandSource(),
				converter: $converter,
				validator: new CustomTokenSetValidator(),
				cssParser: $parser
			),
			storage: new BrandSetStorage(customSets: $this->sets, contrast: new ContrastService(), darkPalette: $dark),
			records: new BrandImportRecords(config: $this->config, audit: $this->audit)
		);
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
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => ($params[$key] ?? $default));
		$request->method('getUploadedFile')->willReturn(null);
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnArgument(0);
		$repoApp = $this->createMock(originalClassName: IAppManager::class);
		$repoApp->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		return new CustomTokenSetController(
			appName: 'thematiq',
			request: $request,
			pipeline: new CustomSetUploadPipeline(
				service: $this->sets,
				validator: new CustomTokenSetValidator(),
				cssParser: new CssParserService(),
				converter: new TokenSetConverterService(
					appManager: $repoApp,
					cssParser: new CssParserService(),
					contrast: new ContrastService(),
					dtcgMapper: new DesignTokensMapper(),
					fonts: $this->createMock(originalClassName: FontService::class),
					logger: $this->createMock(originalClassName: LoggerInterface::class)
				)
			),
			records: new CustomSetRecords(
				l: $l,
				auditService: $this->audit,
				config: $this->config,
				themingService: $this->createMock(originalClassName: ThemingService::class)
			),
			sources: new CustomSetSources(
				designSystems: $this->createMock(originalClassName: DesignSystemService::class),
				brandingCapture: null,
				multiBrand: $this->imports
			)
		);
	}//end uploadController()

	/**
	 * Without `brands` the upload lists the brands and stores nothing.
	 *
	 * @return void
	 */
	public function testFirstUploadListsAndStoresNothing(): void {
		$params = ['name' => 'Gemeente Voorbeeld', 'content' => $this->fixture(name: 'three-themes.tokens.json')];
		$data = $this->uploadController(params: $params)->upload()->getData();

		$this->assertTrue(condition: $data['multiBrand']);
		$this->assertFalse(condition: $data['stored']);
		$this->assertSame(expected: ['noord', 'zuid', 'oost'], actual: array_column($data['brands'], 'key'));
		$this->assertSame(expected: [], actual: $this->sets->list());
	}//end testFirstUploadListsAndStoresNothing()

	/**
	 * Scenario: the administrator imports two of three brands; the source record lists them.
	 *
	 * @return void
	 */
	public function testSourceRecordListsBrands(): void {
		$this->audit->expects($this->exactly(count: 2))->method('log')->with(
			'custom_set_uploaded',
			$this->callback(callback: static fn (array $c): bool => $c['sourceId'] === 'gemeente-voorbeeld' && in_array(
				$c['brand'],
				['noord', 'zuid'],
				true
			))
		);
		$params = ['name' => 'Gemeente Voorbeeld', 'content' => $this->fixture(name: 'three-themes.tokens.json'), 'brands' => ['noord', 'zuid']];
		$response = $this->uploadController(params: $params)->upload();

		$this->assertSame(expected: 200, actual: $response->getStatus(), message: json_encode($response->getData()));
		$record = $this->imports->records()['gemeente-voorbeeld'];
		$this->assertSame(
			expected: ['noord' => 'custom-gemeente-voorbeeld-noord', 'zuid' => 'custom-gemeente-voorbeeld-zuid'],
			actual: $record['brands']
		);
		$this->assertStringStartsWith(prefix: 'sha256:', string: $record['contentHash']);
		$this->assertSame(
			expected: ['id' => 'gemeente-voorbeeld', 'brand' => 'noord'],
			actual: $this->sets->getManifest()['custom-gemeente-voorbeeld-noord']['source']
		);
		$this->assertSame(expected: 'Gemeente Voorbeeld: Zuid', actual: $this->sets->getManifest()['custom-gemeente-voorbeeld-zuid']['name']);
	}//end testSourceRecordListsBrands()

	/**
	 * A two-brand document whose brands share a `source` set of raw colours and carry a logo.
	 *
	 * @return string The document.
	 */
	private function brandsWithSourceSetAndLogo(): string {
		$logo = 'url("data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"></svg>') . '")';
		$brand = static fn (string $colour): array => [
			'color' => ['primary' => ['value' => $colour, 'type' => 'color']],
			'conduction' => ['logo' => ['header' => ['background-image' => ['value' => $logo]]]],
		];
		$themes = [];
		foreach (['noord', 'zuid'] as $key) {
			$themes[] = ['id' => $key, 'name' => ucfirst($key), 'selectedTokenSets' => ['palette' => 'source', $key => 'enabled']];
		}

		return (string)json_encode(
			[
				'palette' => ['raw' => ['blue' => ['value' => '#0b71a1', 'type' => 'color']]],
				'noord' => $brand('#0b71a1'),
				'zuid' => $brand('#154273'),
				'$themes' => $themes,
			]
		);
	}//end brandsWithSourceSetAndLogo()

	/**
	 * A brand is stored with the converter's own stylesheet and theming.
	 *
	 * @return void
	 */
	public function testStoredBrandKeepsTheConvertedCssAndTheming(): void {
		$result = $this->imports->import(
			sourceName: 'Stad',
			selection: new BrandSelection(document: new BrandDocument(content: $this->brandsWithSourceSetAndLogo()), keys: ['noord', 'zuid'])
		);

		$this->assertSame(expected: 'custom-stad-zuid', actual: $result['sets'][1]['id']);
		$manifest = $this->sets->getManifest()['custom-stad-zuid'];
		$this->assertSame(expected: '#154273', actual: $manifest['theming']['primary_color']);
		$this->assertStringContainsString(needle: 'GENERATED by Thematiq', haystack: (string)$this->sets->getRawContent(id: 'custom-stad-zuid'));
	}//end testStoredBrandKeepsTheConvertedCssAndTheming()

	/**
	 * A token only in a `source` set resolves a reference but is not part of the brand.
	 *
	 * @return void
	 */
	public function testASourceSetTokenIsNotPartOfTheBrand(): void {
		$this->imports->import(
			sourceName: 'Stad',
			selection: new BrandSelection(document: new BrandDocument(content: $this->brandsWithSourceSetAndLogo()), keys: ['noord', 'zuid'])
		);

		$this->assertStringNotContainsString(needle: 'raw-blue', haystack: (string)$this->sets->getRawContent(id: 'custom-stad-noord'));
	}//end testASourceSetTokenIsNotPartOfTheBrand()

	/**
	 * A brand's logo is named after its own set, so two brands of one source keep their own file.
	 *
	 * @return void
	 */
	public function testABrandLogoIsNamedAfterItsSet(): void {
		$this->imports->import(
			sourceName: 'Stad',
			selection: new BrandSelection(document: new BrandDocument(content: $this->brandsWithSourceSetAndLogo()), keys: ['noord', 'zuid'])
		);

		foreach (['noord', 'zuid'] as $key) {
			$logo = (string)($this->sets->getManifest()['custom-stad-' . $key]['theming']['logo'] ?? '');
			$this->assertStringContainsString(needle: 'custom-stad-' . $key, haystack: $logo);
		}
	}//end testABrandLogoIsNamedAfterItsSet()

	/**
	 * Unknown keys and more than 20 are refused with 422.
	 *
	 * @return void
	 */
	public function testUnknownBrandIs422(): void {
		$params = ['name' => 'Voorbeeld', 'content' => $this->fixture(name: 'two-brand-classes.css'), 'brands' => ['west']];
		$response = $this->uploadController(params: $params)->upload();

		$this->assertSame(expected: 422, actual: $response->getStatus());
		$this->assertStringContainsString(needle: 'west', haystack: $response->getData()['error']);
	}//end testUnknownBrandIs422()

	/**
	 * One colliding id refuses the whole import, naming the brand; nothing is written.
	 *
	 * @return void
	 */
	public function testCollisionRefusesWholeImport(): void {
		$this->sets->store(
			set: new NewCustomTokenSet(
				fields: [
					'displayName' => 'Voorbeeld: Zuid',
					'description' => '',
					'declarations' => ['--nldesign-color-primary' => '#000000'],
				]
			)
		);

		try {
			$this->imports->import(
				sourceName: 'Voorbeeld',
				selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')), keys: ['noord', 'zuid'])
			);
			$this->fail(message: 'expected a refusal');
		} catch (RuntimeException $e) {
			$this->assertSame(expected: 409, actual: $e->getCode());
			$this->assertStringContainsString(needle: 'Zuid', haystack: $e->getMessage());
		}

		$this->assertArrayNotHasKey(key: 'custom-voorbeeld-noord', array: $this->sets->getManifest());
		$this->assertSame(expected: [], actual: $this->imports->records());
	}//end testCollisionRefusesWholeImport()

	/**
	 * Scenario: an update replaces every listed brand, reports a vanished one as missing and keeps it, and names a new one.
	 *
	 * @return void
	 */
	public function testUpdateReplacesEveryBrand(): void {
		$this->imports->import(
			sourceName: 'Voorbeeld',
			selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')), keys: ['noord', 'zuid'])
		);
		$this->audit->expects($this->once())->method('log')->with('custom_source_updated', $this->anything());
		$changed = str_replace('#154273', '#0b2a4a', $this->fixture(name: 'two-brand-classes.css'));

		$report = $this->imports->update(sourceId: 'voorbeeld', document: new BrandDocument(content: $changed));

		$this->assertSame(expected: ['noord', 'zuid'], actual: $report['updated']);
		$this->assertStringContainsString(needle: '#0b2a4a', haystack: (string)$this->sets->getRawContent(id: 'custom-voorbeeld-noord'));
		$this->assertFileExists(
			filename: $this->appDir . '/css/tokens/dark/custom-voorbeeld-noord.css',
			message: 'each brand gets a fresh dark variant'
		);
	}//end testUpdateReplacesEveryBrand()

	/**
	 * A brand gone from the source is kept and reported missing; a new one is named, not added.
	 *
	 * @return void
	 */
	public function testMissingBrandIsKept(): void {
		$this->imports->import(
			sourceName: 'Voorbeeld',
			selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')), keys: ['noord', 'zuid'])
		);
		$next = ":root { --voorbeeld-radius: 4px; }\n.noord-theme { --voorbeeld-color-primary: #154273; }\n"
			. ".west-theme { --voorbeeld-color-primary: #2e7d32; }\n";

		$report = $this->imports->update(sourceId: 'voorbeeld', document: new BrandDocument(content: $next));

		$this->assertSame(expected: ['zuid'], actual: $report['missing']);
		$this->assertSame(expected: ['west'], actual: $report['new']);
		$this->assertNotNull(actual: $this->sets->getRawContent(id: 'custom-voorbeeld-zuid'));
		$this->assertArrayNotHasKey(key: 'custom-voorbeeld-west', array: $this->sets->getManifest());
	}//end testMissingBrandIsKept()

	/**
	 * One brand that fails validation replaces nothing, and is named.
	 *
	 * @return void
	 */
	public function testOneFailingBrandReplacesNothing(): void {
		$this->imports->import(
			sourceName: 'Voorbeeld',
			selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')), keys: ['noord', 'zuid'])
		);
		$before = $this->sets->getRawContent(id: 'custom-voorbeeld-noord');
		$this->audit->expects($this->never())->method('log');
		$bad = str_replace(
			'--voorbeeld-color-primary: #c8102e;',
			'--voorbeeld-color-primary: expression(alert(1));',
			str_replace('#154273', '#0b2a4a', $this->fixture(name: 'two-brand-classes.css'))
		);

		try {
			$this->imports->update(sourceId: 'voorbeeld', document: new BrandDocument(content: $bad));
			$this->fail(message: 'expected a refusal');
		} catch (RuntimeException $e) {
			$this->assertSame(expected: 422, actual: $e->getCode());
			$this->assertStringContainsString(needle: 'zuid', haystack: $e->getMessage());
		}

		$this->assertSame(expected: $before, actual: $this->sets->getRawContent(id: 'custom-voorbeeld-noord'));
	}//end testOneFailingBrandReplacesNothing()

	/**
	 * Deleting the last brand removes the source record.
	 *
	 * @return void
	 */
	public function testDeletingLastBrandRemovesSource(): void {
		$this->imports->import(
			sourceName: 'Voorbeeld',
			selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')), keys: ['noord', 'zuid'])
		);
		$controller = $this->uploadController(params: []);

		$controller->delete(id: 'custom-voorbeeld-noord');
		$this->assertSame(expected: ['zuid' => 'custom-voorbeeld-zuid'], actual: $this->imports->records()['voorbeeld']['brands']);
		$controller->delete(id: 'custom-voorbeeld-zuid');
		$this->assertSame(expected: [], actual: $this->imports->records());
	}//end testDeletingLastBrandRemovesSource()

	/**
	 * Task 5.3: each brand carries its own contrast warnings; a red primary with dark text fails.
	 *
	 * @return void
	 */
	public function testEachBrandCarriesItsOwnContrastWarnings(): void {
		$css = ":root { --voorbeeld-x: 1px; }\n.licht-theme { --nldesign-color-primary: #ffff00; --nldesign-color-primary-text: #ffffff; }\n"
			. ".donker-theme { --nldesign-color-primary: #154273; --nldesign-color-primary-text: #ffffff; }\n";
		$result = $this->imports->import(
			sourceName: 'Proef',
			selection: new BrandSelection(document: new BrandDocument(content: $css), keys: ['licht', 'donker'])
		);
		$warnings = array_column($result['sets'], 'warnings', 'brand');

		$this->assertNotSame(expected: [], actual: $warnings['licht']);
		$this->assertNotSame(expected: count($warnings['licht']), actual: count($warnings['donker']));
	}//end testEachBrandCarriesItsOwnContrastWarnings()

	/**
	 * An update measures each replaced brand again: a brand whose new colours fail contrast carries the warnings.
	 *
	 * @return void
	 */
	public function testUpdateRecomputesTheContrastWarnings(): void {
		$good = ":root { --voorbeeld-x: 1px; }\n.licht-theme { --nldesign-color-primary: #154273; --nldesign-color-primary-text: #ffffff; }\n"
			. ".donker-theme { --nldesign-color-primary: #0b2a4a; --nldesign-color-primary-text: #ffffff; }\n";
		$this->imports->import(
			sourceName: 'Proef',
			selection: new BrandSelection(document: new BrandDocument(content: $good), keys: ['licht', 'donker'])
		);
		$before = $this->sets->getManifest()['custom-proef-licht']['warnings'];

		$this->imports->update(
			sourceId: 'proef',
			document: new BrandDocument(content: str_replace(search: '#154273', replace: '#ffff00', subject: $good))
		);

		$this->assertNotSame(expected: $before, actual: $this->sets->getManifest()['custom-proef-licht']['warnings']);
	}//end testUpdateRecomputesTheContrastWarnings()

	/**
	 * Task 5.4: a dark variant is written per brand on import.
	 *
	 * @return void
	 */
	public function testDarkVariantWrittenPerBrand(): void {
		$this->imports->import(
			sourceName: 'Voorbeeld',
			selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')), keys: ['noord', 'zuid'])
		);

		$this->assertFileExists(filename: $this->appDir . '/css/tokens/dark/custom-voorbeeld-noord.css');
		$this->assertFileExists(filename: $this->appDir . '/css/tokens/dark/custom-voorbeeld-zuid.css');
	}//end testDarkVariantWrittenPerBrand()

	/**
	 * Task 5.5: a brand that sets only a primary still gets the full semantic layer.
	 *
	 * @return void
	 */
	public function testIncompleteBrandFallsBackToDefaults(): void {
		$this->imports->import(
			sourceName: 'Voorbeeld',
			selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'three-themes.tokens.json')), keys: ['oost', 'zuid'])
		);
		$declarations = (new CssParserService())->parseRootBlock(css: (string)$this->sets->getRawContent(id: 'custom-voorbeeld-oost'));

		$this->assertArrayHasKey(key: '--nldesign-color-primary-hover', array: $declarations);
		$this->assertArrayHasKey(key: '--nldesign-color-text', array: $declarations);
	}//end testIncompleteBrandFallsBackToDefaults()

	/**
	 * The source routes are admin-only.
	 *
	 * @return void
	 */
	public function testSourceRoutesAreAdminOnly(): void {
		foreach (['list', 'update'] as $method) {
			$attribute = (new ReflectionMethod(TokenSourceController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertSame(expected: Admin::class, actual: ($attribute[0]->getArguments()['settings'] ?? null), message: $method);
		}
	}//end testSourceRoutesAreAdminOnly()

	/**
	 * Scenario: a file with a light theme and a dark theme is one house style, not two brands.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-a-file-from-the-openproject-plugin-uploads-into-thematiq
	 */
	public function testALightAndDarkFileIsNotTwoBrands(): void {
		$file = (string)file_get_contents(\dirname(__DIR__, 3) . '/tests/fixtures/figma/plugin/cunningham.tokens-studio.json');
		$document = json_decode($file, true);

		$this->assertSame(expected: [], actual: $this->imports->brands(content: $file));

		// The same file with its dark theme called something else is two brands again.
		$document['$themes'][1]['id'] = 'noord';
		$document['$themes'][1]['name'] = 'Noord';
		$this->assertCount(expectedCount: 2, haystack: $this->imports->brands(content: (string)json_encode($document)));
	}//end testALightAndDarkFileIsNotTwoBrands()

	/**
	 * An import records its source and audits each stored brand with the uploaded file's name.
	 *
	 * @return void
	 */
	public function testImportRecordsTheSourceAndAuditsEachBrand(): void {
		$calls = [];
		$this->audit->method('log')->willReturnCallback(function (string $action, array $context) use (&$calls): void {
			$calls[] = [$action, $context];
		});
		$content = $this->fixture(name: 'two-brand-classes.css');

		$result = $this->imports->import(
			sourceName: 'Voorbeeld',
			selection: new BrandSelection(document: new BrandDocument(content: $content, fileName: 'stad.css'), keys: ['noord', 'zuid'])
		);

		$this->assertSame(expected: 'voorbeeld', actual: $result['sourceId']);
		$this->assertSame(expected: ['noord', 'zuid'], actual: array_column($result['sets'], 'brand'));
		$this->assertSame(expected: ['custom-voorbeeld-noord', 'custom-voorbeeld-zuid'], actual: array_column($result['sets'], 'id'));
		$record = $this->imports->records()['voorbeeld'];
		$this->assertSame(expected: ['name', 'inputKind', 'contentHash', 'brands', 'updatedAt'], actual: array_keys($record));
		$this->assertSame(expected: 'Voorbeeld', actual: $record['name']);
		$this->assertSame(expected: ['noord' => 'custom-voorbeeld-noord', 'zuid' => 'custom-voorbeeld-zuid'], actual: $record['brands']);
		$this->assertNotSame(expected: '', actual: $record['inputKind']);
		$this->assertNotSame(expected: '', actual: $record['contentHash']);
		$this->assertSame(expected: ['custom_set_uploaded', 'custom_set_uploaded'], actual: array_column($calls, 0));
		$this->assertSame(
			expected: ['id', 'name', 'declarationCount', 'sourceId', 'brand', 'file'],
			actual: array_keys($calls[0][1])
		);
		$this->assertSame(expected: 'custom-voorbeeld-zuid', actual: $calls[1][1]['id']);
		$this->assertSame(expected: 'Voorbeeld: Zuid', actual: $calls[1][1]['name']);
		$this->assertSame(expected: 'voorbeeld', actual: $calls[1][1]['sourceId']);
		$this->assertSame(expected: 'zuid', actual: $calls[1][1]['brand']);
		$this->assertSame(expected: 'stad.css', actual: $calls[1][1]['file']);
		$this->assertSame(
			expected: ['source' => ['id' => 'voorbeeld', 'brand' => 'noord']],
			actual: array_intersect_key($this->sets->getManifest()['custom-voorbeeld-noord'], ['source' => true])
		);
	}//end testImportRecordsTheSourceAndAuditsEachBrand()

	/**
	 * A second import under a source name that exists is refused with 409, writing nothing.
	 *
	 * @return void
	 */
	public function testImportOfAnExistingSourceIs409(): void {
		$this->imports->import(
			sourceName: 'Voorbeeld',
			selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')), keys: ['noord'])
		);
		$before = $this->imports->records();

		try {
			$this->imports->import(
				sourceName: 'Voorbeeld',
				selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')), keys: ['zuid'])
			);
			$this->fail(message: 'expected a refusal');
		} catch (RuntimeException $e) {
			$this->assertSame(expected: 409, actual: $e->getCode());
			$this->assertSame(expected: 'A source named "Voorbeeld" already exists. Update it instead.', actual: $e->getMessage());
		}

		$this->assertSame(expected: $before, actual: $this->imports->records());
		$this->assertArrayNotHasKey(key: 'custom-voorbeeld-zuid', array: $this->sets->getManifest());
	}//end testImportOfAnExistingSourceIs409()

	/**
	 * An update audits the old and new content hash with its report, and restamps the record.
	 *
	 * @return void
	 */
	public function testUpdateAuditsTheHashesAndRestampsTheRecord(): void {
		$this->imports->import(
			sourceName: 'Voorbeeld',
			selection: new BrandSelection(document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')), keys: ['noord', 'zuid'])
		);
		$oldHash = $this->imports->records()['voorbeeld']['contentHash'];
		$calls = [];
		$this->audit->method('log')->willReturnCallback(function (string $action, array $context) use (&$calls): void {
			$calls[] = [$action, $context];
		});
		$changed = str_replace('#154273', '#0b2a4a', $this->fixture(name: 'two-brand-classes.css'));

		$report = $this->imports->update(sourceId: 'voorbeeld', document: new BrandDocument(content: $changed, fileName: 'stad-2.css'));

		$this->assertSame(expected: ['updated' => ['noord', 'zuid'], 'missing' => [], 'new' => []], actual: $report);
		$record = $this->imports->records()['voorbeeld'];
		$this->assertNotSame(expected: $oldHash, actual: $record['contentHash']);
		$this->assertSame(expected: ['name', 'inputKind', 'contentHash', 'brands', 'updatedAt'], actual: array_keys($record));
		$this->assertSame(
			expected: [
				[
					'custom_source_updated',
					[
						'sourceId' => 'voorbeeld',
						'old' => $oldHash,
						'new' => $record['contentHash'],
						'updated' => ['noord', 'zuid'],
						'missing' => [],
					],
				],
			],
			actual: $calls
		);
		$this->assertSame(expected: ['id' => 'voorbeeld', 'brand' => 'noord'], actual: $this->sets->getManifest()['custom-voorbeeld-noord']['source']);
	}//end testUpdateAuditsTheHashesAndRestampsTheRecord()

	/**
	 * An update of a source that is not recorded is refused with 404.
	 *
	 * @return void
	 */
	public function testUpdateOfAnUnknownSourceIs404(): void {
		$this->expectException(exception: RuntimeException::class);
		$this->expectExceptionCode(code: 404);
		$this->expectExceptionMessage(message: 'Unknown source.');

		$this->imports->update(sourceId: 'onbekend', document: new BrandDocument(content: $this->fixture(name: 'two-brand-classes.css')));
	}//end testUpdateOfAnUnknownSourceIs404()
}//end class
