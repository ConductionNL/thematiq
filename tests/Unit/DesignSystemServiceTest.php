<?php

/**
 * Unit tests for DesignSystemService's icon-pack resolver.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/icon-packs/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\DesignSystemService;
use OCP\App\IAppManager;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * Covers `openspec/specs/icon-packs/spec.md`: `getIconPacks()` normalization,
 * `resolveActiveIconPacks()` precedence (admin override > token-set override >
 * design-system default > empty), and `resolveIconPath()` lookup + traversal
 * safety — against a temp app dir with a controlled design-systems.json,
 * token-sets.json, and img/icons/ fixture tree.
 */
class DesignSystemServiceTest extends TestCase {

	/**
	 * The temp app directory standing in for the nldesign app path.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * Set up a temp app dir with fixture manifests and icon files.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/nldesign-icon-packs-test-' . uniqid();

		mkdir($this->appDir . '/img/icons/rvo', 0777, true);
		mkdir($this->appDir . '/img/icons/open-gemeenten', 0777, true);
		mkdir($this->appDir . '/img/icons/den-haag', 0777, true);
		mkdir($this->appDir . '/img/icons/dsfr', 0777, true);

		file_put_contents($this->appDir . '/img/icons/rvo/rvo-home.svg', '<svg></svg>');
		// Present in both rvo and open-gemeenten, to test ordered-list precedence.
		file_put_contents($this->appDir . '/img/icons/rvo/shared-name.svg', '<svg>rvo</svg>');
		file_put_contents($this->appDir . '/img/icons/open-gemeenten/shared-name.svg', '<svg>og</svg>');
		file_put_contents($this->appDir . '/img/icons/dsfr/arrow-right-line.svg', '<svg></svg>');

		file_put_contents(
			$this->appDir . '/design-systems.json',
			(string)json_encode(
				[
					['id' => 'none', 'name' => 'Stock', 'description' => '', 'stylesheets' => []],
					[
						'id' => 'nldesign',
						'name' => 'NL Design',
						'description' => '',
						'stylesheets' => [],
						'icon_pack' => ['rvo', 'open-gemeenten', 'den-haag'],
					],
					[
						'id' => 'lasuite',
						'name' => 'La Suite',
						'description' => '',
						'stylesheets' => [],
						'icon_pack' => 'dsfr',
					],
				]
			)
		);

		file_put_contents(
			$this->appDir . '/token-sets.json',
			(string)json_encode(
				[
					['id' => 'rijkshuisstijl', 'design_system' => 'nldesign'],
					['id' => 'lasuite', 'design_system' => 'lasuite'],
					['id' => 'nextcloud', 'design_system' => 'none'],
					['id' => 'ghost-system', 'design_system' => 'does-not-exist'],
					// A real Dutch municipality set: the manifest OMITS
					// design_system, relying on the app-wide 'nldesign' default.
					['id' => 'gemeente-zonder-ds'],
				]
			)
		);
	}//end setUp()

	/**
	 * Remove the temp app dir after each test.
	 */
	protected function tearDown(): void {
		$this->rrmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Recursively remove a directory tree.
	 *
	 * @param string $dir The directory to remove.
	 */
	private function rrmdir(string $dir): void {
		if (is_dir($dir) === false) {
			return;
		}

		foreach (scandir($dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $dir . '/' . $entry;
			if (is_dir($path) === true) {
				$this->rrmdir($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}//end rrmdir()

	/**
	 * Build a service against the temp app dir, with a controllable appconfig
	 * `icon_pack` override.
	 *
	 * @param string|null $override The appconfig `icon_pack` value (null = unset).
	 */
	private function service(?string $override = null): DesignSystemService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $appName, string $key, $default = '') use ($override) {
				if ($key === 'icon_pack') {
					return $override ?? $default;
				}

				return $default;
			}
		);

		return new DesignSystemService($appManager, $config);
	}//end service()

	/**
	 * getIconPacks() normalizes a scalar `icon_pack` to a one-element list.
	 */
	public function testGetIconPacksNormalizesScalarToList(): void {
		$this->assertSame(['dsfr'], $this->service()->getIconPacks('lasuite'));
	}//end testGetIconPacksNormalizesScalarToList()

	/**
	 * getIconPacks() preserves an already-ordered array `icon_pack`.
	 */
	public function testGetIconPacksPreservesOrderedArray(): void {
		$this->assertSame(['rvo', 'open-gemeenten', 'den-haag'], $this->service()->getIconPacks('nldesign'));
	}//end testGetIconPacksPreservesOrderedArray()

	/**
	 * getIconPacks() returns [] for a design system with no `icon_pack` field.
	 */
	public function testGetIconPacksAbsentFieldReturnsEmpty(): void {
		$this->assertSame([], $this->service()->getIconPacks('none'));
	}//end testGetIconPacksAbsentFieldReturnsEmpty()

	/**
	 * getIconPacks() returns [] (never throws) for an unknown design system id.
	 */
	public function testGetIconPacksUnknownDesignSystemReturnsEmpty(): void {
		$this->assertSame([], $this->service()->getIconPacks('does-not-exist'));
	}//end testGetIconPacksUnknownDesignSystemReturnsEmpty()

	/**
	 * An active French (`lasuite`) theme resolves the DSFR pack.
	 */
	public function testResolveActiveIconPacksLasuiteDefault(): void {
		$this->assertSame(['dsfr'], $this->service()->resolveActiveIconPacks('lasuite'));
	}//end testResolveActiveIconPacksLasuiteDefault()

	/**
	 * An active Dutch (`nldesign`) theme resolves the ordered Dutch packs.
	 */
	public function testResolveActiveIconPacksNldesignDefault(): void {
		$this->assertSame(['rvo', 'open-gemeenten', 'den-haag'], $this->service()->resolveActiveIconPacks('rijkshuisstijl'));
	}//end testResolveActiveIconPacksNldesignDefault()

	/**
	 * A token set that OMITS design_system (the ~33 Dutch municipality sets)
	 * must default to the `nldesign` design system — and therefore resolve
	 * the Dutch icon packs, not an empty list. Regression: the resolver
	 * previously returned [] for a missing field, silently stripping the
	 * Dutch government's own icons.
	 */
	public function testResolveActiveIconPacksDefaultsMissingDesignSystemToNldesign(): void {
		$this->assertSame(
			['rvo', 'open-gemeenten', 'den-haag'],
			$this->service()->resolveActiveIconPacks('gemeente-zonder-ds')
		);
	}//end testResolveActiveIconPacksDefaultsMissingDesignSystemToNldesign()

	/**
	 * A `none` (stock) design system resolves to no pack.
	 */
	public function testResolveActiveIconPacksNoneDesignSystemReturnsEmpty(): void {
		$this->assertSame([], $this->service()->resolveActiveIconPacks('nextcloud'));
	}//end testResolveActiveIconPacksNoneDesignSystemReturnsEmpty()

	/**
	 * An unknown token set (absent from token-sets.json) degrades to [], never throws.
	 */
	public function testResolveActiveIconPacksUnknownTokenSetReturnsEmpty(): void {
		$this->assertSame([], $this->service()->resolveActiveIconPacks('unknown-token-set-xyz'));
	}//end testResolveActiveIconPacksUnknownTokenSetReturnsEmpty()

	/**
	 * A token set whose design_system is itself unknown degrades to [], never throws.
	 */
	public function testResolveActiveIconPacksUnknownDesignSystemOnTokenSetReturnsEmpty(): void {
		$this->assertSame([], $this->service()->resolveActiveIconPacks('ghost-system'));
	}//end testResolveActiveIconPacksUnknownDesignSystemOnTokenSetReturnsEmpty()

	/**
	 * A valid appconfig `icon_pack` override wins regardless of the active
	 * token set's design system.
	 */
	public function testResolveActiveIconPacksAdminOverrideWins(): void {
		$this->assertSame(['dsfr'], $this->service(override: 'dsfr')->resolveActiveIconPacks('rijkshuisstijl'));
	}//end testResolveActiveIconPacksAdminOverrideWins()

	/**
	 * An override naming a directory that does not exist under img/icons/ is
	 * ignored — resolution falls through to the design-system default.
	 */
	public function testResolveActiveIconPacksInvalidOverrideFallsThrough(): void {
		$service = $this->service(override: 'does-not-exist-on-disk');
		$this->assertSame(['rvo', 'open-gemeenten', 'den-haag'], $service->resolveActiveIconPacks('rijkshuisstijl'));
	}//end testResolveActiveIconPacksInvalidOverrideFallsThrough()

	/**
	 * resolveIconPath() finds a name within the active pack.
	 */
	public function testResolveIconPathFindsFileInActivePack(): void {
		$this->assertSame('icons/dsfr/arrow-right-line.svg', $this->service()->resolveIconPath('arrow-right-line', 'lasuite'));
	}//end testResolveIconPathFindsFileInActivePack()

	/**
	 * resolveIconPath() returns the FIRST pack (declared order) containing a
	 * name present in more than one pack of the active ordered list.
	 */
	public function testResolveIconPathFirstPackWinsAcrossOrderedList(): void {
		$this->assertSame('icons/rvo/shared-name.svg', $this->service()->resolveIconPath('shared-name', 'rijkshuisstijl'));
	}//end testResolveIconPathFirstPackWinsAcrossOrderedList()

	/**
	 * resolveIconPath() returns null for a name absent from every active pack.
	 */
	public function testResolveIconPathMissingNameReturnsNull(): void {
		$this->assertNull($this->service()->resolveIconPath('does-not-exist', 'lasuite'));
	}//end testResolveIconPathMissingNameReturnsNull()

	/**
	 * resolveIconPath() rejects traversal / separator input without touching
	 * the filesystem outside img/icons/.
	 */
	public function testResolveIconPathRejectsTraversalAndSeparators(): void {
		$service = $this->service();
		$this->assertNull($service->resolveIconPath('../../secret', 'lasuite'));
		$this->assertNull($service->resolveIconPath('foo/bar', 'lasuite'));
		$this->assertNull($service->resolveIconPath('foo\\bar', 'lasuite'));
		$this->assertNull($service->resolveIconPath('', 'lasuite'));
	}//end testResolveIconPathRejectsTraversalAndSeparators()

	/**
	 * resolveIconPath() returns null when no pack is active for the token set.
	 */
	public function testResolveIconPathNoActivePackReturnsNull(): void {
		$this->assertNull($this->service()->resolveIconPath('rvo-home', 'nextcloud'));
	}//end testResolveIconPathNoActivePackReturnsNull()

	/**
	 * An id design-systems.json does not ship resolves to an empty bundle,
	 * so no design-system stylesheet is loaded and nothing throws.
	 *
	 * @spec openspec/specs/css-architecture/spec.md#unknown-design-system-falls-back-safely
	 */
	public function testGetDesignSystemUnknownIdFallsBackToNoStylesheets(): void {
		$system = $this->service()->getDesignSystem('does-not-exist');

		$this->assertSame('does-not-exist', $system['id']);
		$this->assertSame([], $system['stylesheets']);
	}//end testGetDesignSystemUnknownIdFallsBackToNoStylesheets()

	/**
	 * design-systems.json is read once per service instance (one request):
	 * a second call answers from the cache even after the file changed.
	 *
	 * @spec openspec/specs/css-architecture/spec.md#design-systems-are-cached-per-request
	 */
	public function testGetDesignSystemsReadsTheManifestOncePerInstance(): void {
		$service = $this->service();
		$first = $service->getDesignSystems();
		$this->assertArrayHasKey('nldesign', $first);

		file_put_contents($this->appDir . '/design-systems.json', '[]');

		$this->assertSame($first, $service->getDesignSystems());
		// A fresh instance (a new request) does read the changed file.
		$this->assertSame([], $this->service()->getDesignSystems());
	}//end testGetDesignSystemsReadsTheManifestOncePerInstance()
}//end class
