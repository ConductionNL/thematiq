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

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/thematiq-settable-' . bin2hex(random_bytes(4));
		mkdir($this->dir, 0777, true);
		$this->store = new DirectoryRuntimeFileStore($this->dir);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));
		$parser = new CssParserService();
		$dark = new DarkPaletteService(new ContrastService(), $parser, $appManager, $this->createMock(LoggerInterface::class), $this->store);
		$this->service = new CustomOverridesService($this->store, $parser, $dark);
	}//end setUp()

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

	public function testASettableValueIsStoredAsItsTokenInTheLightScopeOnly(): void {
		$this->service->write(tokens: ['--color-mark' => '#ffe08a', '--color-primary' => '#24578f']);

		$css = $this->file();
		$root = substr($css, 0, (int)strpos($css, '}'));
		$this->assertStringContainsString('--nldesign-nc-color-mark: #ffe08a !important;', $root);
		$this->assertStringNotContainsString('  --color-mark:', $css, 'the plain variable would lose to the theme scopes');
		$dark = substr($css, (int)strpos($css, '}'));
		$this->assertStringContainsString('--color-primary:', $dark, 'a brand colour still gets its derived dark value');
		$this->assertStringNotContainsString('--nldesign-nc-color-mark', $dark, 'a settable value gets no derived dark value');
	}//end testASettableValueIsStoredAsItsTokenInTheLightScopeOnly()

	public function testTheEditorReadsTheNextcloudName(): void {
		$this->service->write(tokens: ['--color-mark' => '#ffe08a', '--header-height' => '56px']);

		$this->assertSame(['--color-mark' => '#ffe08a', '--header-height' => '56px'], $this->service->read());
	}//end testTheEditorReadsTheNextcloudName()

	public function testAnOlderPlainDeclarationStillReadsAndIsSavedAsTheToken(): void {
		$this->store->write('css/custom-overrides.css', ":root {\n  --color-scrollbar: #cccccc !important;\n}\n");

		$this->assertSame(['--color-scrollbar' => '#cccccc'], $this->service->read());

		$this->service->write(tokens: $this->service->read());
		$this->assertStringContainsString('--nldesign-nc-color-scrollbar: #cccccc !important;', $this->file());
	}//end testAnOlderPlainDeclarationStillReadsAndIsSavedAsTheToken()

	public function testAnIconIsStillNotEditable(): void {
		$this->assertSame(['--icon-download-dark' => 'not an editable token'], $this->service->findRejected(['--icon-download-dark' => 'url(x.svg)']));
	}//end testAnIconIsStillNotEditable()
}//end class
