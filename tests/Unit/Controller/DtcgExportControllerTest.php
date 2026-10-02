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
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
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
	 * @return DtcgExportController
	 */
	private function controller(): DtcgExportController {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$appManager->method('getAppVersion')->willReturn('9.9.9');
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$cache = $this->createMock(ICacheFactory::class);
		$cache->method('createDistributed')->willReturn($this->createMock(ICache::class));
		$parser    = new CssParserService();
		$tokenSets = new TokenSetService($appManager, $config, $this->createMock(LoggerInterface::class), new ShippedTokenSetAuditService(new ContrastService(), $parser), $cache, new TokenSetVocabularyAuditService($parser));
		$l         = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		return new DtcgExportController('thematiq', $this->createMock(IRequest::class), $tokenSets, $appManager, $parser, new DesignTokensWriter(), new DeprecationRecords($config), $l);
	}//end controller()

	/**
	 * Scenario: an administrator downloads a shipped set.
	 *
	 * @return void
	 */
	public function testExportDtcgShippedSet(): void {
		$response = $this->controller()->export(id: 'amsterdam');
		$document = json_decode((string)json_encode($response->getData()), true);

		$this->assertSame(200, $response->getStatus());
		// Read the headers as set: getHeaders() asks the server container, absent in a bare checkout.
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($response);
		$this->assertSame('attachment; filename="amsterdam.tokens.json"', $headers['Content-Disposition']);
		$this->assertStringContainsString('application/json', $headers['Content-Type']);
		$this->assertSame('amsterdam', $document['$extensions']['nl.conduction.thematiq']['setId']);
		$this->assertSame('9.9.9', $document['$extensions']['nl.conduction.thematiq']['appVersion']);
		$this->assertArrayHasKey('$type', $document['nldesign']['color']['primary']);
		$this->assertArrayHasKey('$value', $document['nldesign']['color']['primary']);
	}//end testExportDtcgShippedSet()

	/**
	 * Scenario: defaults and overrides are left out; only the set's own declarations.
	 *
	 * @return void
	 */
	public function testOnlyTheSetsOwnDeclarations(): void {
		$own      = (new CssParserService())->parseRootBlock(css: (string)file_get_contents(\dirname(__DIR__, 3) . '/css/tokens/amsterdam.css'));
		$document = json_decode((string)json_encode($this->controller()->export(id: 'amsterdam')->getData()), true);
		$names    = array_keys($document['$extensions']['nl.conduction.thematiq']['cssOnly']);
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

		$this->assertSame($expected, $names);
	}//end testOnlyTheSetsOwnDeclarations()

	/**
	 * Scenario: an unknown set is refused with 404 and a generic message.
	 *
	 * @return void
	 */
	public function testExportDtcgUnknownIs404(): void {
		$response = $this->controller()->export(id: 'does-not-exist');

		$this->assertSame(404, $response->getStatus());
		$this->assertSame(['error' => 'Token set not found.'], $response->getData());
	}//end testExportDtcgUnknownIs404()

	/**
	 * Task 6.7: a traversal id never reads a file outside css/tokens.
	 *
	 * @return void
	 */
	public function testExportDtcgRejectsTraversalId(): void {
		foreach (['../appinfo/info', '..', 'amsterdam/../../appinfo/info', 'Amsterdam'] as $id) {
			$this->assertSame(404, $this->controller()->export(id: $id)->getStatus(), $id);
		}
	}//end testExportDtcgRejectsTraversalId()

	/**
	 * The endpoint is admin-only.
	 *
	 * @return void
	 */
	public function testAdminOnly(): void {
		$method = new ReflectionMethod(DtcgExportController::class, 'export');

		$this->assertSame(Admin::class, ($method->getAttributes(AuthorizedAdminSetting::class)[0]->getArguments()['settings'] ?? null));
		$this->assertSame([], $method->getAttributes(NoAdminRequired::class));
	}//end testAdminOnly()

	/**
	 * Lifecycle task 3.5: a recorded deprecation reaches the download.
	 *
	 * @return void
	 */
	public function testDeprecationReachesTheDownload(): void {
		$this->stored[DeprecationRecords::CONFIG_KEY] = (string)json_encode(['--nldesign-color-primary' => ['severity' => 'warning', 'message' => 'Moving to the new palette', 'state' => 'active']]);
		$document = json_decode((string)json_encode($this->controller()->export(id: 'amsterdam')->getData()), true);

		$this->assertSame('Moving to the new palette', $document['nldesign']['color']['primary']['$deprecated']);
	}//end testDeprecationReachesTheDownload()
}//end class
