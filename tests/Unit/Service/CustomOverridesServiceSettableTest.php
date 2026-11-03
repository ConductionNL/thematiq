<?php

/**
 * An admin override of a settable Nextcloud variable is stored as its token.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/nextcloud-variable-mapping/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomOverridesService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\OverridesStorage;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The theme scopes read `--nldesign-nc-*` tokens on body's children, so a
 * plain `--color-mark` on `:root` would never reach the page. The writer
 * stores the token; the editor still reads and writes the Nextcloud name.
 */
class CustomOverridesServiceSettableTest extends TestCase {

	private string $dir;
	private DirectoryRuntimeFileStore $store;
	private CustomOverridesService $service;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/thematiq-settable-' . bin2hex(random_bytes(4));
		mkdir($this->dir, 0777, true);
		$this->store = new DirectoryRuntimeFileStore(root: $this->dir);
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$parser = new CssParserService();
		$dark = new DarkPaletteService(
			contrast: new ContrastService(),
			parser: $parser,
			appManager: $appManager,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			store: $this->store
		);
		$this->service = new CustomOverridesService(storage: new OverridesStorage(store: $this->store, cssParser: $parser, darkPalette: $dark));
	}//end setUp()

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach (glob($this->dir . '/css/*') ?: [] as $file) {
			unlink($file);
		}

		@rmdir($this->dir . '/css');
		@rmdir($this->dir);
		parent::tearDown();
	}//end tearDown()

	private function file(): string {
		return (string)$this->store->read('css/custom-overrides.css');
	}//end file()

	/**
	 * A settable value is stored as its token in the light scope only.
	 *
	 * @return void
	 */
	public function testASettableValueIsStoredAsItsTokenInTheLightScopeOnly(): void {
		$this->service->write(tokens: ['--color-mark' => '#ffe08a', '--color-primary' => '#24578f']);

		$css = $this->file();
		$root = substr($css, 0, (int)strpos($css, '}'));
		$this->assertStringContainsString(needle: '--nldesign-nc-color-mark: #ffe08a !important;', haystack: $root);
		$this->assertStringNotContainsString(needle: '  --color-mark:', haystack: $css, message: 'the plain variable would lose to the theme scopes');
		$dark = substr($css, (int)strpos($css, '}'));
		$this->assertStringContainsString(needle: '--color-primary:', haystack: $dark, message: 'a brand colour still gets its derived dark value');
		$this->assertStringNotContainsString(
			needle: '--nldesign-nc-color-mark',
			haystack: $dark,
			message: 'a settable value gets no derived dark value'
		);
	}//end testASettableValueIsStoredAsItsTokenInTheLightScopeOnly()

	/**
	 * The editor reads the nextcloud name.
	 *
	 * @return void
	 */
	public function testTheEditorReadsTheNextcloudName(): void {
		$this->service->write(tokens: ['--color-mark' => '#ffe08a', '--header-height' => '56px']);

		$this->assertSame(expected: ['--color-mark' => '#ffe08a', '--header-height' => '56px'], actual: $this->service->read());
	}//end testTheEditorReadsTheNextcloudName()

	/**
	 * An older plain declaration still reads and is saved as the token.
	 *
	 * @return void
	 */
	public function testAnOlderPlainDeclarationStillReadsAndIsSavedAsTheToken(): void {
		$this->store->write('css/custom-overrides.css', ":root {\n  --color-scrollbar: #cccccc !important;\n}\n");

		$this->assertSame(expected: ['--color-scrollbar' => '#cccccc'], actual: $this->service->read());

		$this->service->write(tokens: $this->service->read());
		$this->assertStringContainsString(needle: '--nldesign-nc-color-scrollbar: #cccccc !important;', haystack: $this->file());
	}//end testAnOlderPlainDeclarationStillReadsAndIsSavedAsTheToken()

	/**
	 * An icon is still not editable.
	 *
	 * @return void
	 */
	public function testAnIconIsStillNotEditable(): void {
		$this->assertSame(
			expected: ['--icon-download-dark' => 'not an editable token'],
			actual: $this->service->findRejected(['--icon-download-dark' => 'url(x.svg)'])
		);
	}//end testAnIconIsStillNotEditable()
}//end class
