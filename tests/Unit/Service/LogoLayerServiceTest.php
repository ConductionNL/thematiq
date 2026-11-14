<?php

/**
 * Unit tests for LogoLayerService: the exact logo layer for a set with its
 * own logo (with and without watermark and emblem), with an uploaded logo,
 * with core's masked logo and without one, and the dark logo layer.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CssInjectionService;
use OCA\Thematiq\Service\LogoLayerService;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Pins every logo layer byte for byte.
 *
 * @spec openspec/specs/app-token-set-selection/spec.md
 */
final class LogoLayerServiceTest extends TestCase {

	/**
	 * Build the service.
	 *
	 * @param array<int, string> $files The files that exist, app-relative.
	 * @param array<string, string> $theming The theming app values.
	 * @param bool $coreLogo Whether core's logo resolves.
	 * @param array<string, string> $urls Names whose url differs from `/u/<name>`.
	 *
	 * @return LogoLayerService
	 */
	private function service(array $files, array $theming = [], bool $coreLogo = true, array $urls = []): LogoLayerService {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(fn (string $app, string $key, $default = '') => ($theming[$key] ?? $default));
		$generator = $this->createMock(IURLGenerator::class);
		$generator->method('imagePath')->willReturnCallback(
			function (string $appName, string $file) use ($coreLogo): string {
				if ($coreLogo === false) {
					throw new RuntimeException('no logo');
				}

				return '/core/img/' . $file;
			}
		);
		$locator = $this->createMock(RuntimeFileLocator::class);
		$locator->method('exists')->willReturnCallback(fn (string $name): bool => in_array($name, $files, true));
		$locator->method('url')->willReturnCallback(fn (string $name): ?string => (array_key_exists($name, $urls) === true ? $urls[$name] : '/u/' . $name));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($coreLogo === true ? $this->never() : $this->once())->method('debug');

		return new LogoLayerService(config: $config, urlGenerator: $generator, logger: $logger, files: $locator);
	}//end service()

	/**
	 * A logo layer entry.
	 *
	 * @param string $css The body.
	 *
	 * @return array<string, string>
	 */
	private function entry(string $css): array {
		return ['layer' => 'logo-url', 'kind' => 'inline', 'css' => $css, 'id' => CssInjectionService::LOGO_STYLE_ID];
	}//end entry()

	/**
	 * A set's own logo, the first extension that exists, with its watermark and emblem when it ships them.
	 *
	 * @return void
	 */
	public function testASetsOwnLogoWithItsWatermarkAndEmblem(): void {
		$plain = $this->service(files: ['img/logos/a.png', 'img/logos/a.webp'])->layer(tokenSet: 'a');
		$full = $this->service(files: ['img/logos/b.svg', 'img/logos/b-emblem-grey.svg', 'img/logos/b-emblem.png'])->layer(tokenSet: 'b');

		$variables = '--nldesign-header-logo-image:var(--nldesign-logo-url);';
		$this->assertStringStartsWith(prefix: ':root{--nldesign-logo-url:url(/u/img/logos/a.png);' . $variables, string: (string)$plain['css']);
		$this->assertStringEndsWith(suffix: '}', string: (string)$plain['css']);
		$this->assertStringNotContainsString(needle: 'watermark', haystack: (string)$plain['css']);
		$this->assertSame(expected: ['layer', 'kind', 'css', 'id'], actual: array_keys((array)$plain));
		$this->assertStringEndsWith(
			suffix: ';--nldesign-login-watermark-image:url(/u/img/logos/b-emblem-grey.svg);--nldesign-emblem-url:url(/u/img/logos/b-emblem.png)}',
			string: (string)$full['css']
		);
		$this->assertSame(expected: 'logo-url', actual: $full['layer']);
	}//end testASetsOwnLogoWithItsWatermarkAndEmblem()

	/**
	 * A logo uploaded on core's Theming page wins over the set's own logo; the
	 * set's watermark and emblem still apply (REQ-TPLAT-001).
	 *
	 * @spec openspec/specs/platform-properties/spec.md#REQ-TPLAT-001
	 *
	 * @return void
	 */
	public function testAnUploadedCoreLogoWinsOverTheSetsOwnLogo(): void {
		$files = ['img/logos/b.svg', 'img/logos/b-emblem-grey.svg', 'img/logos/b-emblem.png'];
		foreach (['logoMime' => 'image/png', 'logoheaderMime' => 'image/svg+xml'] as $key => $mime) {
			$css = (string)$this->service(files: $files, theming: [$key => $mime])->layer(tokenSet: 'b')['css'];

			$this->assertStringStartsWith(prefix: ':root{--nldesign-logo-url:var(--image-logoheader,var(--image-logo));--nldesign-logo-filter:none;', string: $css, message: $key);
			$this->assertStringNotContainsString(needle: 'url(/u/img/logos/b.svg)', haystack: $css, message: $key);
			$this->assertStringEndsWith(
				suffix: ';--nldesign-login-watermark-image:url(/u/img/logos/b-emblem-grey.svg);--nldesign-emblem-url:url(/u/img/logos/b-emblem.png)}',
				string: $css,
				message: $key
			);
		}

		// Without an upload the set's own logo shows, as before.
		$this->assertStringContainsString(needle: 'url(/u/img/logos/b.svg)', haystack: (string)$this->service(files: $files)->layer(tokenSet: 'b')['css']);
	}//end testAnUploadedCoreLogoWinsOverTheSetsOwnLogo()

	/**
	 * Without a set logo an uploaded logo is shown unfiltered; without either core's logo is masked; without that, nothing.
	 *
	 * @return void
	 */
	public function testTheFallbacksWhenASetShipsNoLogo(): void {
		$uploaded = $this->service(files: [], theming: ['logoMime' => 'image/png'])->layer(tokenSet: 'x');
		$header = $this->service(files: [], theming: ['logoheaderMime' => 'image/svg+xml'])->layer(tokenSet: 'x');
		$masked = $this->service(files: [])->layer(tokenSet: 'x');
		$none = $this->service(files: [], coreLogo: false)->layer(tokenSet: 'x');

		$this->assertStringStartsWith(
			prefix: ':root{--nldesign-logo-url:var(--image-logoheader,var(--image-logo));--nldesign-logo-filter:none;--nldesign-header-logo-image:',
			string: (string)$uploaded['css']
		);
		$this->assertSame(expected: $uploaded, actual: $header);
		$mask = 'url(/core/img/logo/logo.svg) no-repeat center / contain!important';
		$this->assertSame(
			expected: $this->entry(
				css: '#nextcloud .logo{background-image:none!important;'
					. 'background-color:var(--nldesign-header-mark-fill,var(--nldesign-color-header-text,#333333))!important;'
					. 'filter:none!important;-webkit-mask:' . $mask . ';mask:' . $mask . '}'
			),
			actual: $masked
		);
		$this->assertNull(actual: $none);
	}//end testTheFallbacksWhenASetShipsNoLogo()

	/**
	 * The dark layer needs a light and a dark logo and a url; a brand logo removes it.
	 *
	 * @return void
	 */
	public function testTheDarkLogoLayer(): void {
		$pair = ['img/logos/a.svg', 'img/logos/a-dark.png'];
		$layer = $this->service(files: $pair)->darkLayer(tokenSet: 'a');

		$declarations = '{--nldesign-logo-url:url(/u/img/logos/a-dark.png);--nldesign-header-logo-image:url(/u/img/logos/a-dark.png)}';
		$this->assertSame(
			expected: [
				'layer' => 'dark-logo-url',
				'kind' => 'inline',
				'css' => '@media (prefers-color-scheme: dark){'
					. 'body:not([data-theme-light]):not([data-theme-dark]):not([data-theme-light-highcontrast]):not([data-theme-dark-highcontrast])'
					. $declarations . '}body[data-theme-dark],body[data-themes*=dark]' . $declarations,
				'id' => CssInjectionService::DARK_LOGO_STYLE_ID,
			],
			actual: $layer
		);
		$this->assertSame(expected: [$layer], actual: $this->service(files: $pair)->darkLayers(tokenSet: 'a', brandLogo: false));
		$this->assertSame(expected: [], actual: $this->service(files: $pair)->darkLayers(tokenSet: 'a', brandLogo: true));
		$this->assertNull(actual: $this->service(files: ['img/logos/a-dark.png'])->darkLayer(tokenSet: 'a'));
		$this->assertNull(actual: $this->service(files: ['img/logos/a.svg'])->darkLayer(tokenSet: 'a'));
		$this->assertNull(actual: $this->service(files: $pair, urls: ['img/logos/a-dark.png' => ''])->darkLayer(tokenSet: 'a'));
		$this->assertNull(actual: $this->service(files: $pair, urls: ['img/logos/a-dark.png' => null])->darkLayer(tokenSet: 'a'));
		$this->assertSame(expected: [], actual: $this->service(files: [])->darkLayers(tokenSet: 'a', brandLogo: false));
	}//end testTheDarkLogoLayer()
}//end class
