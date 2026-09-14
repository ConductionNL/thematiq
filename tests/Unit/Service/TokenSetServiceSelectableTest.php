<?php

/**
 * Unit tests for TokenSetService::getSelectableTokenSets(): the narrowed list
 * the admin dropdown is built from, and the three ids it may never narrow away.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * `getSelectableTokenSets()` narrows the catalogue to what an admin may pick.
 * Narrowing a picker must never be able to change what the instance is doing,
 * so three ids survive the filter whatever the allowlist says: the set that is
 * running now, any set a per-group mapping points at, and every `custom-*`
 * import.
 *
 * Those three are the whole risk in this method. Dropping the ACTIVE set
 * renders the panel with nothing selected and the first save re-themes the
 * instance to whatever happened to come first. Dropping a GROUP's set makes a
 * theme that is still being applied invisible in the UI that is supposed to
 * manage it. Dropping a CUSTOM set makes the importer a liar — it tells the
 * admin their upload is "added and selectable" as it finishes.
 *
 * Discovery itself runs unmocked against the repository root, as the sibling
 * catalogue tests do, so the filter is asserted over the real shipped set.
 *
 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
 */
class TokenSetServiceSelectableTest extends TestCase {

	/**
	 * The WCAG-level store standing in for the distributed cache.
	 *
	 * Static, and therefore shared by every test in this class, for the same
	 * reason the real cache is distributed: the audit behind it is the
	 * expensive part, it depends only on the set id, and none of these tests
	 * varies it. Per-test stores made the class re-audit all 48 shipped sets
	 * eleven times over.
	 *
	 * @var array<string, mixed>
	 */
	private static array $wcagStore = [];

	/**
	 * The synthetic app directory holding the four-set catalogue.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * Repository root, used as the app path for the one test that asserts the
	 * filter against the real shipped catalogue.
	 *
	 * @return string The repository root.
	 */
	private function repoRoot(): string {
		return \dirname(__DIR__, 3);
	}//end repoRoot()

	/**
	 * Lay down a four-set catalogue: the allowlisted `nextcloud`, plus three
	 * shipped sets that are not allowlisted and therefore have to be KEPT by
	 * one of the three survival rules or dropped.
	 *
	 * Synthetic rather than the real `css/tokens/`, because what is under test
	 * is the filter, not discovery — and because a full catalogue build audits
	 * every shipped set's CSS, which made this class alone take longer than
	 * the rest of the suite put together.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-selectable-test-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$manifest = [];
		foreach (['nextcloud', 'rijkshuisstijl', 'amsterdam', 'utrecht'] as $id) {
			file_put_contents(
				$this->appDir . '/css/tokens/' . $id . '.css',
				":root {\n  --nldesign-color-primary: #154273;\n}\n"
			);
			$manifest[] = [
				'id' => $id,
				'name' => ucfirst($id),
				'design_system' => 'none',
				'theming' => ['primary_color' => '#154273', 'background_color' => '#ffffff'],
			];
		}

		file_put_contents($this->appDir . '/token-sets.json', json_encode($manifest));
	}//end setUp()

	/**
	 * Remove the synthetic app directory.
	 */
	protected function tearDown(): void {
		foreach (glob($this->appDir . '/css/tokens/*') as $file) {
			unlink($file);
		}

		unlink($this->appDir . '/token-sets.json');
		rmdir($this->appDir . '/css/tokens');
		rmdir($this->appDir . '/css');
		rmdir($this->appDir);

		parent::tearDown();
	}//end tearDown()

	/**
	 * Build the service with the given appconfig values.
	 *
	 * @param array<string, string> $appConfig The appconfig keys to answer.
	 * @param string|null           $appPath   The app path; defaults to the synthetic catalogue.
	 *
	 * @return TokenSetService The service under test.
	 */
	private function service(array $appConfig, ?string $appPath = null): TokenSetService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($appPath ?? $this->appDir);
		$appManager->method('getAppVersion')->willReturn('3.4.0');

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default = '') => ($appConfig[$key] ?? $default)
		);

		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturnCallback(
			static fn (string $key) => (self::$wcagStore[$key] ?? null)
		);
		$cache->method('set')->willReturnCallback(
			static function (string $key, $value, int $ttl = 0): bool {
				self::$wcagStore[$key] = $value;

				return true;
			}
		);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);

		return new TokenSetService(
			$appManager,
			$config,
			$this->createMock(LoggerInterface::class),
			new ShippedTokenSetAuditService(new ContrastService(), new CssParserService()),
			$cacheFactory,
			new TokenSetVocabularyAuditService(new CssParserService())
		);
	}//end service()

	/**
	 * Extract the ids from a catalogue result.
	 *
	 * @param array<int, array<string, mixed>> $sets The catalogue entries.
	 *
	 * @return array<int, string> The ids.
	 */
	private function ids(array $sets): array {
		return array_map(static fn (array $set): string => (string)$set['id'], $sets);
	}//end ids()

	/**
	 * On a stock instance the dropdown offers the allowlist and nothing else,
	 * even though the catalogue itself still holds every shipped set.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testStockInstanceOffersOnlyTheAllowlistedShippedSets(): void {
		$service = $this->service(['token_set' => 'nextcloud'], $this->repoRoot());

		$selectable = $this->ids($service->getSelectableTokenSets());
		$all = $this->ids($service->getAvailableTokenSets());

		$this->assertSame(TokenSetService::SELECTABLE_SHIPPED_SETS, $selectable);
		$this->assertContains('nextcloud', $selectable);
		// The catalogue is NOT what was narrowed — only the picker is.
		$this->assertGreaterThan(count($selectable), count($all));
	}//end testStockInstanceOffersOnlyTheAllowlistedShippedSets()

	/**
	 * The set the instance is running survives the filter even when it is not
	 * allowlisted; otherwise the panel renders with no option selected and the
	 * first save silently re-themes the instance.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testTheActiveSetIsAlwaysSelectable(): void {
		$service = $this->service(['token_set' => 'rijkshuisstijl']);

		$selectable = $this->ids($service->getSelectableTokenSets());

		$this->assertContains('rijkshuisstijl', $selectable);
		$this->assertContains('nextcloud', $selectable);
		$this->assertNotContains('rijkshuisstijl', TokenSetService::SELECTABLE_SHIPPED_SETS);
	}//end testTheActiveSetIsAlwaysSelectable()

	/**
	 * A set only a per-group mapping points at is still selectable: the group
	 * picker is fed from this same list, so filtering it out would hide a
	 * theme that is still being applied.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testASetUsedByAGroupMappingIsSelectable(): void {
		$mapping = json_encode(
			[
				['group' => 'gemeente', 'tokenSet' => 'amsterdam'],
				['group' => 'ontwerp', 'tokenSet' => 'utrecht'],
			]
		);

		$service = $this->service(['token_set' => 'nextcloud', 'group_token_sets' => $mapping]);
		$selectable = $this->ids($service->getSelectableTokenSets());

		$this->assertContains('amsterdam', $selectable);
		$this->assertContains('utrecht', $selectable);
		$this->assertContains('nextcloud', $selectable);
	}//end testASetUsedByAGroupMappingIsSelectable()

	/**
	 * A group mapping that is not usable JSON, or whose entries are not the
	 * expected shape, narrows the list to the allowlist rather than throwing:
	 * a corrupt appconfig value must not take the admin panel down with it.
	 *
	 * @param string $mapping The stored group-mapping value.
	 *
	 * @dataProvider unusableMappingProvider
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testAnUnusableGroupMappingIsIgnored(string $mapping): void {
		$service = $this->service(['token_set' => 'nextcloud', 'group_token_sets' => $mapping]);

		$this->assertSame(
			TokenSetService::SELECTABLE_SHIPPED_SETS,
			$this->ids($service->getSelectableTokenSets())
		);
	}//end testAnUnusableGroupMappingIsIgnored()

	/**
	 * Group-mapping values that carry no usable token set id.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function unusableMappingProvider(): array {
		return [
			'not json at all' => ['{not json'],
			'json scalar' => ['"amsterdam"'],
			'entries are not arrays' => ['["amsterdam", "utrecht"]'],
			'entries carry no tokenSet' => ['[{"group": "gemeente"}]'],
			'tokenSet is not a string' => ['[{"group": "gemeente", "tokenSet": 42}]'],
			'empty list' => ['[]'],
		];
	}//end unusableMappingProvider()

	/**
	 * An unset active set resolves to `nextcloud`, and an explicitly empty one
	 * adds nothing — the allowlist already carries `nextcloud`, and keying the
	 * filter on an empty string would make every id with no name match.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testAnEmptyActiveSetAddsNothing(): void {
		$this->assertSame(
			TokenSetService::SELECTABLE_SHIPPED_SETS,
			$this->ids($this->service(['token_set' => ''])->getSelectableTokenSets())
		);
		$this->assertSame(
			TokenSetService::SELECTABLE_SHIPPED_SETS,
			$this->ids($this->service([])->getSelectableTokenSets())
		);
	}//end testAnEmptyActiveSetAddsNothing()

	/**
	 * The narrowed list is a subset of the catalogue in the catalogue's own
	 * order, carrying the same entries — it filters, it does not re-shape or
	 * re-sort, because the dropdown and the apply dialog read the same fields
	 * off both.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testSelectableEntriesAreCatalogueEntriesInCatalogueOrder(): void {
		$service = $this->service(['token_set' => 'rijkshuisstijl']);

		$all = $service->getAvailableTokenSets();
		$selectable = $service->getSelectableTokenSets();

		$this->assertNotEmpty($selectable);
		foreach ($selectable as $entry) {
			$this->assertContains($entry, $all);
		}

		$order = array_values(
			array_filter($this->ids($all), static fn (string $id): bool => in_array($id, ['nextcloud', 'rijkshuisstijl'], true))
		);
		$this->assertSame($order, $this->ids($selectable));
	}//end testSelectableEntriesAreCatalogueEntriesInCatalogueOrder()
}//end class
