<?php

/**
 * Tests for OpenProjectSetFileController.
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
 * @spec openspec/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\OpenProjectSetFileController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\OpenProjectSetFileOutput;
use OCA\Thematiq\Service\OpenProjectSetFileWriter;
use OCA\Thematiq\Service\ShellCatalogueService;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetAudits;
use OCA\Thematiq\Service\TokenSetFileLocation;
use OCA\Thematiq\Service\TokenSetFiles;
use OCA\Thematiq\Service\TokenSetRuntime;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
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
 * @spec openspec/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
 */
final class OpenProjectSetFileControllerTest extends TestCase {

	/**
	 * The controller over the repository.
	 *
	 * @return OpenProjectSetFileController
	 */
	private function controller(): OpenProjectSetFileController {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(static fn (string $app, string $key, $default = '') => $default);
		$cache = $this->createMock(originalClassName: ICacheFactory::class);
		$cache->method('createDistributed')->willReturn($this->createMock(originalClassName: ICache::class));
		$parser = new CssParserService();
		$audit = new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: $parser);
		$tokenSets = new TokenSetService(
			setFiles: new TokenSetFiles(appManager: $appManager),
			runtime: new TokenSetRuntime(config: $config, cacheFactory: $cache, logger: $this->createMock(originalClassName: LoggerInterface::class)),
			audits: new TokenSetAudits(audit: $audit, vocabularyAudit: new TokenSetVocabularyAuditService(parser: $parser))
		);
		$writer = new OpenProjectSetFileWriter(catalogue: new ShellCatalogueService(
			declarations: $audit,
			mapper: new ShellRoleMapper(colours: new ContrastService())
		));
		$l = $this->createMock(originalClassName: IL10N::class);
		$l->method('t')->willReturnArgument(0);

		return new OpenProjectSetFileController(
			appName: 'thematiq',
			request: $this->createMock(originalClassName: IRequest::class),
			location: new TokenSetFileLocation(
				tokenSets: $tokenSets,
				appManager: $appManager
			),
			output: new OpenProjectSetFileOutput(
				writer: $writer,
				l: $l
			)
		);
	}//end controller()

	/**
	 * Scenario: the set file of a shipped set holds the roles, the logo verdict and the dark roles.
	 *
	 * @return void
	 */
	public function testTheSetFileHoldsTheRolesAndTheLogo(): void {
		$response = $this->controller()->export(id: 'cunningham');
		$file = json_decode((string)json_encode($response->getData()), true);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 'cunningham', actual: $file['id']);
		$this->assertSame(expected: '#1f883d', actual: $file['tokens']['action']);
		$this->assertCount(expectedCount: 19, haystack: $file['tokens']);
		$this->assertArrayHasKey(key: 'redistributable', array: $file['logo']);
		$this->assertArrayHasKey(key: 'tokens', array: $file['dark']);
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($response);
		$this->assertSame(expected: 'attachment; filename="thematiq-cunningham.json"', actual: $headers['Content-Disposition']);
	}//end testTheSetFileHoldsTheRolesAndTheLogo()

	/**
	 * Scenario: an unknown or malformed id is refused with 404.
	 *
	 * @return void
	 */
	public function testAnUnknownSetIs404(): void {
		$this->assertSame(expected: 404, actual: $this->controller()->export(id: 'does-not-exist')->getStatus());
		$this->assertSame(expected: 404, actual: $this->controller()->export(id: '../token-sets')->getStatus());
	}//end testAnUnknownSetIs404()

	/**
	 * Scenario: only an administrator may download it.
	 *
	 * @return void
	 */
	public function testAdminOnly(): void {
		$attributes = (new ReflectionMethod(OpenProjectSetFileController::class, 'export'))->getAttributes(AuthorizedAdminSetting::class);

		$this->assertCount(expectedCount: 1, haystack: $attributes);
		$this->assertSame(expected: Admin::class, actual: $attributes[0]->getArguments()['settings']);
	}//end testAdminOnly()
}//end class
