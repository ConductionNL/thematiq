<?php

/**
 * Unit tests for EmailThemingService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Mail\NLDesignEMailTemplate;
use OCA\Thematiq\Service\EmailThemingService;
use OCA\Thematiq\Service\Exception\ConfigReadOnlyException;
use OCA\Thematiq\Service\Exception\FooterValidationException;
use OCA\Thematiq\Service\Exception\ForeignMailTemplateClassException;
use OCA\Thematiq\Service\RuntimeFile\DirectoryRuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCA\Thematiq\Service\TokenSetService;
use OCP\App\IAppManager;
use OCP\HintException;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EmailThemingService.
 */
class EmailThemingServiceTest extends TestCase {

	/**
	 * The config mock.
	 *
	 * @var IConfig
	 */
	private $config;

	/**
	 * The token set service mock.
	 *
	 * @var TokenSetService
	 */
	private $tokenSetService;

	/**
	 * The token set preview service mock.
	 *
	 * @var TokenSetPreviewService
	 */
	private $previewService;

	/**
	 * The URL generator mock.
	 *
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * The service under test.
	 *
	 * @var EmailThemingService
	 */
	private EmailThemingService $service;

	/**
	 * Set up mocks before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->tokenSetService = $this->createMock(originalClassName: TokenSetService::class);
		$this->previewService = $this->createMock(originalClassName: TokenSetPreviewService::class);
		$this->urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		$this->service = new EmailThemingService(
			config: $this->config,
			tokenSetService: $this->tokenSetService,
			previewService: $this->previewService,
			urlGenerator: $this->urlGenerator
		);
	}//end setUp()

	/**
	 * The service with the given stored logo path on the active set, over a file locator that has the given files.
	 *
	 * @param mixed $logo The stored `theming.logo`.
	 * @param array<string, string> $files File name => bytes.
	 *
	 * @return EmailThemingService
	 */
	private function serviceWithLogo(mixed $logo, array $files): EmailThemingService {
		$this->config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => (['token_set' => 'custom-a'][$key] ?? $default)
		);
		$this->tokenSetService->method('getAvailableTokenSets')->willReturn([['id' => 'custom-a', 'theming' => ['logo' => $logo]]]);
		$locator = $this->createMock(originalClassName: RuntimeFileLocator::class);
		$locator->method('read')->willReturnCallback(static fn (string $name): ?string => ($files[$name] ?? null));
		$locator->method('contentType')->willReturn('image/svg+xml');

		return new EmailThemingService(
			config: $this->config,
			tokenSetService: $this->tokenSetService,
			previewService: $this->previewService,
			urlGenerator: $this->urlGenerator,
			files: $locator
		);
	}//end serviceWithLogo()

	/**
	 * The active set's logo is read as bytes and a type.
	 *
	 * @return void
	 */
	public function testActiveLogoIsReadForEmbedding(): void {
		$service = $this->serviceWithLogo(logo: 'img/logos/custom-a.svg', files: ['img/logos/custom-a.svg' => '<svg/>']);

		$this->assertSame(expected: ['content' => '<svg/>', 'mimeType' => 'image/svg+xml'], actual: $service->getActiveEmailLogoImage());
	}//end testActiveLogoIsReadForEmbedding()

	/**
	 * Only an image under img/ is read, whatever the stored path says.
	 *
	 * @param mixed $logo The stored path.
	 *
	 * @return void
	 *
	 * @dataProvider refusedLogoPaths
	 */
	public function testAStoredPathOutsideTheImagesIsNotRead(mixed $logo): void {
		$files = [
			'config/img/a.svg' => 'secret',
			'config/config.php' => 'secret',
			'img/../config/config.svg' => 'secret',
			'img/a.php' => 'x',
			'/etc/passwd.svg' => 'secret',
		];
		$service = $this->serviceWithLogo(logo: $logo, files: $files);

		$this->assertNull(actual: $service->getActiveEmailLogoImage());
	}//end testAStoredPathOutsideTheImagesIsNotRead()

	/**
	 * Stored paths that must not be read.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function refusedLogoPaths(): array {
		return [
			'outside img' => ['config/config.php'],
			'img further down' => ['config/img/a.svg'],
			'traversal' => ['img/../config/config.svg'],
			'not an image' => ['img/a.php'],
			'absolute' => ['/etc/passwd.svg'],
			'not a string' => [['img/a.svg']],
			'missing file' => ['img/logos/none.svg'],
			'no logo' => [null],
		];
	}//end refusedLogoPaths()

	/**
	 * On the real file locator a stored path cannot climb out of img/, though the file is there to read.
	 *
	 * @param string $logo The stored path.
	 *
	 * @return void
	 *
	 * @dataProvider climbingLogoPaths
	 */
	public function testAStoredPathCannotClimbOutOfTheImages(string $logo): void {
		$appPath = sys_get_temp_dir() . '/thematiq-mail-logo-' . bin2hex(random_bytes(4));
		mkdir($appPath . '/img/logos', 0777, true);
		mkdir($appPath . '/config');
		file_put_contents($appPath . '/config/config.svg', 'secret');
		file_put_contents($appPath . '/img/logos/own.svg', '<svg/>');
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getAppPath')->willReturn($appPath);
		$locator = new RuntimeFileLocator(
			appManager: $appManager,
			store: new DirectoryRuntimeFileStore(root: $appPath . '/store'),
			urlGenerator: $this->urlGenerator,
			tempManager: $this->createMock(originalClassName: ITempManager::class)
		);
		$this->config->method('getAppValue')->willReturn('custom-a');
		$this->tokenSetService->method('getAvailableTokenSets')->willReturn([['id' => 'custom-a', 'theming' => ['logo' => $logo]]]);
		$service = new EmailThemingService(
			config: $this->config,
			tokenSetService: $this->tokenSetService,
			previewService: $this->previewService,
			urlGenerator: $this->urlGenerator,
			files: $locator
		);

		try {
			$this->assertSame(expected: 'secret', actual: $locator->read(name: 'img/../config/config.svg'));
			$image = $service->getActiveEmailLogoImage();
			if ($logo === 'img/logos/own.svg') {
				$this->assertSame(expected: '<svg/>', actual: $image['content']);
				return;
			}

			$this->assertNull(actual: $image);
		} finally {
			unlink($appPath . '/config/config.svg');
			unlink($appPath . '/img/logos/own.svg');
			rmdir($appPath . '/config');
			rmdir($appPath . '/img/logos');
			rmdir($appPath . '/img');
			@rmdir($appPath . '/store');
			rmdir($appPath);
		}
	}//end testAStoredPathCannotClimbOutOfTheImages()

	/**
	 * Stored paths that climb, and one own logo as the control.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function climbingLogoPaths(): array {
		return [
			'own logo' => ['img/logos/own.svg'],
			'up one' => ['img/../config/config.svg'],
			'deeper first' => ['img/logos/../../config/config.svg'],
			'dot segment in the name' => ['img/logos/..svg/../../../config/config.svg'],
			'encoded' => ['img/%2e%2e/config/config.svg'],
			'backslash' => ['img\\..\\config\\config.svg'],
			'trailing newline' => ["img/logos/own.svg\n"],
			'null byte' => ["img/logos/own.svg\0.png"],
		];
	}//end climbingLogoPaths()

	/**
	 * The stock theme embeds no set's logo, even when a set carries its id.
	 *
	 * @return void
	 */
	public function testTheStockThemeHasNoEmbeddedLogo(): void {
		$locator = $this->createMock(originalClassName: RuntimeFileLocator::class);
		$locator->expects($this->never())->method('read');
		$this->config->method('getAppValue')->willReturn('nextcloud');
		$this->tokenSetService->method('getAvailableTokenSets')->willReturn([['id' => 'nextcloud', 'theming' => ['logo' => 'img/a.svg']]]);
		$service = new EmailThemingService(
			config: $this->config,
			tokenSetService: $this->tokenSetService,
			previewService: $this->previewService,
			urlGenerator: $this->urlGenerator,
			files: $locator
		);

		$this->assertNull(actual: $service->getActiveEmailLogoImage());
	}//end testTheStockThemeHasNoEmbeddedLogo()

	/**
	 * Without a file locator nothing is read.
	 *
	 * @return void
	 */
	public function testNoLocatorMeansNoEmbeddedLogo(): void {
		$this->config->method('getAppValue')->willReturn('custom-a');
		$this->tokenSetService->method('getAvailableTokenSets')->willReturn([['id' => 'custom-a', 'theming' => ['logo' => 'img/logos/custom-a.svg']]]);

		$this->assertNull(actual: $this->service->getActiveEmailLogoImage());
	}//end testNoLocatorMeansNoEmbeddedLogo()

	/**
	 * Enabling with a writable config.php and nothing configured writes the
	 * system value.
	 *
	 * @return void
	 */
	public function testEnableWritesSystemValue(): void {
		$this->config->method('getSystemValue')->willReturn('');
		$this->config->method('getSystemValueBool')->willReturn(false);
		$this->config->expects($this->once())
			->method('setSystemValue')
			->with('mail_template_class', NLDesignEMailTemplate::class);

		$this->service->enable();
	}//end testEnableWritesSystemValue()

	/**
	 * Disabling deletes the system value.
	 *
	 * @return void
	 */
	public function testDisableDeletesSystemValue(): void {
		$this->config->method('getSystemValue')->willReturn(NLDesignEMailTemplate::class);
		$this->config->method('getSystemValueBool')->willReturn(false);
		$this->config->expects($this->once())->method('deleteSystemValue')->with('mail_template_class');

		$this->service->disable();
	}//end testDisableDeletesSystemValue()

	/**
	 * Enabling never clobbers a foreign mail_template_class — it throws instead.
	 *
	 * @return void
	 */
	public function testEnableThrowsOnForeignClass(): void {
		$this->config->method('getSystemValue')->willReturn('OCA\\Enterprise\\Mail\\CustomTemplate');
		$this->config->method('getSystemValueBool')->willReturn(false);
		$this->config->expects($this->never())->method('setSystemValue');

		$this->expectException(exception: ForeignMailTemplateClassException::class);
		$this->service->enable();
	}//end testEnableThrowsOnForeignClass()

	/**
	 * A read-only config.php (via the config_is_read_only flag) throws
	 * before attempting the write.
	 *
	 * @return void
	 */
	public function testEnableThrowsOnReadOnlyFlag(): void {
		$this->config->method('getSystemValue')->willReturn('');
		$this->config->method('getSystemValueBool')->willReturn(true);
		$this->config->expects($this->never())->method('setSystemValue');

		$this->expectException(exception: ConfigReadOnlyException::class);
		$this->service->enable();
	}//end testEnableThrowsOnReadOnlyFlag()

	/**
	 * A filesystem-level read-only config.php the flag missed still throws
	 * the typed exception, via the caught HintException from the write path.
	 *
	 * @return void
	 */
	public function testEnableThrowsOnReadOnlyHintExceptionFromWrite(): void {
		$this->config->method('getSystemValue')->willReturn('');
		$this->config->method('getSystemValueBool')->willReturn(false);
		$this->config->method('setSystemValue')->willThrowException(new HintException('read-only', 'hint'));

		$this->expectException(exception: ConfigReadOnlyException::class);
		$this->service->enable();
	}//end testEnableThrowsOnReadOnlyHintExceptionFromWrite()

	/**
	 * Disabling a foreign class is a no-op — thematiq never touches it.
	 *
	 * @return void
	 */
	public function testDisableLeavesForeignClassUntouched(): void {
		$this->config->method('getSystemValue')->willReturn('OCA\\Enterprise\\Mail\\CustomTemplate');
		$this->config->method('getSystemValueBool')->willReturn(false);
		$this->config->expects($this->never())->method('deleteSystemValue');

		$this->service->disable();
	}//end testDisableLeavesForeignClassUntouched()

	/**
	 * A javascript: scheme URL is rejected before anything is persisted.
	 *
	 * @return void
	 */
	public function testFooterUrlValidationRejectsJavascriptScheme(): void {
		$this->config->expects($this->never())->method('setAppValue');

		$this->expectException(exception: FooterValidationException::class);
		$this->service->setFooterConfig('Org', 'javascript:alert(1)', '');
	}//end testFooterUrlValidationRejectsJavascriptScheme()

	/**
	 * Non-URL junk is rejected.
	 *
	 * @return void
	 */
	public function testFooterUrlValidationRejectsJunk(): void {
		$this->expectException(exception: FooterValidationException::class);
		$this->service->setFooterConfig('Org', 'not a url', '');
	}//end testFooterUrlValidationRejectsJunk()

	/**
	 * Valid http(s) URLs are accepted and persisted.
	 *
	 * @return void
	 */
	public function testFooterUrlValidationAcceptsHttpsAndPersists(): void {
		$this->config->expects($this->exactly(count: 3))->method('setAppValue');
		$this->config->method('getAppValue')->willReturnCallback(
			fn ($app, $key, $default) => match ($key) {
				'email_footer_org_name' => 'Org',
				'email_footer_accessibility_url' => 'https://example.org/a11y',
				'email_footer_privacy_url' => 'https://example.org/privacy',
				default => $default,
			}
		);

		$result = $this->service->setFooterConfig('Org', 'https://example.org/a11y', 'https://example.org/privacy');

		$this->assertSame(expected: 'Org', actual: $result['orgName']);
		$this->assertSame(expected: 'https://example.org/a11y', actual: $result['accessibilityUrl']);
		$this->assertSame(expected: 'https://example.org/privacy', actual: $result['privacyUrl']);
	}//end testFooterUrlValidationAcceptsHttpsAndPersists()

	/**
	 * Empty URL values are always valid (omitted, never validated as a scheme).
	 *
	 * @return void
	 */
	public function testFooterUrlValidationAllowsEmptyValues(): void {
		$this->config->method('getAppValue')->willReturn('');
		$result = $this->service->setFooterConfig('', '', '');
		$this->assertSame(expected: '', actual: $result['accessibilityUrl']);
	}//end testFooterUrlValidationAllowsEmptyValues()

	/**
	 * The active token set's manifest theming metadata is used verbatim
	 * when present, and the logo resolves to an absolute URL.
	 *
	 * @return void
	 */
	public function testGetActiveEmailThemeReturnsManifestTheming(): void {
		$this->config->method('getAppValue')->willReturnCallback(
			fn ($app, $key, $default) => match ($key === 'token_set') {
				true => 'amsterdam', default => $default
			}
		);
		$this->tokenSetService->method('getAvailableTokenSets')->willReturn(
			[
				[
					'id' => 'amsterdam',
					'theming' => ['primary_color' => '#004699', 'logo' => 'img/logos/amsterdam.svg'],
				],
			]
		);
		$this->urlGenerator->method('imagePath')->with('thematiq', 'logos/amsterdam.svg')
			->willReturn('/apps/thematiq/img/logos/amsterdam.svg');
		$this->urlGenerator->method('getAbsoluteURL')->willReturnCallback(
			fn (string $url) => 'https://cloud.example.com' . $url
		);

		$theme = $this->service->getActiveEmailTheme();

		$this->assertNotNull(actual: $theme);
		$this->assertSame(expected: '#004699', actual: $theme['primaryColor']);
		$this->assertSame(expected: '#ffffff', actual: $theme['primaryTextColor']);
		$this->assertSame(expected: 'https://cloud.example.com/apps/thematiq/img/logos/amsterdam.svg', actual: $theme['logoUrl']);
	}//end testGetActiveEmailThemeReturnsManifestTheming()

	/**
	 * A token set without a `theming` block falls back to
	 * TokenSetPreviewService::getResolvedColors().
	 *
	 * @return void
	 */
	public function testGetActiveEmailThemeFallsBackToResolvedColorsWithoutTheming(): void {
		$this->config->method('getAppValue')->willReturnCallback(
			fn ($app, $key, $default) => match ($key === 'token_set') {
				true => 'utrecht-custom', default => $default
			}
		);
		$this->tokenSetService->method('getAvailableTokenSets')->willReturn([['id' => 'utrecht-custom']]);
		$this->previewService->method('getResolvedColors')->willReturn(
			[
				'--color-primary' => '#24578F',
				'--color-primary-text' => '#ffffff',
			]
		);

		$theme = $this->service->getActiveEmailTheme();

		$this->assertNotNull(actual: $theme);
		$this->assertSame(expected: '#24578F', actual: $theme['primaryColor']);
		$this->assertSame(expected: '#ffffff', actual: $theme['primaryTextColor']);
		$this->assertNull(actual: $theme['logoUrl']);
	}//end testGetActiveEmailThemeFallsBackToResolvedColorsWithoutTheming()

	/**
	 * The stock `nextcloud` token set (unthemed) resolves to null.
	 *
	 * @return void
	 */
	public function testGetActiveEmailThemeReturnsNullForNextcloud(): void {
		$this->config->method('getAppValue')->willReturnCallback(
			fn ($app, $key, $default) => match ($key === 'token_set') {
				true => 'nextcloud', default => $default
			}
		);

		$this->assertNull(actual: $this->service->getActiveEmailTheme());
	}//end testGetActiveEmailThemeReturnsNullForNextcloud()
}//end class
