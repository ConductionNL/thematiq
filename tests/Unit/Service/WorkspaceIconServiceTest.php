<?php

/**
 * Tests for the workspace's mobile logo and touch icon: the store, the controller and the page tags.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Controller\WorkspaceIconController;
use OCA\Thematiq\Listener\WorkspaceIconListener;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\Exception\AppBrandException;
use OCA\Thematiq\Service\ImageSniffer;
use OCA\Thematiq\Service\WorkspaceIconService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * Over an in-memory app data folder.
 *
 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
 */
final class WorkspaceIconServiceTest extends TestCase {

	/**
	 * The stored files.
	 *
	 * @var array<string, string>
	 */
	private array $files = [];

	/**
	 * A PNG header of the given size: enough for the type check and for getimagesizefromstring().
	 *
	 * @param int $width The width.
	 * @param int $height The height.
	 *
	 * @return string The bytes.
	 */
	private static function png(int $width, int $height): string {
		$ihdr = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

		return "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
	}//end png()

	/**
	 * The service over the in-memory folder.
	 *
	 * @return WorkspaceIconService
	 */
	private function service(): WorkspaceIconService {
		$folder = $this->createMock(originalClassName: ISimpleFolder::class);
		$folder->method('fileExists')->willReturnCallback(fn (string $name): bool => isset($this->files[$name]));
		$folder->method('newFile')->willReturnCallback(
			function (string $name, $content = null): ISimpleFile {
				$this->files[$name] = (string)$content;

				return $this->createMock(originalClassName: ISimpleFile::class);
			}
		);
		$folder->method('getFile')->willReturnCallback(
			function (string $name): ISimpleFile {
				if (isset($this->files[$name]) === false) {
					throw new NotFoundException($name);
				}

				$file = $this->createMock(originalClassName: ISimpleFile::class);
				$file->method('getContent')->willReturnCallback(fn (): string => $this->files[$name]);
				$file->method('delete')->willReturnCallback(
					function () use ($name): void {
						unset($this->files[$name]);
					}
				);

				return $file;
			}
		);
		$appData = $this->createMock(originalClassName: IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(static fn (string $route, array $args): string => '/apps/thematiq/api/workspace-icons/' . $args['kind']);

		return new WorkspaceIconService(appData: $appData, sniffer: new ImageSniffer(), urls: $urls);
	}//end service()

	/**
	 * The reason a store is refused, or null when it is not.
	 *
	 * @param string $kind The kind.
	 * @param string $bytes The image.
	 *
	 * @return array{0: string, 1: int}|null Reason and status.
	 */
	private function refusal(string $kind, string $bytes): ?array {
		try {
			$this->service()->store(kind: $kind, bytes: $bytes);
		} catch (AppBrandException $e) {
			return [$e->reason, $e->getCode()];
		}

		return null;
	}//end refusal()

	/**
	 * Scenario: both images are stored, read back, listed and removed.
	 *
	 * @return void
	 */
	public function testBothImagesAreStoredAndRemoved(): void {
		$service = $this->service();
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';

		$this->assertSame(expected: ['mobile-logo' => null, 'touch-icon' => null], actual: $service->status());
		$this->assertSame(
			expected: ['kind' => 'mobile-logo', 'mime' => 'image/svg+xml', 'size' => strlen($svg)],
			actual: $service->store(kind: 'mobile-logo', bytes: $svg)
		);
		$this->assertSame(expected: 'image/png', actual: $service->store(kind: 'touch-icon', bytes: self::png(width: 180, height: 180))['mime']);

		$this->assertSame(expected: ['bytes' => $svg, 'mime' => 'image/svg+xml'], actual: $service->read(kind: 'mobile-logo'));
		$status = $service->status();
		$this->assertMatchesRegularExpression(
			pattern: '#^/apps/thematiq/api/workspace-icons/mobile-logo\?v=[0-9a-f]{8}$#',
			string: (string)$status['mobile-logo']
		);
		$this->assertMatchesRegularExpression(
			pattern: '#^/apps/thematiq/api/workspace-icons/touch-icon\?v=[0-9a-f]{8}$#',
			string: (string)$status['touch-icon']
		);

		// A new upload replaces the old one, whatever its type.
		$service->store(kind: 'mobile-logo', bytes: self::png(width: 400, height: 100));
		$this->assertEqualsCanonicalizing(expected: ['mobile-logo.png', 'touch-icon.png'], actual: array_keys($this->files));

		$service->remove(kind: 'mobile-logo');
		$this->assertNull(actual: $service->read(kind: 'mobile-logo'));
		$this->assertNull(actual: $service->read(kind: 'favicon'), message: 'a kind it does not keep reads as nothing');
	}//end testBothImagesAreStoredAndRemoved()

	/**
	 * Scenario: an image the workspace cannot use is refused with its reason.
	 *
	 * @return void
	 */
	public function testAnUnusableImageIsRefused(): void {
		$this->assertSame(
			expected: [AppBrandException::TOO_LARGE, 413],
			actual: $this->refusal(kind: 'mobile-logo', bytes: str_repeat('x', (WorkspaceIconService::MAX_BYTES + 1)))
		);
		$this->assertSame(expected: [AppBrandException::BAD_IMAGE, 422], actual: $this->refusal(kind: 'mobile-logo', bytes: 'GIF89a'));
		$this->assertSame(
			expected: [AppBrandException::BAD_IMAGE, 422],
			actual: $this->refusal(kind: 'mobile-logo', bytes: '<svg><script>alert(1)</script></svg>')
		);
		$this->assertSame(
			expected: [AppBrandException::BAD_IMAGE, 422],
			actual: $this->refusal(kind: 'touch-icon', bytes: "\xFF\xD8\xFF\xE0"),
			message: 'a touch icon is a PNG'
		);
		$this->assertSame(
			expected: [AppBrandException::BAD_IMAGE, 422],
			actual: $this->refusal(kind: 'touch-icon', bytes: self::png(width: 180, height: 120)),
			message: 'not square'
		);
		$this->assertSame(
			expected: [AppBrandException::BAD_IMAGE, 422],
			actual: $this->refusal(kind: 'touch-icon', bytes: self::png(width: 64, height: 64)),
			message: 'too small'
		);
		$this->assertSame(
			expected: [AppBrandException::BAD_IMAGE, 422],
			actual: $this->refusal(kind: 'touch-icon', bytes: "\x89PNG\r\n\x1a\n"),
			message: 'no header to read'
		);
		$this->assertSame(expected: ['unknown_kind', 404], actual: $this->refusal(kind: 'favicon', bytes: self::png(width: 180, height: 180)));
		$this->assertNull(
			actual: $this->refusal(kind: 'touch-icon', bytes: self::png(width: 120, height: 120)),
			message: 'the smallest size is taken'
		);
		$this->assertSame(expected: [], actual: array_diff(array_keys($this->files), ['touch-icon.png']));

		$this->expectException(exception: AppBrandException::class);
		$this->service()->remove(kind: 'favicon');
	}//end testAnUnusableImageIsRefused()

	/**
	 * Scenario: the mobile logo is drawn below 1024 px only, and its address cannot leave the rule.
	 *
	 * @return void
	 */
	public function testTheMobileLogoRuleStaysInsideItsMediaQuery(): void {
		$css = $this->service()->mobileLogoCss(url: '/apps/thematiq/api/workspace-icons/mobile-logo?v=abc12345');

		$this->assertStringStartsWith(
			prefix: '@media (max-width:1024px){#header #nextcloud .logo{background-image:url(/apps/thematiq/api/workspace-icons/mobile-logo?v=abc12345) !important;',
			string: $css
		);
		$this->assertStringEndsWith(suffix: '}}', string: $css);
		$this->assertStringNotContainsString(
			needle: '</style',
			haystack: $this->service()->mobileLogoCss(url: '/x);} body{display:none}</style><script>')
		);
		$this->assertStringContainsString(
			needle: 'url(/xbodydisplaynone/stylescript)',
			haystack: $this->service()->mobileLogoCss(url: '/x);} body{display:none}</style><script>')
		);
	}//end testTheMobileLogoRuleStaysInsideItsMediaQuery()

	/**
	 * The controller over the service.
	 *
	 * @param array<string, mixed>|null $upload What getUploadedFile('file') returns.
	 *
	 * @return WorkspaceIconController
	 */
	private function controller(?array $upload = null): WorkspaceIconController {
		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getUploadedFile')->willReturn($upload);
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return new WorkspaceIconController(appName: 'thematiq', request: $request, icons: $this->service(), l10n: $l10n);
	}//end controller()

	/**
	 * A temporary file holding the given bytes, as an upload.
	 *
	 * @param string $bytes The bytes.
	 *
	 * @return array{tmp_name: string, error: int}
	 */
	private function upload(string $bytes): array {
		$path = (string)tempnam(sys_get_temp_dir(), 'thematiq-icon-');
		file_put_contents($path, $bytes);

		return ['tmp_name' => $path, 'error' => 0];
	}//end upload()

	/**
	 * Scenario: the administrator uploads, lists and removes; anyone reads the image.
	 *
	 * @return void
	 */
	public function testTheControllerUploadsServesAndRemoves(): void {
		$this->assertSame(expected: 400, actual: $this->controller()->upload(kind: 'touch-icon')->getStatus());
		$this->assertSame(
			expected: 400,
			actual: $this->controller(upload: ['tmp_name' => '/nowhere', 'error' => 4])->upload(kind: 'touch-icon')->getStatus()
		);

		$refused = $this->controller(upload: $this->upload(bytes: self::png(width: 100, height: 50)))->upload(kind: 'touch-icon');
		$this->assertSame(expected: 422, actual: $refused->getStatus());
		$this->assertSame(expected: 'Upload a square PNG of at least 120 by 120 pixels; 180 by 180 is best.', actual: $refused->getData()['error']);
		$this->assertSame(
			expected: 'Upload a PNG, JPEG or WebP image, or an SVG without script.',
			actual: $this->controller(upload: $this->upload(bytes: 'nope'))->upload(kind: 'mobile-logo')->getData()['error']
		);
		$this->assertSame(
			expected: 404,
			actual: $this->controller(upload: $this->upload(bytes: self::png(width: 180, height: 180)))->upload(kind: 'favicon')->getStatus()
		);

		$stored = $this->controller(upload: $this->upload(bytes: self::png(width: 180, height: 180)))->upload(kind: 'touch-icon');
		$this->assertSame(expected: 200, actual: $stored->getStatus());
		$this->assertNotNull(actual: $this->controller()->index()->getData()['touch-icon']);

		$shown = $this->controller()->show(kind: 'touch-icon');
		$this->assertInstanceOf(expected: DataDisplayResponse::class, actual: $shown);
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($shown);
		$this->assertSame(expected: 'image/png', actual: $headers['Content-Type']);
		$this->assertSame(expected: 'nosniff', actual: $headers['X-Content-Type-Options']);
		$this->assertSame(expected: 404, actual: $this->controller()->show(kind: 'mobile-logo')->getStatus());

		$this->assertSame(expected: ['removed' => 'touch-icon'], actual: $this->controller()->remove(kind: 'touch-icon')->getData());
		$this->assertSame(expected: 404, actual: $this->controller()->remove(kind: 'favicon')->getStatus());
		$this->assertSame(expected: 404, actual: $this->controller()->show(kind: 'touch-icon')->getStatus());
	}//end testTheControllerUploadsServesAndRemoves()

	/**
	 * Scenario: only an administrator changes the images; the image itself is public.
	 *
	 * @return void
	 */
	public function testWhoMayDoWhat(): void {
		foreach (['index', 'upload', 'remove'] as $method) {
			$attributes = (new ReflectionMethod(WorkspaceIconController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes, message: $method);
			$this->assertSame(expected: Admin::class, actual: $attributes[0]->getArguments()['settings']);
		}

		$show = new ReflectionMethod(WorkspaceIconController::class, 'show');
		$this->assertCount(expectedCount: 1, haystack: $show->getAttributes(PublicPage::class));
		$this->assertSame(expected: ['limit' => 480, 'period' => 60], actual: $show->getAttributes(AnonRateLimit::class)[0]->getArguments());
	}//end testWhoMayDoWhat()

	/**
	 * The listener, with the tags it emits captured.
	 *
	 * @param WorkspaceIconService $service The service.
	 * @param array<int, array<string, mixed>> $tags Filled with the emitted tags.
	 * @param LoggerInterface|null $logger The logger.
	 * @param array<string, mixed> $setMeta The active set's manifest entry.
	 * @param RuntimeException|null $metaFails Thrown by the manifest lookup instead, when given.
	 *
	 * @return WorkspaceIconListener
	 */
	private function listener(WorkspaceIconService $service, array &$tags, ?LoggerInterface $logger = null, array $setMeta = [], ?RuntimeException $metaFails = null): WorkspaceIconListener {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('getAppValue')->willReturn('zuiddrecht');
		$designSystems = $this->createMock(originalClassName: DesignSystemService::class);
		$designSystems->method('getTokenSetMeta')->willReturn($setMeta);
		if ($metaFails !== null) {
			$designSystems = $this->createMock(originalClassName: DesignSystemService::class);
			$designSystems->method('getTokenSetMeta')->willThrowException($metaFails);
		}
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('imagePath')->willReturnCallback(
			fn (string $appName, string $file): string => '/custom_apps/' . $appName . '/img/' . $file
		);
		$logger = ($logger ?? $this->createMock(originalClassName: LoggerInterface::class));

		return new class($service, $logger, $tags, $config, $designSystems, $urls) extends WorkspaceIconListener {
			/**
			 * The captured tags.
			 *
			 * @var array<int, array<string, mixed>>
			 */
			private array $captured;

			/**
			 * Constructor.
			 *
			 * @param WorkspaceIconService $icons The images.
			 * @param LoggerInterface $logger The logger.
			 * @param array<int, array<string, mixed>> $tags Filled with the tags.
			 * @param IConfig $config The config.
			 * @param DesignSystemService $designSystems The set manifest.
			 * @param IURLGenerator $urls The URL generator.
			 */
			public function __construct(
				WorkspaceIconService $icons,
				LoggerInterface $logger,
				array &$tags,
				IConfig $config,
				DesignSystemService $designSystems,
				IURLGenerator $urls,
			) {
				parent::__construct(icons: $icons, logger: $logger, config: $config, designSystems: $designSystems, urls: $urls);
				$this->captured = &$tags;
			}//end __construct()

			/**
			 * Capture a tag.
			 *
			 * @param string $tag The tag.
			 * @param array<string, string> $attributes Its attributes.
			 * @param string $text Its content.
			 *
			 * @return void
			 */
			protected function emit(string $tag, array $attributes, string $text): void {
				$this->captured[] = ['tag' => $tag, 'attributes' => $attributes, 'text' => $text];
			}//end emit()
		};
	}//end listener()

	/**
	 * Scenario: every page and the login page get a tag per image that is set, and nothing else.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
	 */
	public function testTheListenerAddsATagPerImage(): void {
		$service = $this->service();
		$tags = [];
		$page = $this->createMock(originalClassName: BeforeTemplateRenderedEvent::class);
		$login = $this->createMock(originalClassName: BeforeLoginTemplateRenderedEvent::class);

		$this->listener(service: $service, tags: $tags)->handle(event: $page);
		$this->assertSame(expected: [], actual: $tags, message: 'nothing is set, nothing is added');

		$service->store(kind: 'touch-icon', bytes: self::png(width: 180, height: 180));
		$service->store(kind: 'mobile-logo', bytes: self::png(width: 300, height: 80));
		$this->listener(service: $service, tags: $tags)->handle(event: $login);

		$this->assertCount(expectedCount: 2, haystack: $tags);
		$this->assertSame(expected: 'link', actual: $tags[0]['tag']);
		$this->assertSame(expected: ['rel' => 'apple-touch-icon', 'sizes' => '180x180'], actual: array_slice($tags[0]['attributes'], 0, 2));
		$this->assertStringStartsWith(prefix: '/apps/thematiq/api/workspace-icons/touch-icon?v=', string: $tags[0]['attributes']['href']);
		$this->assertSame(expected: 'style', actual: $tags[1]['tag']);
		$this->assertSame(expected: ['id' => WorkspaceIconListener::STYLE_ID], actual: $tags[1]['attributes']);
		$this->assertStringStartsWith(prefix: '@media (max-width:1024px)', string: $tags[1]['text']);

		$tags = [];
		$this->listener(service: $service, tags: $tags)->handle(event: new Event());
		$this->assertSame(expected: [], actual: $tags, message: 'another event is ignored');
	}//end testTheListenerAddsATagPerImage()

	/**
	 * Scenario: an image that cannot be read never breaks a page.
	 *
	 * @return void
	 */
	public function testTheListenerFailsOpen(): void {
		$appData = $this->createMock(originalClassName: IAppData::class);
		$appData->method('getFolder')->willThrowException(new RuntimeException('storage down'));
		$service = new WorkspaceIconService(
			appData: $appData,
			sniffer: new ImageSniffer(),
			urls: $this->createMock(originalClassName: IURLGenerator::class)
		);
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning');
		$tags = [];

		$this->listener(
			service: $service,
			tags: $tags,
			logger: $logger
		)->handle(event: $this->createMock(originalClassName: BeforeTemplateRenderedEvent::class));

		$this->assertSame(expected: [], actual: $tags);
	}//end testTheListenerFailsOpen()

	/**
	 * Scenario: a set that declares an SVG favicon gets a `<link rel="icon">` to it on every page.
	 *
	 * @return void
	 */
	public function testTheListenerLinksTheSetFavicon(): void {
		$tags = [];
		$setMeta = ['theming' => ['favicon' => 'img/logos/zuiddrecht-emblem.svg']];

		$event = $this->createMock(originalClassName: BeforeLoginTemplateRenderedEvent::class);
		$this->listener(service: $this->service(), tags: $tags, setMeta: $setMeta)->handle(event: $event);

		$href = '/custom_apps/thematiq/img/logos/zuiddrecht-emblem.svg';
		$this->assertSame(
			expected: [['tag' => 'link', 'attributes' => ['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => $href], 'text' => '']],
			actual: $tags
		);
	}//end testTheListenerLinksTheSetFavicon()

	/**
	 * Scenario: no favicon, a raster one or a path outside img/logos/ adds no icon link.
	 *
	 * @return void
	 */
	public function testTheListenerSkipsAnUnusableSetFavicon(): void {
		$unusable = ['img/logos/x.png', 'img/logos/../../x.svg', 'img/backgrounds/x.svg'];
		$cases = array_merge([[]], array_map(fn (string $path): array => ['theming' => ['favicon' => $path]], $unusable));
		foreach ($cases as $setMeta) {
			$tags = [];
			$event = $this->createMock(originalClassName: BeforeTemplateRenderedEvent::class);
			$this->listener(service: $this->service(), tags: $tags, setMeta: $setMeta)->handle(event: $event);
			$this->assertSame(expected: [], actual: $tags, message: (string)json_encode($setMeta));
		}
	}//end testTheListenerSkipsAnUnusableSetFavicon()
	/**
	 * Scenario: the set favicon comes first, survives unreadable images, and an unreadable set adds only the images.
	 *
	 * @return void
	 */
	public function testTheListenerOrderAndItsFailures(): void {
		$setMeta = ['theming' => ['favicon' => 'img/logos/zuiddrecht-emblem.svg']];
		$service = $this->service();
		$service->store(kind: 'touch-icon', bytes: self::png(width: 180, height: 180));
		$service->store(kind: 'mobile-logo', bytes: self::png(width: 300, height: 80));
		$page = $this->createMock(originalClassName: BeforeTemplateRenderedEvent::class);

		$tags = [];
		$this->listener(service: $service, tags: $tags, setMeta: $setMeta)->handle(event: $page);
		$this->assertSame(expected: [['link', 'icon'], ['link', 'apple-touch-icon'], ['style', null]], actual: array_map(static fn (array $t): array => [$t['tag'], ($t['attributes']['rel'] ?? null)], $tags));

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with('Thematiq: the set favicon could not be read.', $this->arrayHasKey('exception'));
		$tags = [];
		$this->listener(service: $service, tags: $tags, logger: $logger, metaFails: new RuntimeException('manifest down'))->handle(event: $page);
		$this->assertSame(expected: ['link', 'style'], actual: array_column($tags, 'tag'));

		$appData = $this->createMock(originalClassName: IAppData::class);
		$appData->method('getFolder')->willThrowException(new RuntimeException('storage down'));
		$broken = new WorkspaceIconService(appData: $appData, sniffer: new ImageSniffer(), urls: $this->createMock(originalClassName: IURLGenerator::class));
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with('Thematiq: the workspace icons could not be read.', $this->arrayHasKey('exception'));
		$tags = [];
		$this->listener(service: $broken, tags: $tags, logger: $logger, setMeta: $setMeta)->handle(event: $page);
		$this->assertSame(expected: [['link', 'icon']], actual: array_map(static fn (array $t): array => [$t['tag'], ($t['attributes']['rel'] ?? null)], $tags));

		foreach ([['theming' => ['favicon' => 7]], ['theming' => ['favicon' => 'img/logos/x.SVG']], ['theming' => 'x']] as $odd) {
			$tags = [];
			$this->listener(service: $service, tags: $tags, setMeta: $odd)->handle(event: $page);
			$this->assertSame(expected: ['apple-touch-icon', null], actual: array_map(static fn (array $t): ?string => ($t['attributes']['rel'] ?? null), $tags), message: (string)json_encode($odd));
		}
	}//end testTheListenerOrderAndItsFailures()
}//end class
