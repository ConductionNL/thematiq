<?php

/**
 * Unit tests for TokenSetSelectionPolicy::selectable(): the narrowed list
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
use OCA\Thematiq\Service\TokenSetSelectionPolicy;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\TokenSetVocabularyAuditService;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * `TokenSetSelectionPolicy::selectable()` narrows the catalogue to what an admin may pick.
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
	 * Named sets in the synthetic catalogue that the audit does not report
	 * incomplete, and which must therefore be offered.
	 *
	 * @var array<int, string>
	 */
	private const COMPLETE_SETS = ['nextcloud', 'cunningham', 'rijkshuisstijl', 'amsterdam', 'utrecht'];

	/**
	 * A named set the vocabulary audit reports incomplete.
	 */
	private const INCOMPLETE_SET = 'halfbrand';

	/**
	 * A token file with no `token-sets.json` entry — the shape
	 * `css/tokens/conduction.css` has in the real repository.
	 */
	private const UNNAMED_SET = 'rolelayer';

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
	 * Lay down the catalogue: every allowlisted id, plus three shipped sets
	 * that are not allowlisted and therefore have to be KEPT by one of the
	 * three survival rules or dropped.
	 *
	 * The allowlisted half is read from the constant rather than spelled out,
	 * because these tests assert that the filter's output IS the constant — an
	 * id the synthetic catalogue never carried could not survive the filter,
	 * so a hard-coded list here silently turns "widened the allowlist" into a
	 * test failure that says nothing about the filter.
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

		// Named sets whose design system reads no --nldesign-* vocabulary, so
		// the audit reports them not auditable and therefore not incomplete:
		// these are the ones that MUST be offered.
		foreach (self::COMPLETE_SETS as $id) {
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

		// A named nldesign set declaring only a primary: the vocabulary audit
		// reports it incomplete, so it MUST NOT be offered. Without a set in
		// this state the filter could stop filtering and every assertion below
		// would still pass.
		mkdir($this->appDir . '/css/systems/nldesign', 0777, true);
		foreach (glob($this->repoRoot() . '/css/systems/nldesign/*.css') ?: [] as $layer) {
			copy($layer, $this->appDir . '/css/systems/nldesign/' . basename($layer));
		}
		copy($this->repoRoot() . '/design-systems.json', $this->appDir . '/design-systems.json');
		file_put_contents(
			$this->appDir . '/css/tokens/' . self::INCOMPLETE_SET . '.css',
			":root {\n  --nldesign-color-primary: #154273;\n}\n"
		);
		$manifest[] = [
			'id' => self::INCOMPLETE_SET,
			'name' => 'Halfbrand',
			'design_system' => 'nldesign',
			'theming' => ['primary_color' => '#154273', 'background_color' => '#ffffff'],
		];

		// A token file with NO manifest entry: the shared role layer's shape.
		// Discovery finds it; the picker must not offer it, because it has no
		// name, description or theming of its own to show.
		file_put_contents(
			$this->appDir . '/css/tokens/' . self::UNNAMED_SET . '.css',
			":root {\n  --nldesign-color-primary: #154273;\n}\n"
		);

		file_put_contents($this->appDir . '/token-sets.json', json_encode($manifest));
	}//end setUp()

	/**
	 * Remove the synthetic app directory.
	 */
	protected function tearDown(): void {
		foreach (glob($this->appDir . '/css/tokens/*') as $file) {
			unlink($file);
		}

		foreach (glob($this->appDir . '/css/systems/nldesign/*') as $file) {
			unlink($file);
		}

		unlink($this->appDir . '/token-sets.json');
		@unlink($this->appDir . '/design-systems.json');
		rmdir($this->appDir . '/css/systems/nldesign');
		rmdir($this->appDir . '/css/systems');
		rmdir($this->appDir . '/css/tokens');
		rmdir($this->appDir . '/css');
		rmdir($this->appDir);

		parent::tearDown();
	}//end tearDown()

	/**
	 * Build the service with the given appconfig values.
	 *
	 * @param array<string, string> $appConfig The appconfig keys to answer.
	 * @param string|null $appPath The app path; defaults to the synthetic catalogue.
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
	/**
	 * The selectable entries for a catalogue built with the given appconfig.
	 *
	 * The decision lives in `TokenSetSelectionPolicy`, so these tests drive the
	 * policy over the service's real catalogue rather than a method on the
	 * service.
	 *
	 * @param array<string, string> $appConfig The appconfig keys to answer.
	 * @param string|null $appPath The app path; defaults to the synthetic catalogue.
	 *
	 * @return array<int, array<string, mixed>> The selectable entries.
	 */
	private function selectable(array $appConfig, ?string $appPath = null): array {
		return (new TokenSetSelectionPolicy())->selectable(
			tokenSets: $this->service($appConfig, $appPath),
			config: $this->configFor($appConfig),
			appPath: ($appPath ?? $this->appDir)
		);
	}//end selectable()

	/**
	 * An IConfig that answers the given appconfig keys.
	 *
	 * @param array<string, string> $appConfig The appconfig keys to answer.
	 *
	 * @return IConfig The configured mock.
	 */
	private function configFor(array $appConfig): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, $default = '') => ($appConfig[$key] ?? $default)
		);

		return $config;
	}//end configFor()

	private function ids(array $sets): array {
		return array_map(static fn (array $set): string => (string)$set['id'], $sets);
	}//end ids()

	/**
	 * On a stock instance the dropdown offers every NAMED set the vocabulary
	 * audit does not report incomplete, and nothing else.
	 *
	 * Both halves matter. The incomplete set must be absent, or the filter has
	 * stopped filtering; the complete ones must be present, or it is narrowing
	 * when it should not. The set with no manifest entry must be absent too:
	 * discovery finds it, and it has no name to show.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testStockInstanceOffersEveryNamedCompleteSet(): void {
		$config = ['token_set' => 'nextcloud'];

		$selectable = $this->ids($this->selectable($config));
		$all = $this->ids($this->service($config)->getAvailableTokenSets());

		$this->assertEqualsCanonicalizing(self::COMPLETE_SETS, $selectable);
		$this->assertNotContains(self::INCOMPLETE_SET, $selectable, 'A vocabulary-incomplete set must not be offered.');
		$this->assertNotContains(self::UNNAMED_SET, $selectable, 'A set with no manifest entry must not be offered.');

		// The catalogue is NOT what was narrowed — only the picker is.
		$this->assertContains(self::INCOMPLETE_SET, $all);
		$this->assertContains(self::UNNAMED_SET, $all);
		$this->assertGreaterThan(count($selectable), count($all));
	}//end testStockInstanceOffersEveryNamedCompleteSet()

	/**
	 * The set the instance is running survives BOTH filters — the vocabulary
	 * verdict and the manifest-entry rule — because narrowing a picker must
	 * never change what the instance is doing. Otherwise the panel renders with
	 * no option selected and the first save silently re-themes the instance.
	 *
	 * Asserted on the two sets that are otherwise filtered out, which is the
	 * only way this rule can be exercised now that every named complete set is
	 * offered anyway.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testTheActiveSetIsAlwaysSelectable(): void {
		foreach ([self::INCOMPLETE_SET, self::UNNAMED_SET] as $id) {
			$selectable = $this->ids($this->selectable(['token_set' => $id]));

			$this->assertContains($id, $selectable, $id . ' is active, so it must still be offered.');
			$this->assertContains('nextcloud', $selectable);
		}

		// ...and it is genuinely filtered out when it is NOT the active set, so
		// the assertion above is not passing on a filter that keeps everything.
		$this->assertNotContains(
			self::INCOMPLETE_SET,
			$this->ids($this->selectable(['token_set' => 'nextcloud']))
		);
	}//end testTheActiveSetIsAlwaysSelectable()

	/**
	 * A set only a per-group mapping points at is still selectable: the group
	 * picker is fed from this same list, so filtering it out would hide a
	 * theme that is still being applied.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testASetUsedByAGroupMappingIsSelectable(): void {
		// The mapping points at the two sets the filter would otherwise drop,
		// because a mapping onto a set that is offered anyway proves nothing.
		$mapping = json_encode(
			[
				['group' => 'gemeente', 'tokenSet' => self::INCOMPLETE_SET],
				['group' => 'ontwerp', 'tokenSet' => self::UNNAMED_SET],
			]
		);

		$selectable = $this->ids(
			$this->selectable(['token_set' => 'nextcloud', 'group_token_sets' => $mapping])
		);

		$this->assertContains(self::INCOMPLETE_SET, $selectable);
		$this->assertContains(self::UNNAMED_SET, $selectable);
		$this->assertContains('nextcloud', $selectable);
	}//end testASetUsedByAGroupMappingIsSelectable()

	/**
	 * A group mapping that is not usable JSON, or whose entries are not the
	 * expected shape, leaves the measured list alone rather than throwing: a
	 * corrupt appconfig value must not take the admin panel down with it, and
	 * must not widen the picker either.
	 *
	 * @param string $mapping The stored group-mapping value.
	 *
	 * @dataProvider unusableMappingProvider
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testAnUnusableGroupMappingIsIgnored(string $mapping): void {
		$this->assertEqualsCanonicalizing(
			self::COMPLETE_SETS,
			$this->ids($this->selectable(['token_set' => 'nextcloud', 'group_token_sets' => $mapping]))
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
	 * adds nothing — `nextcloud` is offered on its own merits, and keying the
	 * filter on an empty string would make every id with no name match.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testAnEmptyActiveSetAddsNothing(): void {
		$this->assertEqualsCanonicalizing(
			self::COMPLETE_SETS,
			$this->ids($this->selectable(['token_set' => '']))
		);
		$this->assertEqualsCanonicalizing(
			self::COMPLETE_SETS,
			$this->ids($this->selectable([]))
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
		$config = ['token_set' => 'nextcloud'];

		$all = $this->service($config)->getAvailableTokenSets();
		$selectable = $this->selectable($config);

		$this->assertNotEmpty($selectable);
		foreach ($selectable as $entry) {
			$this->assertContains($entry, $all);
		}

		$expected = self::COMPLETE_SETS;
		$order = array_values(
			array_filter($this->ids($all), static fn (string $id): bool => in_array($id, $expected, true))
		);
		$this->assertSame($order, $this->ids($selectable));
	}//end testSelectableEntriesAreCatalogueEntriesInCatalogueOrder()

	/**
	 * THE ANTI-NARROWING GATE, over the REAL shipped catalogue.
	 *
	 * Selectability used to be a hand-written constant holding two of the 59
	 * shipped sets, so an administrator could not choose vng, leiden, zwolle,
	 * rotterdam or any other municipality at all. That is the opposite of what
	 * this app is for, and it stayed true for weeks after its own stated exit
	 * condition was met, because nothing failed when it went stale.
	 *
	 * So this asserts the rule against the real `css/tokens/` and the real
	 * `token-sets.json`: the picker offers EVERY named shipped set the
	 * vocabulary audit does not report incomplete, and the only shipped file it
	 * withholds is one with no manifest entry. A future change that narrows the
	 * picker again fails here and names the sets it dropped.
	 *
	 * Run against the repository root rather than a synthetic catalogue on
	 * purpose: a synthetic one would pass while the shipped sets were hidden.
	 *
	 * @spec openspec/specs/token-sets/spec.md#requirement-only-fully-functional-brands-are-selectable
	 */
	public function testEveryNamedShippedSetTheAuditPassesIsOfferedOnTheRealCatalogue(): void {
		$config = ['token_set' => 'nextcloud'];

		$all = $this->service($config, $this->repoRoot())->getAvailableTokenSets();
		$selectable = $this->ids($this->selectable($config, $this->repoRoot()));

		$manifest = json_decode((string)file_get_contents($this->repoRoot() . '/token-sets.json'), true);
		$this->assertIsArray($manifest);
		$named = array_column($manifest, 'id');

		$audit = new TokenSetVocabularyAuditService(new CssParserService());
		$incomplete = [];
		foreach ($audit->auditAll($this->repoRoot()) as $result) {
			if ($result['complete'] === false) {
				$incomplete[] = $result['id'];
			}
		}

		$expected = array_values(array_diff($named, $incomplete));

		$this->assertEqualsCanonicalizing(
			$expected,
			$selectable,
			"The admin picker must offer every named shipped set the vocabulary audit passes.\n"
			. 'Withheld: ' . implode(', ', array_diff($expected, $selectable)) . "\n"
			. 'Offered but should not be: ' . implode(', ', array_diff($selectable, $expected))
		);

		// The guarantee is not vacuous: the catalogue is strictly larger,
		// and what it holds beyond the picker is a file with no manifest entry.
		$unnamed = array_values(array_diff($this->ids($all), $named));
		$this->assertNotSame([], $unnamed, 'This control needs at least one token file with no manifest entry.');
		foreach ($unnamed as $id) {
			$this->assertNotContains($id, $selectable, $id . ' has no manifest entry, so it must not be offered.');
		}

		// And it is a real widening, not a rename of the old two-set list.
		$this->assertGreaterThan(
			50,
			count($selectable),
			'All 59 shipped sets but the role layer are expected to be selectable; a count near 2 means the picker was narrowed again.'
		);
	}//end testEveryNamedShippedSetTheAuditPassesIsOfferedOnTheRealCatalogue()
}//end class
