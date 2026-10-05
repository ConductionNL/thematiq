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
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => ($this->stored[$key] ?? $default)
		);
		$this->config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, string $value): void {
				$this->stored[$key] = $value;
			}
		);

		$manifest = $this->shippedManifest();
		$designSystems = $this->createMock(DesignSystemService::class);
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
		$this->assertIsArray($decoded, 'token-sets.json must decode.');

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
	 */
	public function testZuiddrechtWearsTheLightLayoutAndTheStripe(): void {
		$this->assertSame('', $this->service->workplaceLayoutSetting());
		$this->assertSame('', $this->service->brandStripeSetting());
		$this->assertSame('light', $this->service->workplaceLayout(tokenSet: 'zuiddrecht'));
		$this->assertTrue($this->service->brandStripe(tokenSet: 'zuiddrecht'));
	}//end testZuiddrechtWearsTheLightLayoutAndTheStripe()

	/**
	 * The four school sets wear the light layout with nothing stored. The two
	 * whose designed Nextcloud login carries the motif also turn the stripe
	 * on; the other two leave it to the administrator.
	 *
	 * @spec openspec/changes/school-token-sets/specs/school-token-sets/spec.md#requirement-each-school-set-wears-the-light-workplace
	 */
	public function testTheSchoolSetsWearTheLightLayout(): void {
		$expected = [
			'wilgenboom' => true,
			'vaartveld' => false,
			'esdoornveen' => true,
			'warmtepompacademie' => false,
		];
		foreach ($expected as $id => $stripe) {
			$this->assertSame('light', $this->service->workplaceLayout(tokenSet: $id), $id);
			$this->assertSame($stripe, $this->service->brandStripe(tokenSet: $id), $id);
		}
	}//end testTheSchoolSetsWearTheLightLayout()

	/**
	 * No other shipped set changes: each resolves to the default layout and
	 * no stripe. Walks the whole manifest, so a set added later with a layout
	 * block has to be named here on purpose.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
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

		$this->assertGreaterThan(50, $walked, 'The walk must cover the shipped sets, not an empty list.');
		$this->assertSame([], $changed);
	}//end testEveryOtherShippedSetKeepsTheDefaultLayout()

	/**
	 * An unknown set, and a set whose manifest entry is not there, resolve to
	 * the defaults rather than failing the page render.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 */
	public function testAnUnknownSetResolvesToTheDefaults(): void {
		$this->assertSame(['workplaceLayout' => 'default', 'brandStripe' => false], $this->service->setDefaults(tokenSet: 'custom-does-not-exist'));
	}//end testAnUnknownSetResolvesToTheDefaults()

	/**
	 * The administrator's choice always wins, in both directions.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-a-token-set-may-carry-layout-defaults
	 */
	public function testAStoredChoiceWinsOverTheSet(): void {
		$this->assertSame('', $this->service->setWorkplaceLayout(layout: 'default'));
		$this->assertSame('', $this->service->setBrandStripe(stripe: '0'));
		$this->assertSame('default', $this->service->workplaceLayout(tokenSet: 'zuiddrecht'));
		$this->assertFalse($this->service->brandStripe(tokenSet: 'zuiddrecht'));

		$this->assertSame('default', $this->service->setWorkplaceLayout(layout: 'light'));
		$this->assertSame('0', $this->service->setBrandStripe(stripe: '1'));
		$this->assertSame('light', $this->service->workplaceLayout(tokenSet: 'utrecht'));
		$this->assertTrue($this->service->brandStripe(tokenSet: 'utrecht'));
	}//end testAStoredChoiceWinsOverTheSet()

	/**
	 * Storing the empty value hands the option back to the set.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 */
	public function testTheEmptyChoiceFollowsTheSetAgain(): void {
		$this->service->setWorkplaceLayout(layout: 'light');
		$this->service->setBrandStripe(stripe: '1');
		$this->service->setWorkplaceLayout(layout: '');
		$this->service->setBrandStripe(stripe: '');

		$this->assertSame('default', $this->service->workplaceLayout(tokenSet: 'utrecht'));
		$this->assertFalse($this->service->brandStripe(tokenSet: 'utrecht'));
	}//end testTheEmptyChoiceFollowsTheSetAgain()

	/**
	 * A value outside the three is refused and nothing is written.
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md#requirement-the-workplace-layout-is-an-admin-option
	 */
	public function testAnUnknownLayoutIsRefused(): void {
		try {
			$this->service->setWorkplaceLayout(layout: 'wide');
			$this->fail('An unknown layout must be refused.');
		} catch (InvalidArgumentException $e) {
			$this->assertArrayNotHasKey('workplace_layout', $this->stored);
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service->setBrandStripe(stripe: 'true');
	}//end testAnUnknownLayoutIsRefused()

	/**
	 * A value that reached the store some other way (an occ command, an old
	 * import) and is not one of the three is read as "follow the set".
	 *
	 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md#requirement-the-brand-stripe-is-an-admin-option
	 */
	public function testAnUnreadableStoredValueFollowsTheSet(): void {
		$this->stored = ['workplace_layout' => 'wide', 'brand_stripe' => 'yes'];

		$this->assertSame('', $this->service->workplaceLayoutSetting());
		$this->assertSame('', $this->service->brandStripeSetting());
		$this->assertSame('light', $this->service->workplaceLayout(tokenSet: 'zuiddrecht'));
		$this->assertFalse($this->service->brandStripe(tokenSet: 'utrecht'));
	}//end testAnUnreadableStoredValueFollowsTheSet()
}//end class
