<?php

/**
 * Unit tests for LayoutOptionsService.
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
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\LayoutOptionsService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The two layout options resolve in three steps: a stored choice, else the
 * active set's default, else what every set had before the options existed.
 *
 * The set defaults are read from the SHIPPED token-sets.json, not from a
 * fixture: the claim under test is about the sets this release ships, and a
 * fixture would answer about itself.
 */
class LayoutOptionsServiceTest extends TestCase {

	/**
	 * The shipped sets that carry a layout block, each named on purpose.
	 *
	 * @var array<int, string>
	 */
	private const SETS_WITH_LAYOUT = ['zuiddrecht', 'wilgenboom', 'vaartveld', 'esdoornveen', 'warmtepompacademie'];

	/**
	 * The stored app values, by key.
	 *
	 * @var array<string, string>
	 */
	private array $stored = [];

	/**
	 * The config mock, backed by {@see self::$stored}.
	 *
	 * @var IConfig&MockObject
	 */
	private $config;

	/**
	 * The service under test, reading the shipped manifest.
	 *
	 * @var LayoutOptionsService
	 */
	private LayoutOptionsService $service;

	/**
	 * Wire a config mock that remembers what is written, and a design-system
	 * mock that answers from the repository's own token-sets.json.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->stored = [];
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->stored[$key] ?? $default)
		);
		$this->config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, string $value): void {
				$this->stored[$key] = $value;
			}
		);

		$manifest = $this->shippedManifest();
		$designSystems = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystems->method('getTokenSetMeta')->willReturnCallback(
			static fn (string $tokenSetId): array => ($manifest[$tokenSetId] ?? [])
		);

		$this->service = new LayoutOptionsService(config: $this->config, designSystemService: $designSystems);
	}//end setUp()

	/**
	 * The shipped manifest, indexed by set id.
	 *
	 * @return array<string, array<string, mixed>> The entries.
	 */
	private function shippedManifest(): array {
		$decoded = json_decode((string)file_get_contents(\dirname(__DIR__, 3) . '/token-sets.json'), true);
		$this->assertIsArray(actual: $decoded, message: 'token-sets.json must decode.');

		$indexed = [];
		foreach ($decoded as $entry) {
			$indexed[(string)$entry['id']] = $entry;
		}

		return $indexed;
	}//end shippedManifest()

	/**
	 * Zuiddrecht carries both defaults, and wears them with nothing stored.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 *
	 * @return void
	 */
	public function testZuiddrechtWearsTheLightLayoutAndTheStripe(): void {
		$this->assertSame(expected: '', actual: $this->service->workplaceLayoutSetting());
		$this->assertSame(expected: '', actual: $this->service->brandStripeSetting());
		$this->assertSame(expected: 'light', actual: $this->service->workplaceLayout(tokenSet: 'zuiddrecht'));
		$this->assertTrue(condition: $this->service->brandStripe(tokenSet: 'zuiddrecht'));
	}//end testZuiddrechtWearsTheLightLayoutAndTheStripe()

	/**
	 * The four school sets wear the light layout with nothing stored. Only
	 * esdoornveen, whose 6px motif fits inside the top bar the way
	 * zuiddrecht's 5px stripe does, also turns the stripe on; the other three
	 * leave it to the administrator.
	 *
	 * @spec openspec/changes/school-token-sets/specs/school-token-sets/spec.md#requirement-each-school-set-wears-the-light-workplace
	 *
	 * @return void
	 */
	public function testTheSchoolSetsWearTheLightLayout(): void {
		$expected = [
			'wilgenboom' => false,
			'vaartveld' => false,
			'esdoornveen' => true,
			'warmtepompacademie' => false,
		];
		foreach ($expected as $id => $stripe) {
			$this->assertSame(expected: 'light', actual: $this->service->workplaceLayout(tokenSet: $id), message: $id);
			$this->assertSame(expected: $stripe, actual: $this->service->brandStripe(tokenSet: $id), message: $id);
		}
	}//end testTheSchoolSetsWearTheLightLayout()

	/**
	 * No other shipped set changes: each resolves to the default layout and
	 * no stripe. Walks the whole manifest, so a set added later with a layout
	 * block has to be named here on purpose.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 *
	 * @return void
	 */
	public function testEveryOtherShippedSetKeepsTheDefaultLayout(): void {
		$changed = [];
		$walked = 0;
		foreach (array_keys($this->shippedManifest()) as $id) {
			if (in_array($id, self::SETS_WITH_LAYOUT, true) === true) {
				continue;
			}

			$walked++;
			if ($this->service->workplaceLayout(tokenSet: $id) !== 'default' || $this->service->brandStripe(tokenSet: $id) === true) {
				$changed[] = $id;
			}
		}

		$this->assertGreaterThan(expected: 50, actual: $walked, message: 'The walk must cover the shipped sets, not an empty list.');
		$this->assertSame(expected: [], actual: $changed);
	}//end testEveryOtherShippedSetKeepsTheDefaultLayout()

	/**
	 * An unknown set, and a set whose manifest entry is not there, resolve to
	 * the defaults rather than failing the page render.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 *
	 * @return void
	 */
	public function testAnUnknownSetResolvesToTheDefaults(): void {
		$this->assertSame(
			expected: [
				'workplaceLayout' => 'default',
				'brandStripe' => false,
				'navigationWidth' => '',
				'navigationActiveStyle' => 'default',
				'brandStripePlacement' => 'header-and-login',
				'loginWatermark' => '1',
				'headerStyle' => 'default',
			],
			actual: $this->service->setDefaults(tokenSet: 'custom-does-not-exist')
		);
	}//end testAnUnknownSetResolvesToTheDefaults()

	/**
	 * Zuiddrecht names a 264px navigation, the soft selected entry, the
	 * stripe on the login card only and the workplace top bar; it leaves the
	 * watermark to the built-in default.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-newer-layout-defaults
	 *
	 * @return void
	 */
	public function testZuiddrechtWearsTheNewerDefaults(): void {
		$this->assertSame(
			expected: [
				'workplaceLayout' => 'light',
				'brandStripe' => true,
				'navigationWidth' => 264,
				'navigationActiveStyle' => 'soft',
				'brandStripePlacement' => 'login',
				'loginWatermark' => true,
				'headerStyle' => 'workplace',
			],
			actual: $this->service->resolved(tokenSet: 'zuiddrecht')
		);
		$this->assertSame(
			expected: ['workplace-layout', 'header-workplace', 'brand-stripe', 'brand-stripe-login-only', 'navigation-width', 'navigation-active-soft'],
			actual: $this->service->stylesheets(tokenSet: 'zuiddrecht')
		);
		$this->assertSame(
			expected: [['id' => 'thematiq-navigation-width', 'css' => ':root { --thematiq-navigation-width: 264px; }']],
			actual: $this->service->inlineStyles(tokenSet: 'zuiddrecht')
		);
	}//end testZuiddrechtWearsTheNewerDefaults()

	/**
	 * Every shipped set but zuiddrecht resolves the newer options to what
	 * it had before they existed: Nextcloud's navigation width, the default
	 * selected entry, the stripe in both places, the watermark drawn and
	 * Nextcloud's own top bar.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-newer-layout-defaults
	 *
	 * @return void
	 */
	public function testEveryOtherShippedSetKeepsTheNewerDefaults(): void {
		$changed = [];
		$walked = 0;
		foreach (array_keys($this->shippedManifest()) as $id) {
			if ($id === 'zuiddrecht') {
				continue;
			}

			$walked++;
			$resolved = $this->service->resolved(tokenSet: $id);
			$newer = array_diff_key($resolved, ['workplaceLayout' => 1, 'brandStripe' => 1]);
			$expected = [
				'navigationWidth' => null,
				'navigationActiveStyle' => 'default',
				'brandStripePlacement' => 'header-and-login',
				'loginWatermark' => true,
				'headerStyle' => 'default',
			];
			if ($newer !== $expected) {
				$changed[] = $id;
			}

			$sheets = array_diff($this->service->stylesheets(tokenSet: $id), ['workplace-layout', 'brand-stripe']);
			if ($sheets !== [] || $this->service->inlineStyles(tokenSet: $id) !== []) {
				$changed[] = $id . ' (stylesheets)';
			}
		}

		$this->assertGreaterThan(expected: 60, actual: $walked, message: 'The walk must cover the shipped sets, not an empty list.');
		$this->assertSame(expected: [], actual: $changed);
	}//end testEveryOtherShippedSetKeepsTheNewerDefaults()

	/**
	 * A stored choice wins over the set for each newer option, and the empty
	 * choice follows the set again.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 *
	 * @return void
	 */
	public function testAStoredNewerChoiceWinsOverTheSet(): void {
		$this->assertSame(expected: '', actual: $this->service->setOption(key: 'workplace_layout', value: 'light'));
		$this->assertSame(expected: '', actual: $this->service->setOption(key: 'brand_stripe', value: '1'));
		$this->assertSame(expected: '', actual: $this->service->setOption(key: 'navigation_width', value: '320'));
		$this->assertSame(expected: '', actual: $this->service->setOption(key: 'navigation_active_style', value: 'default'));
		$this->assertSame(expected: '', actual: $this->service->setOption(key: 'brand_stripe_placement', value: 'login'));
		$this->assertSame(expected: '', actual: $this->service->setOption(key: 'login_watermark', value: '0'));

		$resolved = $this->service->resolved(tokenSet: 'zuiddrecht');
		$this->assertSame(expected: 320, actual: $resolved['navigationWidth']);
		$this->assertSame(expected: 'default', actual: $resolved['navigationActiveStyle']);
		$this->assertSame(expected: 'login', actual: $resolved['brandStripePlacement']);
		$this->assertFalse(condition: $resolved['loginWatermark']);
		$this->assertSame(
			expected: ['workplace-layout', 'login-watermark-off', 'header-workplace', 'brand-stripe', 'brand-stripe-login-only', 'navigation-width'],
			actual: $this->service->stylesheets(tokenSet: 'zuiddrecht')
		);
		$this->assertSame(expected: ':root { --thematiq-navigation-width: 320px; }', actual: $this->service->inlineStyles(tokenSet: 'zuiddrecht')[0]['css']);

		$this->assertSame(expected: '320', actual: $this->service->setOption(key: 'navigation_width', value: ''));
		$this->assertSame(expected: 'login', actual: $this->service->setOption(key: 'brand_stripe_placement', value: 'header'));
		$this->assertSame(expected: 264, actual: $this->service->resolved(tokenSet: 'zuiddrecht')['navigationWidth']);
		$this->assertSame(
			expected: ['workplace-layout', 'login-watermark-off', 'header-workplace', 'brand-stripe', 'brand-stripe-header-only', 'navigation-width'],
			actual: $this->service->stylesheets(tokenSet: 'zuiddrecht')
		);
	}//end testAStoredNewerChoiceWinsOverTheSet()

	/**
	 * A value an option does not accept is refused and nothing is stored: a
	 * width outside 200 to 480 or not a whole number, an unknown style, an
	 * unknown placement, a watermark that is not 1 or 0, an unknown layout
	 * for an older option, an unknown key.
	 *
	 * @param string $key The app config key.
	 * @param string $value The value offered.
	 *
	 * @return void
	 *
	 * @dataProvider refusedNewerValueProvider
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-the-navigation-width-is-an-admin-option
	 */
	public function testAValueAnOptionDoesNotAcceptIsRefused(string $key, string $value): void {
		$this->assertFalse(condition: $this->service->accepts(key: $key, value: $value));
		try {
			$this->service->setOption(key: $key, value: $value);
			$this->fail(message: 'Expected an InvalidArgumentException.');
		} catch (InvalidArgumentException $e) {
			$this->assertSame(expected: [], actual: $this->stored);
		}
	}//end testAValueAnOptionDoesNotAcceptIsRefused()

	/**
	 * Values each option refuses.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function refusedNewerValueProvider(): array {
		return [
			'width below the range' => ['navigation_width', '199'],
			'width above the range' => ['navigation_width', '481'],
			'width with a unit' => ['navigation_width', '264px'],
			'width that is not a number' => ['navigation_width', 'wide'],
			'unknown style' => ['navigation_active_style', 'bold'],
			'unknown placement' => ['brand_stripe_placement', 'footer'],
			'watermark that is not a flag' => ['login_watermark', 'yes'],
			'an older option refuses an unknown value too' => ['workplace_layout', 'wide'],
			'unknown key' => ['navigation_colour', 'red'],
		];
	}//end refusedNewerValueProvider()

	/**
	 * A stored value the option no longer accepts, and a manifest default the
	 * option does not accept, both read as "follow": the page still renders.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-newer-layout-defaults
	 *
	 * @return void
	 */
	public function testAnUnreadableNewerValueFollowsTheBuiltInDefault(): void {
		$this->stored['navigation_width'] = '12';
		$this->stored['navigation_active_style'] = 'bold';
		$this->assertSame(expected: '', actual: $this->service->setting(key: 'navigation_width'));
		$this->assertSame(expected: '', actual: $this->service->setting(key: 'navigation_active_style'));
		$this->assertSame(expected: 264, actual: $this->service->resolved(tokenSet: 'zuiddrecht')['navigationWidth']);

		$designSystems = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystems->method('getTokenSetMeta')->willReturn(
			['layout' => ['navigation_width' => 900, 'navigation_active_style' => 7, 'brand_stripe_placement' => 'roof', 'login_watermark' => 'no', 'header_style' => 'dark']]
		);
		$service = new LayoutOptionsService(config: $this->config, designSystemService: $designSystems);
		$this->stored = [];
		$this->assertSame(
			expected: ['navigationWidth' => '', 'navigationActiveStyle' => 'default', 'brandStripePlacement' => 'header-and-login', 'loginWatermark' => '1', 'headerStyle' => 'default'],
			actual: array_diff_key($service->setDefaults(tokenSet: 'odd'), ['workplaceLayout' => 1, 'brandStripe' => 1])
		);
	}//end testAnUnreadableNewerValueFollowsTheBuiltInDefault()

	/**
	 * A set may declare the width as a number or as a string, and may say
	 * the watermark is off and the stripe is on the login card only.
	 *
	 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-the-newer-layout-defaults
	 *
	 * @return void
	 */
	public function testASetMayDeclareEveryNewerDefault(): void {
		$designSystems = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystems->method('getTokenSetMeta')->willReturn(
			[
				'layout' => [
					'workplace_layout' => 'light',
					'brand_stripe' => true,
					'navigation_width' => '240',
					'navigation_active_style' => 'soft',
					'brand_stripe_placement' => 'login',
					'login_watermark' => false,
					'header_style' => 'workplace',
				],
			]
		);
		$service = new LayoutOptionsService(config: $this->config, designSystemService: $designSystems);

		$this->assertSame(
			expected: [
				'workplaceLayout' => 'light',
				'brandStripe' => true,
				'navigationWidth' => 240,
				'navigationActiveStyle' => 'soft',
				'brandStripePlacement' => 'login',
				'loginWatermark' => false,
				'headerStyle' => 'workplace',
			],
			actual: $service->resolved(tokenSet: 'declares-all')
		);
		$this->assertSame(
			expected: ['workplace-layout', 'login-watermark-off', 'header-workplace', 'brand-stripe', 'brand-stripe-login-only', 'navigation-width', 'navigation-active-soft'],
			actual: $service->stylesheets(tokenSet: 'declares-all')
		);
	}//end testASetMayDeclareEveryNewerDefault()

	/**
	 * The administrator's choice always wins, in both directions.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 *
	 * @return void
	 */
	public function testAStoredChoiceWinsOverTheSet(): void {
		$this->assertSame(expected: '', actual: $this->service->setWorkplaceLayout(layout: 'default'));
		$this->assertSame(expected: '', actual: $this->service->setBrandStripe(stripe: '0'));
		$this->assertSame(expected: 'default', actual: $this->service->workplaceLayout(tokenSet: 'zuiddrecht'));
		$this->assertFalse(condition: $this->service->brandStripe(tokenSet: 'zuiddrecht'));

		$this->assertSame(expected: 'default', actual: $this->service->setWorkplaceLayout(layout: 'light'));
		$this->assertSame(expected: '0', actual: $this->service->setBrandStripe(stripe: '1'));
		$this->assertSame(expected: 'light', actual: $this->service->workplaceLayout(tokenSet: 'utrecht'));
		$this->assertTrue(condition: $this->service->brandStripe(tokenSet: 'utrecht'));
	}//end testAStoredChoiceWinsOverTheSet()

	/**
	 * Storing the empty value hands the option back to the set.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 *
	 * @return void
	 */
	public function testTheEmptyChoiceFollowsTheSetAgain(): void {
		$this->service->setWorkplaceLayout(layout: 'light');
		$this->service->setBrandStripe(stripe: '1');
		$this->service->setWorkplaceLayout(layout: '');
		$this->service->setBrandStripe(stripe: '');

		$this->assertSame(expected: 'default', actual: $this->service->workplaceLayout(tokenSet: 'utrecht'));
		$this->assertFalse(condition: $this->service->brandStripe(tokenSet: 'utrecht'));
	}//end testTheEmptyChoiceFollowsTheSetAgain()

	/**
	 * A value outside the three is refused and nothing is written.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 *
	 * @return void
	 */
	public function testAnUnknownLayoutIsRefused(): void {
		try {
			$this->service->setWorkplaceLayout(layout: 'wide');
			$this->fail(message: 'An unknown layout must be refused.');
		} catch (InvalidArgumentException $e) {
			$this->assertArrayNotHasKey(key: 'workplace_layout', array: $this->stored);
		}

		$this->expectException(exception: InvalidArgumentException::class);
		$this->service->setBrandStripe(stripe: 'true');
	}//end testAnUnknownLayoutIsRefused()

	/**
	 * A value that reached the store some other way (an occ command, an old
	 * import) and is not one of the three is read as "follow the set".
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-brand-stripe-is-an-admin-option
	 *
	 * @return void
	 */
	public function testAnUnreadableStoredValueFollowsTheSet(): void {
		$this->stored = ['workplace_layout' => 'wide', 'brand_stripe' => 'yes'];

		$this->assertSame(expected: '', actual: $this->service->workplaceLayoutSetting());
		$this->assertSame(expected: '', actual: $this->service->brandStripeSetting());
		$this->assertSame(expected: 'light', actual: $this->service->workplaceLayout(tokenSet: 'zuiddrecht'));
		$this->assertFalse(condition: $this->service->brandStripe(tokenSet: 'utrecht'));
	}//end testAnUnreadableStoredValueFollowsTheSet()

	/**
	 * The workplace top bar loads only with the light layout: a set or an
	 * administrator that asks for it on Nextcloud's own coloured bar gets
	 * nothing, since the bar it places is the light one.
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md#requirement-the-header-style-is-a-layout-option
	 *
	 * @return void
	 */
	public function testTheWorkplaceHeaderNeedsTheLightLayout(): void {
		$this->assertSame(expected: '', actual: $this->service->setOption(key: 'header_style', value: 'workplace'));
		$this->assertContains(needle: 'header-workplace', haystack: $this->service->stylesheets(tokenSet: 'zuiddrecht'));
		$this->assertNotContains(needle: 'header-workplace', haystack: $this->service->stylesheets(tokenSet: 'utrecht'));
		$this->assertSame(expected: 'workplace', actual: $this->service->resolved(tokenSet: 'utrecht')['headerStyle']);

		$this->assertSame(expected: '', actual: $this->service->setOption(key: 'workplace_layout', value: 'default'));
		$this->assertNotContains(needle: 'header-workplace', haystack: $this->service->stylesheets(tokenSet: 'zuiddrecht'));

		$this->assertSame(expected: 'workplace', actual: $this->service->setOption(key: 'header_style', value: 'default'));
		$this->assertSame(expected: 'default', actual: $this->service->setOption(key: 'workplace_layout', value: ''));
		$this->assertNotContains(needle: 'header-workplace', haystack: $this->service->stylesheets(tokenSet: 'zuiddrecht'));
		$this->assertFalse(condition: $this->service->accepts(key: 'header_style', value: 'dark'));
	}//end testTheWorkplaceHeaderNeedsTheLightLayout()
}//end class
