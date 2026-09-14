<?php

/**
 * Unit tests for what an upload actually WRITES, as opposed to what it accepts.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Controller;

use OCA\Thematiq\Controller\CustomTokenSetController;
use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Since the converter landed, the bytes `store()` writes are the CONVERTER'S
 * emitted CSS, not `serialize($accepted)`. The accepted/skipped split is still
 * computed, but it no longer describes the file — and two things follow from
 * that, both of which are asserted here rather than left to reading.
 *
 * FIRST, THE VALUE GATE HAS TO COVER MORE THAN THE ACCEPTED SET. The converter
 * parses `--[\w-]+` and keeps a name it does not recognise verbatim; the
 * validator's name rule is `[a-z0-9-]+`. So `--utrecht-colorPrimary` is
 * emitted by one and rejected by the other, and while the value check sat
 * behind the name check, its value was written to disk unjudged.
 * `stripExternalUrls()` does not cover this: it matches `url(http…)` only,
 * where `isForbiddenValue()` also covers `@import`, `expression(`,
 * `javascript:` and injection characters.
 *
 * SECOND, THE STORED FILE IS NOT THE UPLOADED FILE. A hand-authored
 * `--nldesign-*` document — the one shape the endpoint accepted before this
 * change, and stored verbatim — is now backfilled from the mapping table's
 * fallbacks, so a two-declaration upload is stored as forty. That is a
 * deliberate consequence of routing everything through the converter, and it
 * is the only pre-existing production path through it, so it is pinned here.
 *
 * @spec openspec/specs/custom-token-sets/spec.md
 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
 */
class CustomTokenSetUploadWritesTest extends TestCase {

	/**
	 * The temp app directory the service writes into.
	 *
	 * @var string
	 */
	private string $appDir;

	/**
	 * In-memory appconfig store.
	 *
	 * @var array<string, string>
	 */
	private array $appConfig = [];

	/**
	 * The request mock, reconfigured per test.
	 *
	 * @var IRequest&\PHPUnit\Framework\MockObject\MockObject
	 */
	private $request;

	/**
	 * The controller under test.
	 *
	 * @var CustomTokenSetController
	 */
	private CustomTokenSetController $controller;

	/**
	 * The storage service, exercised for real against the temp dir.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $service;

	/**
	 * Wire the controller over a temp app dir and a real converter.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appDir = sys_get_temp_dir() . '/thematiq-upload-writes-' . uniqid();
		mkdir($this->appDir . '/css/tokens', 0777, true);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($this->appDir);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			fn (string $app, string $key, $default = '') => ($this->appConfig[$key] ?? $default)
		);
		$config->method('setAppValue')->willReturnCallback(
			function (string $app, string $key, $value): void {
				$this->appConfig[$key] = (string)$value;
			}
		);

		$this->service = new CustomTokenSetService(
			$appManager,
			$config,
			new CustomTokenSetValidator(),
			new ContrastService(),
			new DarkPaletteService(
				new ContrastService(),
				new CssParserService(),
				$appManager,
				$this->createMock(LoggerInterface::class)
			)
		);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(fn (string $text, $p = []) => (empty($p) ? $text : vsprintf($text, $p)));

		// The converter reads its mapping table and vocabulary stylesheets from
		// the REPO; everything it writes still goes through the service above.
		$repoAppManager = $this->createMock(IAppManager::class);
		$repoAppManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$this->request = $this->createMock(IRequest::class);
		$this->controller = new CustomTokenSetController(
			'thematiq',
			$this->request,
			$this->service,
			new CustomTokenSetValidator(),
			new CssParserService(),
			$l,
			$this->createMock(ThemingAuditService::class),
			$config,
			new TokenSetConverterService(
				$repoAppManager,
				new CssParserService(),
				new ContrastService(),
				new DesignTokensMapper(),
				$this->createMock(FontService::class),
				$this->createMock(LoggerInterface::class)
			),
			$this->createMock(ThemingService::class)
		);
	}//end setUp()

	/**
	 * Remove the temp app dir.
	 */
	protected function tearDown(): void {
		$this->rrmdir($this->appDir);
		parent::tearDown();
	}//end tearDown()

	/**
	 * Recursively remove a directory tree.
	 *
	 * @param string $dir The directory to remove.
	 *
	 * @return void
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
	 * Drive one upload of the given CSS.
	 *
	 * @param string $css  The uploaded document.
	 * @param string $name The token set display name.
	 *
	 * @return \OCP\AppFramework\Http\JSONResponse The upload response.
	 */
	private function upload(string $css, string $name = 'Gemeente Voorbeeld', string $fileName = 'theme.css') {
		$tmpFile = sys_get_temp_dir() . '/thematiq-upload-' . uniqid();
		file_put_contents($tmpFile, $css);

		$this->request->method('getParam')->willReturnCallback(
			fn (string $key, $default = null) => ($key === 'name' ? $name : $default)
		);
		$this->request->method('getUploadedFile')->willReturn(
			['tmp_name' => $tmpFile, 'name' => $fileName, 'size' => strlen($css)]
		);

		return $this->controller->upload();
	}//end upload()

	/**
	 * What a consumer of the stored file would actually execute: the bytes with
	 * every CSS comment removed.
	 *
	 * Stripped NON-GREEDILY, the way `CustomTokenSetValidator::hasDisallowedSelector()`
	 * does, because that is the weaker of the two readings — a terminator
	 * smuggled into a comment ends the strip early there and leaves whatever
	 * follows as live text.
	 *
	 * @param string $id The stored set id.
	 *
	 * @return string The stored CSS with comments removed.
	 */
	private function liveCssOf(string $id): string {
		return (string)preg_replace(
			'#/\*.*?\*/#s',
			'',
			(string)$this->service->getRawContent(id: $id)
		);
	}//end liveCssOf()

	/**
	 * The CSS files present under the temp app dir.
	 *
	 * @return array<int, string> The file names.
	 */
	private function writtenFiles(): array {
		return array_map('basename', (glob($this->appDir . '/css/tokens/*.css') ?: []));
	}//end writtenFiles()

	/**
	 * A forbidden value under a name the validator does NOT accept is refused,
	 * and nothing is written. The converter emits such a name verbatim, so
	 * before the value gate moved ahead of the name split this reached disk
	 * unjudged.
	 *
	 * @param string $name  The declaration name the converter keeps verbatim.
	 * @param string $value The forbidden value.
	 *
	 * @dataProvider smuggledValueProvider
	 *
	 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-1
	 */
	public function testAForbiddenValueUnderANameTheValidatorSkipsIsRefused(string $name, string $value): void {
		$css = ":root {\n  --nldesign-color-primary: #154273;\n  " . $name . ': ' . $value . ";\n}\n";

		$response = $this->upload($css);

		$this->assertSame(422, $response->getStatus());
		$this->assertStringContainsString($name, $response->getData()['error']);
		$this->assertStringContainsString('forbidden value', $response->getData()['error']);

		// Nothing partial: no manifest entry, no file.
		$this->assertSame([], $this->service->list());
		$this->assertSame([], $this->writtenFiles());
	}//end testAForbiddenValueUnderANameTheValidatorSkipsIsRefused()

	/**
	 * Names the converter keeps verbatim but the validator's `[a-z0-9-]+` rule
	 * refuses, each carrying a construct `stripExternalUrls()` does not catch.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function smuggledValueProvider(): array {
		return [
			'camelCase name, expression()' => ['--utrecht-colorPrimary', 'expression(alert(1))'],
			'snake_case name, javascript:' => ['--utrecht-color_secondary', 'javascript:alert(2)'],
			'camelCase name, raw markup' => ['--ams-brandColor', '<script>alert(3)</script>'],
			'snake_case name, javascript: under a third prefix' => ['--denhaag-brand_mark', 'javascript:alert(4)'],
		];
	}//end smuggledValueProvider()

	/**
	 * `@import` inside a VALUE never reaches the value gate, because the
	 * converter's at-rule handling consumes it first and the declaration is
	 * simply not emitted. Pinned separately from the cases above: it is a
	 * second, independent defence, and a test that lumped it in with them
	 * would claim the value gate catches something it never sees.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testAnImportInsideAValueIsDroppedBeforeItIsEverStored(): void {
		$response = $this->upload(
			":root {\n  --nldesign-color-primary: #154273;\n  --ams-brandColor: @import url(evil.css);\n}\n"
		);

		$this->assertSame(200, $response->getStatus());

		$stored = (string)$this->service->getRawContent(id: 'custom-gemeente-voorbeeld');
		$this->assertStringNotContainsString('@import', $stored);
		$this->assertStringNotContainsString('evil.css', $stored);
		$this->assertStringNotContainsString('--ams-brandColor', $stored);
	}//end testAnImportInsideAValueIsDroppedBeforeItIsEverStored()

	/**
	 * The same constructs under an ACCEPTED name are refused too — the gate
	 * did not move from one set to the other, it widened to cover both.
	 *
	 * @spec openspec/changes/harden-custom-token-set-value-validation/tasks.md#task-1
	 */
	public function testAForbiddenValueUnderAnAcceptedNameIsStillRefused(): void {
		$response = $this->upload(":root {\n  --nldesign-color-primary: expression(alert(1));\n}\n");

		$this->assertSame(422, $response->getStatus());
		$this->assertSame([], $this->writtenFiles());
	}//end testAForbiddenValueUnderAnAcceptedNameIsStillRefused()

	/**
	 * A component token whose name IS acceptable and whose value is ordinary
	 * still stores — the gate rejects values, not vocabularies, and the
	 * component layer is the reason those prefixes are accepted at all.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	public function testAWellFormedComponentTokenStillStores(): void {
		$response = $this->upload(
			":root {\n  --nldesign-color-primary: #154273;\n  --utrecht-button-border-radius: 4px;\n}\n"
		);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['custom-gemeente-voorbeeld.css'], $this->writtenFiles());

		$stored = (string)$this->service->getRawContent(id: 'custom-gemeente-voorbeeld');
		$this->assertStringContainsString('--utrecht-button-border-radius: 4px;', $stored);
	}//end testAWellFormedComponentTokenStillStores()

	/**
	 * THE PROVENANCE COMMENT IS A SECOND BYTE-PATH TO DISK. It is built into
	 * the emitted file and never passes the declaration gate, because the
	 * parser only ever collects `--*` names — so a payload that is not a custom
	 * property is dropped before the gate while its bytes are already written.
	 *
	 * A `*​/` in the uploaded FILENAME closed the comment early and turned
	 * everything after it into live CSS, in a file served to anonymous visitors
	 * on the login page.
	 *
	 * @param string $payload The injected provenance value.
	 *
	 * @dataProvider provenancePayloadProvider
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testAFilenameCannotCloseTheProvenanceComment(string $payload): void {
		$response = $this->upload(
			":root {\n  --nldesign-color-primary: #154273;\n}\n",
			'Filename Probe',
			$payload
		);

		$this->assertSame(200, $response->getStatus());

		// The comment must still be one comment: nothing the filename carried
		// may survive as executable CSS.
		$live = $this->liveCssOf('custom-filename-probe');
		$this->assertStringNotContainsString('attacker.example', $live);
		$this->assertStringNotContainsString('expression(', $live);
		$this->assertStringNotContainsString('*/', $this->provenanceValueOf('custom-filename-probe', 'source:'));
	}//end testAFilenameCannotCloseTheProvenanceComment()

	/**
	 * The same through the version the converter reads out of the uploaded
	 * DOCUMENT. This one does not need a hostile admin — only an admin who
	 * uploads somebody else's theme.
	 *
	 * @param string $payload The injected provenance value.
	 *
	 * @dataProvider provenancePayloadProvider
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testADocumentVersionCannotCloseTheProvenanceComment(string $payload): void {
		$document = json_encode(
			[
				'$version' => $payload,
				'color' => ['primary' => ['$type' => 'color', '$value' => '#154273']],
			]
		);

		$response = $this->upload($document, 'Version Probe', 'theme.tokens.json');

		$this->assertSame(200, $response->getStatus());

		$live = $this->liveCssOf('custom-version-probe');
		$this->assertStringNotContainsString('attacker.example', $live);
		$this->assertStringNotContainsString('expression(', $live);
		$this->assertStringNotContainsString('*/', $this->provenanceValueOf('custom-version-probe', 'source version:'));
	}//end testADocumentVersionCannotCloseTheProvenanceComment()

	/**
	 * Payloads that close the provenance comment and continue with live CSS.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provenancePayloadProvider(): array {
		return [
			'external image' => ['*/ background-image:url(https://attacker.example/p.png); /*'],
			'expression()' => ['*/ zoom:expression(alert(1)); /*'],
			'a new selector' => ['*/ } a{background:url(https://attacker.example/p.png)} :root{ /*'],
			'a newline and a declaration' => ["*/\n\tbackground-image:url(https://attacker.example/p.png);\n\t/*"],
		];
	}//end provenancePayloadProvider()

	/**
	 * The provenance line carrying the given label, as stored.
	 *
	 * @param string $id    The stored set id.
	 * @param string $label The provenance label, e.g. `source:`.
	 *
	 * @return string The rest of that line, or '' when absent.
	 */
	private function provenanceValueOf(string $id, string $label): string {
		foreach (explode("\n", (string)$this->service->getRawContent(id: $id)) as $line) {
			if (str_contains($line, $label) === true) {
				return trim(substr($line, (strpos($line, $label) + strlen($label))));
			}
		}

		return '';
	}//end provenanceValueOf()

	/**
	 * A legitimate filename still reaches the provenance block — the guard
	 * strips comment terminators, it does not blank the field.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testAnOrdinaryFilenameIsStillRecorded(): void {
		$this->upload(
			":root {\n  --nldesign-color-primary: #154273;\n}\n",
			'Gemeente Voorbeeld',
			'gemeente-voorbeeld-tokens.css'
		);

		$this->assertSame(
			'gemeente-voorbeeld-tokens.css',
			$this->provenanceValueOf('custom-gemeente-voorbeeld', 'source:')
		);
	}//end testAnOrdinaryFilenameIsStillRecorded()

	/**
	 * THE STORED FILE IS NOT THE UPLOADED FILE. A hand-authored `--nldesign-*`
	 * document is the one input shape that worked before the converter, and it
	 * was stored verbatim. It is now backfilled from the mapping table, so the
	 * admin's two declarations are stored as a complete semantic layer.
	 *
	 * The admin's own values are what must survive that: a backfill that
	 * overwrote a declared value would silently re-theme their brand.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	public function testAHandAuthoredUploadIsStoredBackfilledButNeverOverwritten(): void {
		$response = $this->upload(
			":root {\n  --nldesign-color-primary: #7d3f98;\n  --nldesign-color-primary-text: #ffffff;\n}\n"
		);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame('D', $response->getData()['inputKind']);
		// `imported` counts what the ADMIN'S document yielded, not the file.
		$this->assertSame(2, $response->getData()['imported']);

		$stored = (string)$this->service->getRawContent(id: 'custom-gemeente-voorbeeld');
		$declarations = (new CssParserService())->parseRootBlock(css: $stored);

		// What the admin wrote survives exactly.
		$this->assertSame('#7d3f98', $declarations['--nldesign-color-primary']);
		$this->assertSame('#ffffff', $declarations['--nldesign-color-primary-text']);

		// And the file carries far more than was uploaded, filled from the
		// mapping table's fallbacks rather than left to the cascade.
		$this->assertGreaterThan(2, count($declarations));
		$this->assertArrayHasKey('--nldesign-color-link', $declarations);
		$this->assertArrayHasKey('--nldesign-border-radius', $declarations);

		// The report says so per token, so the backfill is inspectable rather
		// than silent: every derived token names why it was derived.
		$derived = array_filter(
			$response->getData()['report'],
			static fn (array $entry): bool => ($entry['action'] ?? '') === 'adapted'
		);
		$this->assertNotEmpty($derived);
	}//end testAHandAuthoredUploadIsStoredBackfilledButNeverOverwritten()
}//end class
