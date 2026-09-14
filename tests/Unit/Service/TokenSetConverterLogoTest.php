<?php

/**
 * Unit tests for TokenSetConverterService's asset path: pulling a theme's
 * wordmark out of a token and deciding what may be written to disk.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The logo is the one thing a conversion takes OUT of the stylesheet and puts
 * on disk, which makes it the only part of the converter that can write a file
 * whose bytes came from an uploaded document. Everything here is therefore
 * about what is refused, and about the fact that a refusal is reported rather
 * than silently dropped.
 *
 * Four refusals have to hold, and none of them is visible from the emitted
 * CSS — a refused logo leaves a token set that looks entirely correct:
 *
 *  - an image type Nextcloud's ImageManager does not take;
 *  - a payload over the table's `maxBytes`, which is what stops a 40 KB
 *    wordmark being inlined into a file served to every anonymous visitor;
 *  - a `url()` target with a scheme or authority, which is the external-host
 *    case the app refuses everywhere else;
 *  - a relative path that resolves outside the table's logo directory, because
 *    the path travels on to `ThemingService::validateSinglePath()` and
 *    Nextcloud core theming.
 *
 * And the report must not carry the payload it rejected: it is rendered in the
 * admin panel and stored in the audit log, so a 40 KB data URI must not travel
 * through either.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */
class TokenSetConverterLogoTest extends TestCase {

	/**
	 * The converter under test.
	 *
	 * @var TokenSetConverterService
	 */
	private TokenSetConverterService $converter;

	/**
	 * Build the converter against the repository root, which is where its
	 * mapping table and the two vocabulary stylesheets live.
	 */
	protected function setUp(): void {
		parent::setUp();

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(\dirname(__DIR__, 3));

		$this->converter = new TokenSetConverterService(
			$appManager,
			new CssParserService(),
			new ContrastService(),
			new DesignTokensMapper(),
			$this->createMock(FontService::class),
			$this->createMock(LoggerInterface::class)
		);
	}//end setUp()

	/**
	 * Convert a `:root` block carrying the given declarations.
	 *
	 * @param array<string, string> $declarations Declaration name => value.
	 *
	 * @return array<string, mixed> The conversion result.
	 */
	private function convert(array $declarations): array {
		$css = ":root {\n";
		foreach ($declarations as $name => $value) {
			$css .= '  ' . $name . ': ' . $value . ";\n";
		}

		$css .= "}\n";

		return $this->converter->convert(
			content: $css,
			slug: 'voorbeeld',
			displayName: 'Voorbeeld',
			sourceName: 'theme.css',
			assetName: 'custom-voorbeeld'
		);
	}//end convert()

	/**
	 * The report entries naming the logo target.
	 *
	 * @param array<string, mixed> $result The conversion result.
	 *
	 * @return array<int, array<string, mixed>> The logo report entries.
	 */
	private function logoEntries(array $result): array {
		return array_values(
			array_filter(
				$result['report'],
				static fn (array $entry): bool => ($entry['target'] ?? '') === '--nldesign-logo-url'
			)
		);
	}//end logoEntries()

	/**
	 * A base64 `data:image/svg+xml` payload — the shape an NL Design System
	 * theme actually ships its wordmark in — is decoded, reported as applied
	 * and handed back as bytes to write under the SET's id, so an uploaded
	 * theme called "Amsterdam" cannot overwrite the shipped logo.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testBase64SvgLogoIsDecodedAndNamedAfterTheSet(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';
		$uri = 'data:image/svg+xml;base64,' . base64_encode($svg);

		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => 'url("' . $uri . '")',
			]
		);

		$this->assertIsArray($result['logoAsset']);
		$this->assertSame('img/logos/custom-voorbeeld.svg', $result['logoAsset']['path']);
		$this->assertSame($svg, $result['logoAsset']['contents']);
		$this->assertStringContainsString(
			"--nldesign-logo-url: url('../../img/logos/custom-voorbeeld.svg')",
			$result['css']
		);

		$entries = $this->logoEntries($result);
		$this->assertCount(1, $entries);
		$this->assertSame('applied', $entries[0]['action']);
		$this->assertSame('logo-extracted', $entries[0]['reason']);

		// The payload never reaches the emitted stylesheet.
		$this->assertStringNotContainsString('data:image/svg+xml', $result['css']);
	}//end testBase64SvgLogoIsDecodedAndNamedAfterTheSet()

	/**
	 * A percent-encoded data URI — what a hand-written inline SVG looks like —
	 * is decoded too. Only the `;base64` parameter selects base64; without it
	 * the payload is URL-encoded, and treating it as base64 would produce
	 * bytes that are not the image.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testPercentEncodedLogoIsDecodedWithoutBase64(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"/>';
		$uri = 'data:image/svg+xml,' . rawurlencode($svg);

		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => "url('" . $uri . "')",
			]
		);

		$this->assertIsArray($result['logoAsset']);
		$this->assertSame($svg, $result['logoAsset']['contents']);
		$this->assertSame('img/logos/custom-voorbeeld.svg', $result['logoAsset']['path']);
	}//end testPercentEncodedLogoIsDecodedWithoutBase64()

	/**
	 * A PNG payload lands under the extension the table maps the mime to, not
	 * under the mime's own spelling — the file has to be servable by name.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testJpegLogoLandsUnderTheMappedExtension(): void {
		$uri = 'data:image/jpeg;base64,' . base64_encode('not-really-a-jpeg-but-bytes');

		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => 'url(' . $uri . ')',
			]
		);

		$this->assertIsArray($result['logoAsset']);
		$this->assertSame('img/logos/custom-voorbeeld.jpg', $result['logoAsset']['path']);
	}//end testJpegLogoLandsUnderTheMappedExtension()

	/**
	 * An image type the table does not list is refused and REPORTED — the
	 * conversion still succeeds, because a theme without a usable logo is
	 * still a usable theme.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testUnsupportedImageTypeIsRefusedAndReported(): void {
		$uri = 'data:image/tiff;base64,' . base64_encode('II*');

		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => 'url("' . $uri . '")',
			]
		);

		$this->assertNull(($result['logoAsset'] ?? null));

		$entries = $this->logoEntries($result);
		$this->assertCount(1, $entries);
		$this->assertSame('skipped', $entries[0]['action']);
		$this->assertSame('logo-format-unsupported', $entries[0]['reason']);
	}//end testUnsupportedImageTypeIsRefusedAndReported()

	/**
	 * A payload over the table's `maxBytes` is refused, and the report carries
	 * a TRUNCATED stand-in rather than the payload: the report is rendered in
	 * the admin panel and written to the audit log.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testOversizedLogoIsRefusedAndNotEchoedIntoTheReport(): void {
		$oversized = str_repeat('A', (262144 + 16));
		$uri = 'data:image/png;base64,' . base64_encode($oversized);

		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => 'url("' . $uri . '")',
			]
		);

		$this->assertNull(($result['logoAsset'] ?? null));

		$entries = $this->logoEntries($result);
		$this->assertCount(1, $entries);
		$this->assertSame('logo-format-unsupported', $entries[0]['reason']);
		$this->assertLessThanOrEqual(120, strlen((string)$entries[0]['value']));
		$this->assertStringEndsWith('…', (string)$entries[0]['value']);
	}//end testOversizedLogoIsRefusedAndNotEchoedIntoTheReport()

	/**
	 * A relative path that already lives in the table's logo directory is kept
	 * as-is: the theme resolved it itself, so there are no bytes to write.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testAnExistingLocalLogoPathIsKeptWithoutWritingBytes(): void {
		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => "url('../../img/logos/amsterdam.svg')",
			]
		);

		// No bytes to write, so no asset — but core theming still gets the
		// path, and the emitted set still points at the same file.
		$this->assertNull(($result['logoAsset'] ?? null));
		$this->assertSame('img/logos/amsterdam.svg', $result['manifestEntry']['theming']['logo']);
		$this->assertStringContainsString(
			"--nldesign-logo-url: url('../../img/logos/amsterdam.svg')",
			$result['css']
		);
	}//end testAnExistingLocalLogoPathIsKeptWithoutWritingBytes()

	/**
	 * A relative target outside the logo directory, or one carrying a scheme
	 * or authority, is not a local logo path. The value travels on to core
	 * theming, which only accepts `img/logos/` and `img/backgrounds/`, so
	 * accepting it here would hand core a path it must then refuse.
	 *
	 * @param string $target The `url()` target.
	 *
	 * @dataProvider rejectedLocalTargetProvider
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testALocalTargetOutsideTheLogoDirectoryIsRefused(string $target): void {
		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => "url('" . $target . "')",
			]
		);

		$this->assertNull(($result['logoAsset'] ?? null));
		$this->assertSame('logo-format-unsupported', $this->logoEntries($result)[0]['reason']);
	}//end testALocalTargetOutsideTheLogoDirectoryIsRefused()

	/**
	 * Relative targets that are not an acceptable local logo path.
	 *
	 * Remote targets are deliberately NOT in this list: they never reach the
	 * logo step at all — see the external-host test below.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function rejectedLocalTargetProvider(): array {
		return [
			'another app directory' => ['../../css/tokens/utrecht.css'],
			'climbs out after the prefix' => ['img/logos/../../../etc/passwd'],
			'outside both permitted directories' => ['img/icons/logo.svg'],
		];
	}//end rejectedLocalTargetProvider()

	/**
	 * A logo pointing at a remote host is dropped by the external-URL policy
	 * BEFORE the logo step runs, so it is reported as a blocked external URL
	 * rather than as an unsupported logo format. The distinction matters in
	 * the panel: one tells the admin their theme references another server,
	 * the other tells them to re-export their artwork.
	 *
	 * @param string $target The `url()` target.
	 *
	 * @dataProvider remoteTargetProvider
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testARemoteLogoIsBlockedBeforeTheLogoStep(string $target): void {
		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => "url('" . $target . "')",
			]
		);

		$this->assertNull(($result['logoAsset'] ?? null));
		$this->assertSame([], $this->logoEntries($result));
		$this->assertStringNotContainsString($target, $result['css']);

		$blocked = array_values(
			array_filter(
				$result['report'],
				static fn (array $entry): bool => ($entry['reason'] ?? '') === 'external-url-blocked'
			)
		);
		$this->assertCount(1, $blocked);
		$this->assertSame('--conduction-logo-header-background-image', $blocked[0]['source']);
	}//end testARemoteLogoIsBlockedBeforeTheLogoStep()

	/**
	 * Targets pointing at another host.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function remoteTargetProvider(): array {
		return [
			'protocol-relative authority' => ['//cdn.example/logo.svg'],
			'https host' => ['https://cdn.example/logo.svg'],
		];
	}//end remoteTargetProvider()

	/**
	 * `none` and an empty target are not a logo and not a failure either — a
	 * theme that explicitly switches the wordmark off has said something, and
	 * it must not be reported as an unsupported format.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testAnExplicitlyDisabledLogoIsNotAFailure(): void {
		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => 'none',
			]
		);

		$this->assertNull(($result['logoAsset'] ?? null));
		$this->assertSame([], $this->logoEntries($result));
	}//end testAnExplicitlyDisabledLogoIsNotAFailure()

	/**
	 * A theme repeats one wordmark across its header, navbar and footer slots.
	 * The first is the source; the others are recorded as aliases so they can
	 * be pointed at `--nldesign-logo-url` instead of each carrying a copy of
	 * the payload.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
	 */
	public function testIdenticalArtworkInOtherSlotsIsRecordedAsAnAlias(): void {
		$uri = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>');
		$value = 'url("' . $uri . '")';

		$result = $this->convert(
			[
				'--nldesign-color-primary' => '#154273',
				'--conduction-logo-header-background-image' => $value,
				'--conduction-logo-navbar-background-image' => $value,
				'--conduction-logo-footer-background-image' => $value,
			]
		);

		$this->assertIsArray($result['logoAsset']);

		// The other two slots survive — Conduction's apps read them — but as a
		// reference, so the payload is carried once instead of three times.
		$this->assertStringContainsString(
			'--conduction-logo-navbar-background-image: var(--nldesign-logo-url);',
			$result['css']
		);
		$this->assertStringContainsString(
			'--conduction-logo-footer-background-image: var(--nldesign-logo-url);',
			$result['css']
		);

		// The chosen source is not re-emitted at all: it became the logo url.
		$this->assertStringNotContainsString('--conduction-logo-header-background-image', $result['css']);
		$this->assertStringNotContainsString('data:image/svg+xml', $result['css']);
	}//end testIdenticalArtworkInOtherSlotsIsRecordedAsAnAlias()
}//end class
