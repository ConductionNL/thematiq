<?php

/**
 * OwnTokenController and TokenDeprecationController: admin-only, one audit entry per
 * successful change and none for a refused one, the file rewritten, generic error texts.
 * CatalogController::deprecations(): the public shape for consuming apps.
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
 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\CatalogController;
use OCA\Thematiq\Controller\OwnTokenController;
use OCA\Thematiq\Controller\TokenDeprecationController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DeprecationRecords;
use OCA\Thematiq\Service\OwnTokenService;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenDeprecationService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenValueValidator;
use OCA\Thematiq\Settings\Admin;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Real services over a temp app dir; the request, the audit trail and l10n are doubles.
 *
 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-adds-an-own-token-from-the-token-editor
 */
final class OwnTokenControllerTest extends TestCase {

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
	 * The own tokens.
	 *
	 * @var OwnTokenService
	 */
	private OwnTokenService $ownTokens;

	/**
	 * The deprecations.
	 *
	 * @var TokenDeprecationService
	 */
	private TokenDeprecationService $deprecations;

	/**
	 * The overrides.
	 *
	 * @var CustomOverridesService
	 */
	private CustomOverridesService $overrides;

	/**
	 * The audit double.
	 *
	 * @var ThemingAuditService&MockObject
	 */
	private ThemingAuditService $audit;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->appDir = sys_get_temp_dir() . '/thematiq-own-ctl-' . bin2hex(random_bytes(4));
		mkdir($this->appDir . '/css/systems/nldesign', 0777, true);
		copy(\dirname(__DIR__, 3) . '/css/systems/nldesign/defaults.css', $this->appDir . '/css/systems/nldesign/defaults.css');
		$this->stored = [];
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($this->stored[$key] ?? $default));
		$config->method('setAppValue')->willReturnCallback(function (string $app, string $key, $value): void {
			$this->stored[$key] = (string)$value;
		});
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);
		$parser = new CssParserService();
		$records = new DeprecationRecords($config);
		$this->ownTokens = new OwnTokenService($config, new TokenValueValidator(), $records);
		$this->deprecations = new TokenDeprecationService($records, $this->ownTokens, $appManager, $parser);
		$dark = new DarkPaletteService(new ContrastService(), $parser, $appManager, $this->createMock(LoggerInterface::class));
		$this->overrides = new CustomOverridesService(new DirectoryRuntimeFileStore($this->appDir), $parser, $dark, null, null, null, $this->ownTokens);
		$this->audit = $this->createMock(ThemingAuditService::class);
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
	 * A request double answering these params.
	 *
	 * @param array<string, mixed> $params The params.
	 *
	 * @return IRequest
	 */
	private function request(array $params): IRequest {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => ($params[$key] ?? $default));
		return $request;
	}//end request()

	/**
	 * An l10n double that returns the text.
	 *
	 * @return IL10N
	 */
	private function l10n(): IL10N {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		return $l;
	}//end l10n()

	/**
	 * The own token controller.
	 *
	 * @param array<string, mixed> $params The request params.
	 *
	 * @return OwnTokenController
	 */
	private function own(array $params = []): OwnTokenController {
		return new OwnTokenController('thematiq', $this->request($params), $this->ownTokens, $this->deprecations, $this->overrides, $this->audit, $this->l10n());
	}//end own()

	/**
	 * The deprecation controller.
	 *
	 * @param array<string, mixed> $params The request params.
	 *
	 * @return TokenDeprecationController
	 */
	private function deprecationController(array $params = []): TokenDeprecationController {
		return new TokenDeprecationController('thematiq', $this->request($params), $this->deprecations, $this->overrides, $this->audit, $this->l10n());
	}//end deprecationController()

	/**
	 * Every mutating and listing method is admin-only.
	 *
	 * @return void
	 */
	public function testEndpointsAreAdminOnly(): void {
		$methods = [
			OwnTokenController::class => ['list', 'create', 'update', 'delete'],
			TokenDeprecationController::class => ['list', 'save', 'delete', 'adopt'],
		];
		foreach ($methods as $class => $names) {
			foreach ($names as $method) {
				$attributes = (new ReflectionMethod($class, $method))->getAttributes(AuthorizedAdminSetting::class);
				$this->assertCount(1, $attributes, $class . '::' . $method);
				$this->assertSame(Admin::class, ($attributes[0]->getArguments()['settings'] ?? null));
				$this->assertSame([], (new ReflectionMethod($class, $method))->getAttributes(NoAdminRequired::class));
			}
		}
	}//end testEndpointsAreAdminOnly()

	/**
	 * Scenario: adding a brand accent stores it, writes it to the file and audits once.
	 *
	 * @return void
	 */
	public function testCreateWritesFileAndAuditsOnce(): void {
		$this->audit->expects($this->once())->method('log')->with('own_token_changed', $this->callback(fn (array $c): bool => $c['token'] === '--nldesign-org-brand-accent' && $c['old'] === null && $c['new']['value'] === '#e17000'));

		$response = $this->own(['slug' => 'brand-accent', 'label' => 'Brand accent', 'type' => 'color', 'value' => '#e17000'])->create();

		$this->assertSame(200, $response->getStatus());
		$this->assertStringContainsString('--nldesign-org-brand-accent: #e17000;', $this->overrides->getRawContent());
	}//end testCreateWritesFileAndAuditsOnce()

	/**
	 * Scenario: a name outside the rule gets 400 naming the rule, nothing stored, nothing audited.
	 *
	 * @return void
	 */
	public function testRefusedCreateIs400WithoutAudit(): void {
		$this->audit->expects($this->never())->method('log');

		$response = $this->own(['slug' => 'Brand_Accent', 'label' => 'x', 'type' => 'color', 'value' => '#000000'])->create();

		$this->assertSame(400, $response->getStatus());
		$this->assertSame('name', $response->getData()['field']);
		$this->assertStringContainsString('lowercase letters', $response->getData()['error']);
		$this->assertSame([], $this->ownTokens->list());
	}//end testRefusedCreateIs400WithoutAudit()

	/**
	 * Scenario: removing a deprecated token after its date drops it from the file and keeps the record as removed.
	 *
	 * @return void
	 */
	public function testRemoveKeepsDeprecationAsRemoved(): void {
		$this->ownTokens->create(input: ['slug' => 'old-accent', 'label' => 'Old', 'type' => 'color', 'value' => '#aa0000']);
		$this->deprecations->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'warning', 'removalDate' => '2026-10-02'], today: '2026-10-02');
		$this->overrides->rewriteAll();
		$this->overrides->ensureExists();

		$response = $this->own()->delete(name: '--nldesign-org-old-accent');

		$this->assertSame(200, $response->getStatus());
		$this->assertStringNotContainsString('--nldesign-org-old-accent', $this->overrides->getRawContent());
		$this->assertSame('removed', $this->deprecations->list()['--nldesign-org-old-accent']['state']);
		$this->assertSame(404, $this->own()->delete(name: '--nldesign-org-old-accent')->getStatus());
	}//end testRemoveKeepsDeprecationAsRemoved()

	/**
	 * A deprecation is audited once; a refused one writes nothing.
	 *
	 * @return void
	 */
	public function testDeprecationAuditedAndRefusalNot(): void {
		$this->ownTokens->create(input: ['slug' => 'old-accent', 'label' => 'Old', 'type' => 'color', 'value' => '#aa0000']);
		$this->audit->expects($this->once())->method('log')->with('token_deprecation_changed');

		$refused = $this->deprecationController(['token' => '--nldesign-org-old-accent', 'severity' => 'warning', 'replacement' => '--nldesign-org-missing'])->save();
		$this->assertSame(400, $refused->getStatus());
		$this->assertSame('replacement', $refused->getData()['field']);

		$saved = $this->deprecationController(['token' => '--nldesign-org-old-accent', 'severity' => 'warning'])->save();
		$this->assertSame(200, $saved->getStatus());
		$this->assertStringContainsString('/* deprecated (warning) */', $this->overrides->getRawContent());
	}//end testDeprecationAuditedAndRefusalNot()

	/**
	 * Scenario: an administrator keeps the notices from an upload; only on that request.
	 *
	 * @return void
	 */
	public function testAdoptRecordsNotices(): void {
		$response = $this->deprecationController(['notices' => [['path' => 'color.primary', 'message' => 'Use color.brand.primary instead', 'token' => '--nldesign-color-primary']]])->adopt();

		$this->assertSame(['--nldesign-color-primary'], $response->getData()['recorded']);
		$this->assertSame('import', $this->deprecations->list()['--nldesign-color-primary']['source']);
		$this->assertTrue($this->deprecationController()->list()->getData()['deprecations'][0]['own'] === false, 'a shipped name is notice only');
	}//end testAdoptRecordsNotices()

	/**
	 * Scenario: a portal developer reads the deprecations; signed-in users only, no PublicPage.
	 *
	 * @return void
	 */
	public function testDeprecationsShape(): void {
		$this->ownTokens->create(input: ['slug' => 'old-accent', 'label' => 'Old', 'type' => 'color', 'value' => '#aa0000']);
		$this->ownTokens->create(input: ['slug' => 'brand-accent', 'label' => 'Brand', 'type' => 'color', 'value' => '#e17000']);
		$this->deprecations->deprecate(token: '--nldesign-org-old-accent', input: ['severity' => 'warning', 'replacement' => '--nldesign-org-brand-accent', 'removalDate' => '2027-03-01'], today: '2026-10-02');

		$catalog = new CatalogController('thematiq', $this->createMock(IRequest::class), $this->createMock(TokenSetService::class), $this->deprecations);
		$rows = $catalog->deprecations()->getData()['deprecations'];

		$this->assertSame(['token', 'severity', 'replacement', 'removalDate', 'message', 'deprecatedAt', 'due', 'state'], array_keys($rows[0]));
		$this->assertSame('--nldesign-org-brand-accent', $rows[0]['replacement']);
		$method = new ReflectionMethod(CatalogController::class, 'deprecations');
		$this->assertCount(1, $method->getAttributes(NoAdminRequired::class));
		$this->assertSame([], $method->getAttributes(PublicPage::class));
	}//end testDeprecationsShape()
}//end class
