<?php

/**
 * The shell tokens document: the active set, its roles, its logo, its revision.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ActiveTokenSetService;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\LogoLayerService;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\ShellTokenService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\StockTokensService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The declarations are resolved by the REAL resolver from the token files this
 * app ships, and mapped by the real mapper, so the document asserted here is
 * the one an instance on that set would answer. Only what needs a running
 * Nextcloud is replaced: which set is active, the administrator's own values,
 * the stock theme, and the URLs.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */
class ShellTokenServiceTest extends TestCase {

	/**
	 * Build the service for one instance state.
	 *
	 * @param string $active The configured token set id.
	 * @param array<string, string> $overrides The administrator's own values.
	 * @param array<string, string> $stock The stock theme's live tokens.
	 * @param array<string, string> $theming The theming app's config values.
	 * @param array<string, string> $names Display names, by set id.
	 *
	 * @return ShellTokenService The service.
	 */
	private function service(
		string $active,
		array $overrides = [],
		array $stock = [],
		array $theming = [],
		array $names = ['zuiddrecht' => 'Gemeente Zuiddrecht', 'nextcloud' => 'Nextcloud (Base)'],
	): ShellTokenService {
		$appPath = dirname(__DIR__, 3);

		$activeSet = $this->createMock(ActiveTokenSetService::class);
		$activeSet->method('getActive')->willReturn($active);

		$tokenSets = $this->createMock(TokenSetService::class);
		$tokenSets->method('getAppPath')->willReturn($appPath);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(
			static fn (string $tokenSetId): bool => is_file($appPath . '/css/tokens/' . $tokenSetId . '.css')
		);

		$designSystems = $this->createMock(DesignSystemService::class);
		$designSystems->method('getTokenSetMeta')->willReturnCallback(
			static fn (string $tokenSetId): array => array_filter(
				['name' => ($names[$tokenSetId] ?? null), 'theming' => ['background_color' => '#0277BD']]
			)
		);

		$overridesService = $this->createMock(CustomOverridesService::class);
		$overridesService->method('read')->willReturn($overrides);

		$stockTokens = $this->createMock(StockTokensService::class);
		$stockTokens->method('getTokens')->willReturn($stock);

		// The logo files this app ships, found the way LogoLayerService finds them.
		$logos = $this->createMock(LogoLayerService::class);
		$logos->method('shippedLogo')->willReturnCallback(
			static function (string $name) use ($appPath): ?string {
				$candidate = 'img/logos/' . $name . '.svg';
				if (is_file($appPath . '/' . $candidate) === true) {
					return $candidate;
				}

				return null;
			}
		);

		$files = $this->createMock(RuntimeFileLocator::class);
		$files->method('url')->willReturnCallback(static fn (string $name): string => '/custom_apps/thematiq/' . $name);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($theming[$key] ?? $default)
		);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('getAbsoluteURL')->willReturnCallback(static fn (string $url): string => 'https://cloud.example.org' . $url);
		$urlGenerator->method('linkToRoute')->willReturnCallback(
			static function (string $route, array $parameters = []) use ($theming): string {
				if (isset($theming['routeFails']) === true) {
					throw new RuntimeException('no such route');
				}

				return '/apps/theming/image/' . $parameters['key'];
			}
		);

		return new ShellTokenService(
			activeSet: $activeSet,
			tokenSets: $tokenSets,
			designSystems: $designSystems,
			declarations: new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService()),
			stockTokens: $stockTokens,
			overrides: $overridesService,
			mapper: new ShellRoleMapper(colours: new ContrastService()),
			logos: $logos,
			files: $files,
			config: $config,
			urlGenerator: $urlGenerator,
		);
	}//end service()

	/**
	 * The document has exactly the contract's fields, in the contract's order.
	 *
	 * @return void
	 */
	public function testTheDocumentHasTheContractsShape(): void {
		$document = $this->service(active: 'zuiddrecht')->document();

		$this->assertSame(['version', 'id', 'name', 'revision', 'tokens', 'logo'], array_keys($document));
		$this->assertSame(1, $document['version']);
		$this->assertSame('zuiddrecht', $document['id']);
		$this->assertSame('Gemeente Zuiddrecht', $document['name']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $document['revision']);
		$this->assertSame(ShellRoleMapper::ROLES, array_keys($document['tokens']));
		$this->assertSame('#3669A5', $document['tokens']['primary']);
		$this->assertSame('#F5F6F8', $document['tokens']['ground']);
		$this->assertSame(['url', 'inverse_url'], array_keys($document['logo']));
		$this->assertSame('https://cloud.example.org/custom_apps/thematiq/img/logos/zuiddrecht.svg', $document['logo']['url']);
		$this->assertSame('https://cloud.example.org/custom_apps/thematiq/img/logos/zuiddrecht-dark.svg', $document['logo']['inverse_url']);
	}//end testTheDocumentHasTheContractsShape()

	/**
	 * No token is null or empty, whatever set is active.
	 *
	 * @return void
	 */
	public function testNoTokenIsEverEmpty(): void {
		foreach (['zuiddrecht', 'nextcloud', 'rijkshuisstijl', 'summer-breeze', 'a-set-that-was-removed'] as $active) {
			$tokens = $this->service(active: $active)->document()['tokens'];

			$this->assertCount(18, $tokens, $active);
			foreach ($tokens as $role => $value) {
				$this->assertIsString($value, $active . ' ' . $role);
				$this->assertNotSame('', $value, $active . ' ' . $role);
			}
		}
	}//end testNoTokenIsEverEmpty()

	/**
	 * A configured set that no longer exists is answered as the stock set, with no logo.
	 *
	 * @return void
	 */
	public function testARemovedSetIsAnsweredAsTheStockSet(): void {
		$document = $this->service(active: 'custom-that-was-removed')->document();

		$this->assertSame('nextcloud', $document['id']);
		$this->assertSame('Nextcloud (Base)', $document['name']);
		$this->assertSame('#00679e', $document['tokens']['primary']);
		$this->assertSame(['url' => null, 'inverse_url' => null], $document['logo']);
	}//end testARemovedSetIsAnsweredAsTheStockSet()

	/**
	 * The stock set is read from the running instance, over its shipped snapshot.
	 *
	 * @return void
	 */
	public function testTheStockSetTakesTheInstancesLiveValues(): void {
		$document = $this->service(active: 'nextcloud', stock: ['--nldesign-color-primary' => '#7a1fa2'])->document();

		$this->assertSame('#7a1fa2', $document['tokens']['primary']);
		$this->assertSame('#222222', $document['tokens']['ink'], 'what the instance does not report stays as shipped');
	}//end testTheStockSetTakesTheInstancesLiveValues()

	/**
	 * A set on a design system does not take the stock theme's values.
	 *
	 * @return void
	 */
	public function testAnotherSetIgnoresTheStockTheme(): void {
		$document = $this->service(active: 'zuiddrecht', stock: ['--nldesign-color-primary' => '#7a1fa2'])->document();

		$this->assertSame('#3669A5', $document['tokens']['primary']);
	}//end testAnotherSetIgnoresTheStockTheme()

	/**
	 * The administrator's own values reach the document.
	 *
	 * @return void
	 */
	public function testTheAdministratorsOwnValuesReachTheDocument(): void {
		$document = $this->service(active: 'zuiddrecht', overrides: ['--color-primary' => '#aa0000'])->document();

		$this->assertSame('#aa0000', $document['tokens']['primary']);
	}//end testTheAdministratorsOwnValuesReachTheDocument()

	/**
	 * A set's Nextcloud login background is not handed out as its page colour.
	 *
	 * @return void
	 */
	public function testTheLoginBackgroundIsNotThePageColour(): void {
		$document = $this->service(active: 'vng')->document();

		$this->assertSame('#ffffff', $document['tokens']['ground']);
	}//end testTheLoginBackgroundIsNotThePageColour()

	/**
	 * A set without a display name is named after its id.
	 *
	 * @return void
	 */
	public function testASetWithoutANameIsNamedAfterItsId(): void {
		$document = $this->service(active: 'summer-breeze', names: [])->document();

		$this->assertSame('Summer Breeze', $document['name']);
	}//end testASetWithoutANameIsNamedAfterItsId()

	/**
	 * The revision is the same for the same document and moves with any value in it.
	 *
	 * @return void
	 */
	public function testTheRevisionMovesWithEveryValue(): void {
		$base = $this->service(active: 'zuiddrecht')->document()['revision'];

		$this->assertSame($base, $this->service(active: 'zuiddrecht')->document()['revision'], 'the same document, the same revision');

		$changed = [
			'another set' => $this->service(active: 'wilgenboom'),
			'one token' => $this->service(active: 'zuiddrecht', overrides: ['--border-radius-large' => '10px']),
			'the name' => $this->service(active: 'zuiddrecht', names: ['zuiddrecht' => 'Zuiddrecht']),
		];
		foreach ($changed as $what => $service) {
			$this->assertNotSame($base, $service->document()['revision'], $what);
		}

		$stock = $this->service(active: 'nextcloud')->document()['revision'];
		$withLogo = $this->service(active: 'nextcloud', theming: ['logoMime' => 'image/png'])->document()['revision'];
		$this->assertNotSame($stock, $withLogo, 'the logo');
	}//end testTheRevisionMovesWithEveryValue()

	/**
	 * A set without its own logo offers the logo uploaded in Nextcloud's theming settings.
	 *
	 * @return void
	 */
	public function testASetWithoutALogoOffersTheUploadedLogo(): void {
		$none = $this->service(active: 'cunningham')->document()['logo'];
		$this->assertSame(['url' => null, 'inverse_url' => null], $none);

		$uploaded = $this->service(active: 'cunningham', theming: ['logoMime' => 'image/png', 'cachebuster' => '7'])->document()['logo'];
		$this->assertSame('https://cloud.example.org/apps/theming/image/logo?v=7', $uploaded['url']);
		$this->assertNull($uploaded['inverse_url']);

		$header = $this->service(
			active: 'cunningham',
			theming: ['logoMime' => 'image/png', 'logoheaderMime' => 'image/svg+xml', 'cachebuster' => '7']
		)->document()['logo'];
		$this->assertSame('https://cloud.example.org/apps/theming/image/logoheader?v=7', $header['url'], 'the header logo comes first');

		$own = $this->service(active: 'zuiddrecht', theming: ['logoMime' => 'image/png'])->document()['logo'];
		$this->assertStringEndsWith('/img/logos/zuiddrecht.svg', (string)$own['url'], "the set's own logo comes before the upload");
	}//end testASetWithoutALogoOffersTheUploadedLogo()

	/**
	 * With the theming app off, its upload is not offered and the document still answers.
	 *
	 * @return void
	 */
	public function testAnUnservableUploadIsNotOffered(): void {
		$logo = $this->service(active: 'cunningham', theming: ['logoMime' => 'image/png', 'routeFails' => '1'])->document()['logo'];

		$this->assertNull($logo['url']);
	}//end testAnUnservableUploadIsNotOffered()

	/**
	 * The document holds theme values only.
	 *
	 * @return void
	 */
	public function testTheDocumentHoldsThemeValuesOnly(): void {
		$json = (string)json_encode($this->service(active: 'zuiddrecht', overrides: ['--color-primary' => '#aa0000'])->document());

		foreach (['user', 'uid', 'group', 'email', 'password', 'token_set', 'description', 'warnings'] as $word) {
			$this->assertStringNotContainsStringIgnoringCase('"' . $word, $json, $word);
		}
	}//end testTheDocumentHoldsThemeValuesOnly()
}//end class
