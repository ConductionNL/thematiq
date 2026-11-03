<?php

/**
 * DtcgExportController: a shipped set as a DTCG download, 404 for an unknown or traversal
 * id, admin-only, and the set's deprecations written into the document.
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
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-any-token-set-can-be-downloaded-as-a-dtcg-document
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\DtcgExportController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DeprecationRecords;
use OCA\Thematiq\Service\DesignTokensWriter;
use OCA\Thematiq\Service\DtcgExportTools;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetFileLocation;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Over this repo's shipped sets.
 *
 * @spec openspec/specs/token-set-dtcg-export/spec.md#requirement-any-token-set-can-be-downloaded-as-a-dtcg-document
 */
final class DtcgExportControllerTest extends TestCase {

	/**
	 * The stored app values.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The controller over the repository.
	 *
	 * @param string|null $format The `format` query parameter, or null for none.
	 *
	 * @return DtcgExportController
	 */
	private function controller(?string $format = null): DtcgExportController {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$appManager->method('getAppVersion')->willReturn('9.9.9');
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$cache = $this->createMock(originalClassName: ICacheFactory::class);
		$cache->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));
		$parser = new CssParserService();
		$tokenSets = new TokenSetService(appManager: $appManager, config: $config, logger: $this->createMock(originalClassName: LoggerInterface::class), audit: new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: $parser), cacheFactory: $cache, vocabularyAudit: new TokenSetVocabularyAuditService(parser: $parser));
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(static fn (string $key, $default = null) => $key === 'format' ? ($format ?? $default) : $default);

		return new DtcgExportController(
			appName: 'thematiq',
			request: $request,
			location: new TokenSetFileLocation(
				tokenSets: $tokenSets,
				appManager: $appManager
			),
			tools: new DtcgExportTools(
				cssParser: $parser,
				writer: new DesignTokensWriter(),
				deprecations: new DeprecationRecords(config: $config),
				l: $l
			)
		);
	}//end controller()

	/**
	 * Scenario: an administrator downloads a shipped set.
	 *
	 * @return void
	 */
	public function testExportDtcgShippedSet(): void {
		$response = $this->controller()->export(id: 'amsterdam');
		$document = json_decode((string)json_encode($response->getData()), true);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		// Read the headers as set: getHeaders() asks the server container, absent in a bare checkout.
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($response);
		$this->assertSame(expected: 'attachment; filename="amsterdam.tokens.json"', actual: $headers['Content-Disposition']);
		$this->assertStringContainsString(needle: 'application/json', haystack: $headers['Content-Type']);
		$this->assertSame(expected: 'amsterdam', actual: $document['$extensions']['nl.conduction.thematiq']['setId']);
		$this->assertSame(expected: '9.9.9', actual: $document['$extensions']['nl.conduction.thematiq']['appVersion']);
		$this->assertArrayHasKey(key: '$type', array: $document['nldesign']['color']['primary']);
		$this->assertArrayHasKey(key: '$value', array: $document['nldesign']['color']['primary']);
	}//end testExportDtcgShippedSet()

	/**
	 * Scenario: defaults and overrides are left out; only the set's own declarations.
	 *
	 * @return void
	 */
	public function testOnlyTheSetsOwnDeclarations(): void {
		$own = (new CssParserService())->parseRootBlock(css: (string)file_get_contents(\dirname(__DIR__, 3) . '/css/tokens/amsterdam.css'));
		$document = json_decode((string)json_encode($this->controller()->export(id: 'amsterdam')->getData()), true);
		$names = array_keys($document['$extensions']['nl.conduction.thematiq']['cssOnly']);
		array_walk_recursive(
			$document,
			static function ($value, $key) use (&$names): void {
				if ($key === 'cssVariable') {
					$names[] = $value;
				}
			}
		);
		sort($names);
		$expected = array_keys($own);
		sort($expected);

		$this->assertSame(expected: $expected, actual: $names);
	}//end testOnlyTheSetsOwnDeclarations()

	/**
	 * Scenario: the Figma variables format holds colours as text, at the same paths.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function testTheFigmaFormatWritesColoursAsText(): void {
		$response = $this->controller(format: 'figma')->export(id: 'amsterdam');
		$document = json_decode((string)json_encode($response->getData()), true);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'color', actual: $document['nldesign']['color']['primary']['$type']);
		$this->assertMatchesRegularExpression(pattern: '/^#[0-9a-f]{6}$/', string: $document['nldesign']['color']['primary']['$value']);
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($response);
		$this->assertSame(expected: 'attachment; filename="amsterdam.tokens.json"', actual: $headers['Content-Disposition']);
	}//end testTheFigmaFormatWritesColoursAsText()

	/**
	 * Scenario: a Tokens Studio file holds the set under its own key, with one theme that switches it on.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function testTheTokensStudioFormatsWrapTheSet(): void {
		$dollar = json_decode((string)json_encode($this->controller(format: 'tokens-studio')->export(id: 'amsterdam')->getData()), true);
		$legacyResponse = $this->controller(format: 'tokens-studio-legacy')->export(id: 'amsterdam');
		$legacy = json_decode((string)json_encode($legacyResponse->getData()), true);

		$this->assertSame(expected: ['tokenSetOrder' => ['amsterdam']], actual: $dollar['$metadata']);
		$this->assertSame(expected: 'amsterdam', actual: $dollar['$themes'][0]['id']);
		$this->assertSame(expected: ['amsterdam' => 'enabled'], actual: $dollar['$themes'][0]['selectedTokenSets']);
		$this->assertSame(expected: 'color', actual: $dollar['amsterdam']['nldesign']['color']['primary']['$type']);
		$this->assertIsString(actual: $dollar['amsterdam']['nldesign']['color']['primary']['$value']);
		$this->assertSame(
			expected: $dollar['amsterdam']['nldesign']['color']['primary']['$value'],
			actual: $legacy['amsterdam']['nldesign']['color']['primary']['value']
		);
		$this->assertSame(expected: 'color', actual: $legacy['amsterdam']['nldesign']['color']['primary']['type']);
		$this->assertArrayNotHasKey(key: '$value', array: $legacy['amsterdam']['nldesign']['color']['primary']);
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($legacyResponse);
		$this->assertSame(expected: 'attachment; filename="amsterdam.tokens-studio.json"', actual: $headers['Content-Disposition']);
	}//end testTheTokensStudioFormatsWrapTheSet()

	/**
	 * Scenario: a format the app does not write is refused.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/figma-token-files/spec.md#requirement-any-token-set-can-be-downloaded-for-figma
	 */
	public function testAnUnknownFormatIsRefused(): void {
		$response = $this->controller(format: 'sketch')->export(id: 'amsterdam');

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Unknown file format.'], actual: $response->getData());
	}//end testAnUnknownFormatIsRefused()

	/**
	 * Scenario: an unknown set is refused with 404 and a generic message.
	 *
	 * @return void
	 */
	public function testExportDtcgUnknownIs404(): void {
		$response = $this->controller()->export(id: 'does-not-exist');

		$this->assertSame(expected: 404, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Token set not found.'], actual: $response->getData());
	}//end testExportDtcgUnknownIs404()

	/**
	 * Task 6.7: a traversal id never reads a file outside css/tokens.
	 *
	 * @return void
	 */
	public function testExportDtcgRejectsTraversalId(): void {
		foreach (['../appinfo/info', '..', 'amsterdam/../../appinfo/info', 'Amsterdam'] as $id) {
			$this->assertSame(expected: 404, actual: $this->controller()->export(id: $id)->getStatus(), message: $id);
		}
	}//end testExportDtcgRejectsTraversalId()

	/**
	 * The endpoint is admin-only.
	 *
	 * @return void
	 */
	public function testAdminOnly(): void {
		$method = new ReflectionMethod(DtcgExportController::class, 'export');

		$this->assertSame(expected: Admin::class, actual: ($method->getAttributes(AuthorizedAdminSetting::class)[0]->getArguments()['settings'] ?? null));
		$this->assertSame(expected: [], actual: $method->getAttributes(NoAdminRequired::class));
	}//end testAdminOnly()

	/**
	 * Lifecycle task 3.5: a recorded deprecation reaches the download.
	 *
	 * @return void
	 */
	public function testDeprecationReachesTheDownload(): void {
		$this->stored[DeprecationRecords::CONFIG_KEY] = (string)json_encode(['--nldesign-color-primary' => ['severity' => 'warning', 'message' => 'Moving to the new palette', 'state' => 'active']]);
		$document = json_decode((string)json_encode($this->controller()->export(id: 'amsterdam')->getData()), true);

		$this->assertSame(expected: 'Moving to the new palette', actual: $document['nldesign']['color']['primary']['$deprecated']);
	}//end testDeprecationReachesTheDownload()

	/**
	 * Scenario: an administrator downloads a custom set from its row; uploaded sets live in the runtime store.
	 *
	 * @return void
	 */
	public function testExportsACustomSetFromTheStore(): void {
		$dir = sys_get_temp_dir() . '/thematiq-dtcg-store-' . bin2hex(random_bytes(4));
		mkdir($dir . '/css/tokens', 0777, true);
		file_put_contents($dir . '/css/tokens/custom-gemeente-voorbeeld.css', ":root {\n  --nldesign-color-primary: #c8102e;\n}\n");
		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturn(true);
		$tokenSets->method('getAvailableTokenSets')->willReturn([['id' => 'custom-gemeente-voorbeeld', 'name' => 'Gemeente Voorbeeld']]);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$appManager->method('getAppVersion')->willReturn('9.9.9');
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnArgument(0);
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnArgument(2);
		$controller = new DtcgExportController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			location: new TokenSetFileLocation(
				tokenSets: $tokenSets,
				appManager: $appManager,
				store: new DirectoryRuntimeFileStore(root: $dir)
			),
			tools: new DtcgExportTools(
				cssParser: new CssParserService(),
				writer: new DesignTokensWriter(),
				deprecations: new DeprecationRecords(config: $config),
				l: $l
			)
		);

		$document = json_decode((string)json_encode($controller->export(id: 'custom-gemeente-voorbeeld')->getData()), true);
		exec('rm -rf ' . escapeshellarg($dir));

		$this->assertSame(expected: '#c8102e', actual: $document['nldesign']['color']['primary']['$value']['hex']);
		$this->assertSame(expected: 'Gemeente Voorbeeld', actual: $document['$description']);
	}//end testExportsACustomSetFromTheStore()
}//end class
