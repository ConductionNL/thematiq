<?php

/**
 * Golden test for ConfigBundleService export and import.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/config-portability/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\AppThemingService;
use OCA\Thematiq\Service\BundleExtraSections;
use OCA\Thematiq\Service\ConfigBundleService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\Exception\FooterValidationException;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\RestrictedSetGate;
use OCA\Thematiq\Service\ScheduledSwitchStore;
use OCA\Thematiq\Service\TokenLifecycleBundleSection;
use OCA\Thematiq\Service\TokenSetService;
use OCA\Thematiq\Service\UpstreamFreshnessService;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Exports, then imports seeded bundles as a dry run and for real: envelopes right and wrong,
 * a config section that is not an object or holds wrong types, layout options, exclusion lists,
 * planned switches, footers the footer rules refuse, overrides CSS with editable and unknown
 * tokens, custom sets of every shape (bad id, no name, no CSS, a refused selector, refused
 * declarations, passthrough fields, a shipped or unknown design system), font manifests, token
 * sets that resolve or not, a restricted set, and the token lifecycle section present or not.
 * Records the export, the result and every write, case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\ConfigBundleService
 * @uses   \OCA\Thematiq\Service\Exception\FooterValidationException
 * @uses   \OCA\Thematiq\Service\LayoutOptionValues
 * @uses   \OCA\Thematiq\Service\LayoutOptionsService
 * @uses   \OCA\Thematiq\Service\RestrictedSetsInBundle
 * @uses   \OCA\Thematiq\Service\TokenRegistry
 */
class ConfigBundleImportGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/config-bundle-import.golden.json';

	private const SET_IDS = ['custom-acme', 'custom-beta', 'acme', 'custom-', 'custom-gamma'];

	private const TOKEN_SETS = ['nextcloud', 'rijkshuisstijl', 'restricted-set', 'unknown-set', 'custom-acme', 'custom-beta', ''];

	/**
	 * The temp app directory with the design systems manifest.
	 *
	 * @var string
	 */
	private string $appDir = '';

	/**
	 * Remove the temp app directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ($this->appDir !== '' && is_dir($this->appDir) === true) {
			unlink($this->appDir . '/design-systems.json');
			rmdir($this->appDir);
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * Every seeded bundle exports and imports to the recorded result and writes.
	 *
	 * @return void
	 */
	public function testImportMatchesTheGoldenRecording(): void {
		$this->appDir = sys_get_temp_dir() . '/thematiq-bundle-golden-' . getmypid();
		if (is_dir($this->appDir) === false) {
			mkdir($this->appDir);
		}


		$actual = [];
		for ($seed = 1; $seed <= 400; $seed++) {
			$actual['seed:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testImportMatchesTheGoldenRecording()

	/**
	 * One seeded bundle, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$calls = [];
		$service = $this->service(random: $random, calls: $calls);

		$export = null;
		if ($random->getInt(0, 3) === 0) {
			$export = $service->export();
			$export['exportedAt'] = '(time)';
		}

		$bundle = $this->bundle(random: $random);
		$dryRun = ($random->getInt(0, 2) === 0);
		$calls = [];
		$result = $service->import(bundle: $bundle, dryRun: $dryRun);

		return (string)json_encode(['export' => $export, 'dryRun' => $dryRun, 'result' => $result, 'calls' => $calls], JSON_UNESCAPED_SLASHES);
	}//end runCase()

	/**
	 * The service under test, every collaborator a recording double.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param array<int, mixed> $calls The call log.
	 *
	 * @return ConfigBundleService
	 */
	private function service(Randomizer $random, array &$calls): ConfigBundleService {
		$app = ['token_set' => self::TOKEN_SETS[$random->getInt(0, 1)], 'hide_slogan' => (string)$random->getInt(0, 1)];
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, $default = '') => ($app[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $appId, string $key, $value) use (&$calls): void {
				$calls[] = ['setAppValue', $appId, $key, $value];
			}
		);

		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppVersion')->willReturn('9.9.9');
		$appManager->method('getAppPath')->willReturn([$this->appDir, '/nonexistent-thematiq'][(int)($random->getInt(0, 5) === 0)]);
		$manifests = [
			[['id' => 'nlds'], ['id' => 'utrecht'], 'junk', ['name' => 'x']],
			[['id' => 'nlds'], ['id' => 7], ['id' => null], ['id' => 'utrecht']],
			'nlds',
		];
		$manifest = (string)json_encode($manifests[$random->getInt(0, 2)]);
		if ($random->getInt(0, 4) === 0) {
			$manifest = '{"broken';
		}

		file_put_contents($this->appDir . '/design-systems.json', $manifest);

		$gate = $this->createMock(originalClassName: RestrictedSetGate::class);
		$gate->method('refuses')->willReturnCallback(
			static fn (string $tokenSetId, ?string $alreadyApplied = null): bool => $tokenSetId === 'restricted-set'
		);
		$tokenSets = $this->createMock(originalClassName: TokenSetService::class);
		$tokenSets->method('isValidTokenSet')->willReturnCallback(
			static fn (string $tokenSetId): bool => in_array($tokenSetId, ['nextcloud', 'rijkshuisstijl', 'restricted-set'], true)
		);
		$tokenSets->method('licenceGate')->willReturn($gate);

		$overrides = $this->createMock(originalClassName: CustomOverridesService::class);
		$overrides->method('getRawContent')->willReturn(":root {\n  --nldesign-color-primary: #123456;\n}\n");
		$overrides->method('write')->willReturnCallback(
			static function (array $tokens) use (&$calls): void {
				$calls[] = ['overrides.write', $tokens];
			}
		);

		$customSets = $this->createMock(originalClassName: CustomTokenSetService::class);
		$customSets->method('isCustomId')->willReturnCallback(static fn (string $id): bool => preg_match('/^custom-[a-z0-9][a-z0-9-]*$/', $id) === 1);
		$customSets->method('list')->willReturn(
			[['id' => 'custom-one', 'name' => 'One'], ['id' => 'custom-gone', 'name' => 'Gone'], ['name' => 'No id']]
		);
		$customSets->method('getRawContent')->willReturnCallback(
			static fn (string $id): ?string => (['custom-one' => ':root{--a:1}'][$id] ?? null)
		);
		$customSets->method('replace')->willReturnCallback(
			static function (string $id, array $entry, string $css) use (&$calls): void {
				$calls[] = ['customSets.replace', $id, $entry, $css];
			}
		);

		$validator = $this->createMock(originalClassName: CustomTokenSetValidator::class);
		$validator->method('hasDisallowedSelector')->willReturnCallback(static fn (string $css): bool => str_contains($css, 'body'));
		$validator->method('validateDeclarations')->willReturnCallback(
			static function (array $declarations, string $slug): ?array {
				if (isset($declarations['--refused']) === true) {
					return null;
				}

				return ['accepted' => $declarations + ['--slug' => $slug], 'rejected' => []];
			}
		);
		$asked = $random->getInt(0, 1);
		$validator->method('getLastError')->willReturnCallback(
			static function () use (&$asked): ?array {
				$asked++;
				return [null, ['message' => 'refused declaration']][($asked % 2)];
			}
		);
		$validator->method('serialize')->willReturnCallback(static fn (array $declarations): string => 'css:' . json_encode($declarations));

		$cssParser = $this->createMock(originalClassName: CssParserService::class);
		$cssParser->method('parseOverridesFile')->willReturnCallback(
			static function (string $css): ?array {
				if ($css === 'unparsable') {
					return null;
				}

				return ['--nldesign-color-primary' => '#0b71a1', '--not-a-token' => '1px', '--nldesign-color-text' => '#111111'];
			}
		);
		$cssParser->method('parseRootBlock')->willReturnCallback(
			static function (string $css): array {
				if (str_contains($css, 'refuse') === true) {
					return ['--refused' => 'x'];
				}

				return ['--nldesign-color-primary' => substr(md5($css), 0, 6)];
			}
		);

		$appTheming = $this->createMock(originalClassName: AppThemingService::class);
		$appTheming->method('getDisabledApps')->willReturn(['files']);
		$appTheming->method('setDisabledApps')->willReturnCallback(
			static function (array $appIds) use (&$calls): void {
				$calls[] = ['appTheming.setDisabledApps', $appIds];
			}
		);

		$email = $this->createMock(originalClassName: EmailThemingService::class);
		$email->method('getFooterConfig')->willReturn(['orgName' => 'Org', 'accessibilityUrl' => '', 'privacyUrl' => '']);
		$email->method('validateFooterConfig')->willReturnCallback(
			static function (string $orgName, string $accessibilityUrl, string $privacyUrl): void {
				if ($accessibilityUrl === 'not-a-url') {
					throw new FooterValidationException(field: 'accessibilityUrl', message: 'The accessibility URL must be https.');
				}
			}
		);
		$email->method('setFooterConfig')->willReturnCallback(
			static function (string $orgName, string $accessibilityUrl, string $privacyUrl) use (&$calls): array {
				$calls[] = ['email.setFooterConfig', $orgName, $accessibilityUrl, $privacyUrl];
				return [];
			}
		);

		$fonts = $this->createMock(originalClassName: FontService::class);
		$fonts->method('getManifest')->willReturn(['custom-font' => ['name' => 'Font']]);

		$freshness = $this->createMock(originalClassName: UpstreamFreshnessService::class);
		$freshness->method('isEnabled')->willReturn(true);
		$freshness->method('setEnabled')->willReturnCallback(
			static function (bool $enabled) use (&$calls): void {
				$calls[] = ['freshness.setEnabled', $enabled];
			}
		);

		$switches = $this->createMock(originalClassName: ScheduledSwitchStore::class);
		$switches->method('exportable')->willReturn([['tokenSet' => 'nextcloud', 'start' => '2026-01-01T00:00:00Z']]);
		$switches->method('validateImport')->willReturnCallback(
			static function (mixed $raw, callable $setExists): array {
				$entries = [];
				$errors = [];
				foreach ((array)$raw as $entry) {
					$id = (string)(((array)$entry)['tokenSet'] ?? '');
					if ($setExists($id) === false) {
						$errors[] = 'Unknown set ' . $id;
						continue;
					}

					$entries[] = ['tokenSet' => $id];
				}

				return ['entries' => $entries, 'errors' => $errors];
			}
		);
		$switches->method('mergeImported')->willReturnCallback(
			static function (array $imported, int $now) use (&$calls): array {
				$calls[] = ['switches.mergeImported', $imported, ($now > 0)];
				return array_merge([['tokenSet' => 'kept']], $imported);
			}
		);
		$switches->method('save')->willReturnCallback(
			static function (array $entries) use (&$calls): void {
				$calls[] = ['switches.save', $entries];
			}
		);

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		foreach (['info', 'warning'] as $level) {
			$logger->method($level)->willReturnCallback(
				static function (...$arguments) use (&$calls, $level): void {
					$calls[] = ['log.' . $level, $arguments[0]];
				}
			);
		}

		$extra = $this->createMock(originalClassName: BundleExtraSections::class);
		$extra->method('export')->willReturn(['appBrands' => []]);
		$extra->method('validate')->willReturnCallback(
			static function (array $bundle, callable $setExists) use (&$calls): array {
				$calls[] = ['extra.validate', array_map($setExists, ['custom-acme', 'nextcloud', 'unknown-set'])];
				return ['extra' => array_keys($bundle)];
			}
		);
		$extra->method('summary')->willReturnCallback(static fn (array $resolved): array => ['appBrands' => $resolved]);
		$extra->method('apply')->willReturnCallback(
			static function (array $resolved) use (&$calls): void {
				$calls[] = ['extra.apply', $resolved];
			}
		);

		$lifecycle = null;
		if ($random->getInt(0, 1) === 0) {
			$lifecycle = $this->createMock(originalClassName: TokenLifecycleBundleSection::class);
			$lifecycle->method('export')->willReturn(['ownTokens' => []]);
			$lifecycle->method('validate')->willReturn(['own' => 1]);
			$lifecycle->method('summary')->willReturnCallback(static fn (?array $resolved): array => ['ownTokens' => $resolved]);
			$lifecycle->method('apply')->willReturnCallback(
				static function (?array $resolved) use (&$calls): bool {
					$calls[] = ['lifecycle.apply', $resolved];
					return true;
				}
			);
		}

		return $this->construct(
			collaborators: [
				'config' => $config,
				'appManager' => $appManager,
				'tokenSets' => $tokenSets,
				'overrides' => $overrides,
				'customSets' => $customSets,
				'validator' => $validator,
				'appTheming' => $appTheming,
				'cssParser' => $cssParser,
				'email' => $email,
				'fonts' => $fonts,
				'freshness' => $freshness,
				'switches' => $switches,
				'logger' => $logger,
				'extra' => $extra,
				'lifecycle' => $lifecycle,
			]
		);
	}//end service()

	/**
	 * Build the service from its doubles.
	 *
	 * @param array<string, mixed> $collaborators The doubles by role.
	 *
	 * @return ConfigBundleService
	 */
	private function construct(array $collaborators): ConfigBundleService {
		$c = $collaborators;

		return new ConfigBundleService(
			config: $c['config'],
			appManager: $c['appManager'],
			tokenSetService: $c['tokenSets'],
			overridesService: $c['overrides'],
			customTokenSetService: $c['customSets'],
			customTokenSetValidator: $c['validator'],
			appThemingService: $c['appTheming'],
			cssParser: $c['cssParser'],
			emailThemingService: $c['email'],
			fontService: $c['fonts'],
			freshnessService: $c['freshness'],
			scheduledSwitches: $c['switches'],
			logger: $c['logger'],
			extraSections: $c['extra'],
			lifecycle: $c['lifecycle']
		);
	}//end construct()

	/**
	 * A seeded bundle: a valid one, with up to three faults.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, mixed>
	 */
	private function bundle(Randomizer $random): array {
		$bundle = $this->validBundle(random: $random);
		$faults = $random->getInt(-3, 3);
		for ($fault = 0; $fault < $faults; $fault++) {
			$this->corrupt(random: $random, bundle: $bundle);
		}

		return $bundle;
	}//end bundle()

	/**
	 * A seeded bundle that passes validation.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return array<string, mixed>
	 */
	private function validBundle(Randomizer $random): array {
		$sets = [];
		foreach (['custom-acme', 'custom-beta'] as $index => $id) {
			if ($random->getInt(0, 1) === 1) {
				$sets[] = $this->validSet(random: $random, id: $id);
			}
		}

		$config = [
			'tokenSet' => array_merge(['nextcloud', 'rijkshuisstijl'], array_column($sets, 'id'))[$random->getInt(0, (count($sets) + 1))],
			'hideSlogan' => ($random->getInt(0, 1) === 1),
			'showMenuLabels' => ($random->getInt(0, 1) === 1),
			'primaryDrivesComponents' => ($random->getInt(0, 1) === 1),
			'disabledApps' => [[], ['files'], ['files', 'mail']][$random->getInt(0, 2)],
			'upstreamFreshnessEnabled' => ($random->getInt(0, 1) === 1),
		];
		foreach (['hideSlogan', 'showMenuLabels', 'primaryDrivesComponents', 'upstreamFreshnessEnabled'] as $flag) {
			if ($random->getInt(0, 5) === 0) {
				unset($config[$flag]);
			}
		}

		$this->maybe(random: $random, bundle: $config, key: 'layoutOptions', values: [[], ['headerStyle' => '']]);
		$this->maybe(random: $random, bundle: $config, key: 'scheduledSwitches', values: [[], [['tokenSet' => 'nextcloud']]]);

		return [
			'format' => ConfigBundleService::FORMAT,
			'bundleVersion' => $random->getInt(1, 4),
			'config' => $config,
			'emailFooter' => ['orgName' => 'Gemeente ' . $random->getInt(1, 9), 'accessibilityUrl' => 'https://a.example', 'privacyUrl' => ''],
			'customOverridesCss' => ':root{}',
			'customTokenSets' => $sets,
			'customFonts' => ['manifest' => [[], ['custom-a' => ['name' => 'A']]][$random->getInt(0, 1)]],
		];
	}//end validBundle()

	/**
	 * A custom set entry that passes validation, with seeded passthrough fields.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param string $id The set id.
	 *
	 * @return array<string, mixed>
	 */
	private function validSet(Randomizer $random, string $id): array {
		$set = ['id' => $id, 'name' => ' Set ' . $id . ' ', 'css' => ':root{--x:' . $random->getInt(1, 9) . '}'];
		$this->maybe(random: $random, bundle: $set, key: 'description', values: ['Desc', 12]);
		$this->maybe(random: $random, bundle: $set, key: 'theming', values: [['primary_color' => '#000'], 'x']);
		$this->maybe(random: $random, bundle: $set, key: 'warnings', values: [['w1'], 'w']);
		$this->maybe(random: $random, bundle: $set, key: 'version', values: ['1.2.3', null, 4]);
		$this->maybe(random: $random, bundle: $set, key: 'importWarnings', values: [['iw'], 'iw']);
		$this->maybe(random: $random, bundle: $set, key: 'design_system', values: ['nlds', 'utrecht', 'unknown', 7]);

		return $set;
	}//end validSet()

	/**
	 * Put one seeded fault into a bundle.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param array<string, mixed> $bundle The bundle.
	 *
	 * @return void
	 */
	private function corrupt(Randomizer $random, array &$bundle): void {
		$fault = $random->getInt(0, 9);
		if ($fault === 0) {
			unset($bundle['format']);
		}

		if ($fault === 1) {
			$bundle['bundleVersion'] = [5, '4', null][$random->getInt(0, 2)];
		}

		if ($fault === 2 || $fault === 3) {
			$bundle['config'] = $this->configSection(random: $random);
		}

		if ($fault === 4) {
			$bundle['emailFooter'] = ['not an object', ['orgName' => 'Gemeente', 'accessibilityUrl' => 'not-a-url'], []][$random->getInt(0, 2)];
		}

		if ($fault === 5) {
			$bundle['customOverridesCss'] = [42, 'unparsable', ''][$random->getInt(0, 2)];
		}

		if ($fault === 6) {
			$bundle['customFonts'] = ['nope', ['manifest' => 'nope'], []][$random->getInt(0, 2)];
		}

		if ($fault === 7 || $fault === 8) {
			$bundle['customTokenSets'] = $this->customSets(random: $random);
		}

		if ($fault === 9) {
			$keys = array_keys($bundle);
			unset($bundle[$keys[$random->getInt(0, (count($keys) - 1))]]);
		}
	}//end corrupt()

	/**
	 * Set a seeded value for a key, or leave the key out.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param array<string, mixed> $bundle The bundle.
	 * @param string $key The key.
	 * @param array<int, mixed> $values The candidate values.
	 *
	 * @return void
	 */
	private function maybe(Randomizer $random, array &$bundle, string $key, array $values): void {
		$pick = $random->getInt(-1, (count($values) - 1));
		if ($pick >= 0) {
			$bundle[$key] = $values[$pick];
		}
	}//end maybe()

	/**
	 * A seeded `config` section.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return mixed
	 */
	private function configSection(Randomizer $random): mixed {
		$shape = $random->getInt(0, 14);
		if ($shape === 0) {
			return 'not an object';
		}

		if ($shape === 1) {
			return null;
		}

		$config = [];
		$this->maybe(random: $random, bundle: $config, key: 'tokenSet', values: array_merge(self::TOKEN_SETS, [7]));
		foreach (['hideSlogan', 'showMenuLabels', 'primaryDrivesComponents', 'upstreamFreshnessEnabled'] as $flag) {
			$this->maybe(random: $random, bundle: $config, key: $flag, values: [true, false, true, false, 'yes', 1]);
		}

		$this->maybe(random: $random, bundle: $config, key: 'layoutOptions', values: [
			'wide', [], ['workplaceLayout' => 'nonsense'], ['brandStripe' => 5], ['headerStyle' => ''],
		]);
		$this->maybe(random: $random, bundle: $config, key: 'disabledApps', values: ['files', ['files', 'mail'], ['files', 3], [], [5 => 'deck']]);
		$this->maybe(random: $random, bundle: $config, key: 'scheduledSwitches', values: [
			[],
			[['tokenSet' => 'nextcloud'], ['tokenSet' => 'custom-acme']],
			[['tokenSet' => 'unknown-set']],
			'garbage',
			[['tokenSet' => 'restricted-set']],
		]);

		return $config;
	}//end configSection()

	/**
	 * A seeded `customTokenSets` section.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return mixed
	 */
	private function customSets(Randomizer $random): mixed {
		if ($random->getInt(0, 9) === 0) {
			return 'not a list';
		}

		$sets = [];
		$count = $random->getInt(0, 3);
		for ($index = 0; $index < $count; $index++) {
			$sets[($index * 2) + 3] = $this->customSet(random: $random);
		}

		return $sets;
	}//end customSets()

	/**
	 * One seeded `customTokenSets[]` entry.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return mixed
	 */
	private function customSet(Randomizer $random): mixed {
		if ($random->getInt(0, 11) === 0) {
			return 'not an object';
		}

		$set = [];
		$this->maybe(random: $random, bundle: $set, key: 'id', values: self::SET_IDS);
		$this->maybe(random: $random, bundle: $set, key: 'name', values: ['Acme', '  ', ' Beta ', 'Gamma']);
		$this->maybe(random: $random, bundle: $set, key: 'css', values: [':root{--x:1}', '', 5, 'body{color:red}', ':root{--refuse:1}', ':root{--y:2}']);
		$this->maybe(random: $random, bundle: $set, key: 'description', values: ['Desc', 12]);
		$this->maybe(random: $random, bundle: $set, key: 'theming', values: [['primary_color' => '#000'], 'x']);
		$this->maybe(random: $random, bundle: $set, key: 'warnings', values: [['w1'], 'w']);
		$this->maybe(random: $random, bundle: $set, key: 'version', values: ['1.2.3', null, 4]);
		$this->maybe(random: $random, bundle: $set, key: 'importWarnings', values: [['iw'], 'iw']);
		$this->maybe(random: $random, bundle: $set, key: 'design_system', values: ['nlds', 'utrecht', 'unknown', 7]);

		return $set;
	}//end customSet()
}//end class
