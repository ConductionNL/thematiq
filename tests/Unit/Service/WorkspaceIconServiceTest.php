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
 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Controller\WorkspaceIconController;
use OCA\Thematiq\Listener\WorkspaceIconListener;
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
 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
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
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('fileExists')->willReturnCallback(fn (string $name): bool => isset($this->files[$name]));
		$folder->method('newFile')->willReturnCallback(
			function (string $name, $content = null): ISimpleFile {
				$this->files[$name] = (string)$content;

				return $this->createMock(ISimpleFile::class);
			}
		);
		$folder->method('getFile')->willReturnCallback(
			function (string $name): ISimpleFile {
				if (isset($this->files[$name]) === false) {
					throw new NotFoundException($name);
				}

				$file = $this->createMock(ISimpleFile::class);
				$file->method('getContent')->willReturnCallback(fn (): string => $this->files[$name]);
				$file->method('delete')->willReturnCallback(
					function () use ($name): void {
						unset($this->files[$name]);
					}
				);

				return $file;
			}
		);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(static fn (string $route, array $args): string => '/apps/thematiq/api/workspace-icons/' . $args['kind']);

		return new WorkspaceIconService($appData, new ImageSniffer(), $urls);
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

		$this->assertSame(['mobile-logo' => null, 'touch-icon' => null], $service->status());
		$this->assertSame(['kind' => 'mobile-logo', 'mime' => 'image/svg+xml', 'size' => strlen($svg)], $service->store(kind: 'mobile-logo', bytes: $svg));
		$this->assertSame('image/png', $service->store(kind: 'touch-icon', bytes: self::png(180, 180))['mime']);

		$this->assertSame(['bytes' => $svg, 'mime' => 'image/svg+xml'], $service->read(kind: 'mobile-logo'));
		$status = $service->status();
		$this->assertMatchesRegularExpression('#^/apps/thematiq/api/workspace-icons/mobile-logo\?v=[0-9a-f]{8}$#', (string)$status['mobile-logo']);
		$this->assertMatchesRegularExpression('#^/apps/thematiq/api/workspace-icons/touch-icon\?v=[0-9a-f]{8}$#', (string)$status['touch-icon']);

		// A new upload replaces the old one, whatever its type.
		$service->store(kind: 'mobile-logo', bytes: self::png(400, 100));
		$this->assertEqualsCanonicalizing(['mobile-logo.png', 'touch-icon.png'], array_keys($this->files));

		$service->remove(kind: 'mobile-logo');
		$this->assertNull($service->read(kind: 'mobile-logo'));
		$this->assertNull($service->read(kind: 'favicon'), 'a kind it does not keep reads as nothing');
	}//end testBothImagesAreStoredAndRemoved()

	/**
	 * Scenario: an image the workspace cannot use is refused with its reason.
	 *
	 * @return void
	 */
	public function testAnUnusableImageIsRefused(): void {
		$this->assertSame([AppBrandException::TOO_LARGE, 413], $this->refusal(kind: 'mobile-logo', bytes: str_repeat('x', (WorkspaceIconService::MAX_BYTES + 1))));
		$this->assertSame([AppBrandException::BAD_IMAGE, 422], $this->refusal(kind: 'mobile-logo', bytes: 'GIF89a'));
		$this->assertSame([AppBrandException::BAD_IMAGE, 422], $this->refusal(kind: 'mobile-logo', bytes: '<svg><script>alert(1)</script></svg>'));
		$this->assertSame([AppBrandException::BAD_IMAGE, 422], $this->refusal(kind: 'touch-icon', bytes: "\xFF\xD8\xFF\xE0"), 'a touch icon is a PNG');
		$this->assertSame([AppBrandException::BAD_IMAGE, 422], $this->refusal(kind: 'touch-icon', bytes: self::png(180, 120)), 'not square');
		$this->assertSame([AppBrandException::BAD_IMAGE, 422], $this->refusal(kind: 'touch-icon', bytes: self::png(64, 64)), 'too small');
		$this->assertSame([AppBrandException::BAD_IMAGE, 422], $this->refusal(kind: 'touch-icon', bytes: "\x89PNG\r\n\x1a\n"), 'no header to read');
		$this->assertSame(['unknown_kind', 404], $this->refusal(kind: 'favicon', bytes: self::png(180, 180)));
		$this->assertNull($this->refusal(kind: 'touch-icon', bytes: self::png(120, 120)), 'the smallest size is taken');
		$this->assertSame([], array_diff(array_keys($this->files), ['touch-icon.png']));

		$this->expectException(AppBrandException::class);
		$this->service()->remove(kind: 'favicon');
	}//end testAnUnusableImageIsRefused()

	/**
	 * Scenario: the mobile logo is drawn below 1024 px only, and its address cannot leave the rule.
	 *
	 * @return void
	 */
	public function testTheMobileLogoRuleStaysInsideItsMediaQuery(): void {
		$css = $this->service()->mobileLogoCss(url: '/apps/thematiq/api/workspace-icons/mobile-logo?v=abc12345');

		$this->assertStringStartsWith('@media (max-width:1024px){#header #nextcloud .logo{background-image:url(/apps/thematiq/api/workspace-icons/mobile-logo?v=abc12345) !important;', $css);
		$this->assertStringEndsWith('}}', $css);
		$this->assertStringNotContainsString('</style', $this->service()->mobileLogoCss(url: '/x);} body{display:none}</style><script>'));
		$this->assertStringContainsString('url(/xbodydisplaynone/stylescript)', $this->service()->mobileLogoCss(url: '/x);} body{display:none}</style><script>'));
	}//end testTheMobileLogoRuleStaysInsideItsMediaQuery()

	/**
	 * The controller over the service.
	 *
	 * @param array<string, mixed>|null $upload What getUploadedFile('file') returns.
	 *
	 * @return WorkspaceIconController
	 */
	private function controller(?array $upload = null): WorkspaceIconController {
		$request = $this->createMock(IRequest::class);
		$request->method('getUploadedFile')->willReturn($upload);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		return new WorkspaceIconController('thematiq', $request, $this->service(), $l10n);
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
		$this->assertSame(400, $this->controller()->upload(kind: 'touch-icon')->getStatus());
		$this->assertSame(400, $this->controller(upload: ['tmp_name' => '/nowhere', 'error' => 4])->upload(kind: 'touch-icon')->getStatus());

		$refused = $this->controller(upload: $this->upload(bytes: self::png(100, 50)))->upload(kind: 'touch-icon');
		$this->assertSame(422, $refused->getStatus());
		$this->assertSame('Upload a square PNG of at least 120 by 120 pixels; 180 by 180 is best.', $refused->getData()['error']);
		$this->assertSame('Upload a PNG, JPEG or WebP image, or an SVG without script.', $this->controller(upload: $this->upload(bytes: 'nope'))->upload(kind: 'mobile-logo')->getData()['error']);
		$this->assertSame(404, $this->controller(upload: $this->upload(bytes: self::png(180, 180)))->upload(kind: 'favicon')->getStatus());

		$stored = $this->controller(upload: $this->upload(bytes: self::png(180, 180)))->upload(kind: 'touch-icon');
		$this->assertSame(200, $stored->getStatus());
		$this->assertNotNull($this->controller()->index()->getData()['touch-icon']);

		$shown = $this->controller()->show(kind: 'touch-icon');
		$this->assertInstanceOf(DataDisplayResponse::class, $shown);
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($shown);
		$this->assertSame('image/png', $headers['Content-Type']);
		$this->assertSame('nosniff', $headers['X-Content-Type-Options']);
		$this->assertSame(404, $this->controller()->show(kind: 'mobile-logo')->getStatus());

		$this->assertSame(['removed' => 'touch-icon'], $this->controller()->remove(kind: 'touch-icon')->getData());
		$this->assertSame(404, $this->controller()->remove(kind: 'favicon')->getStatus());
		$this->assertSame(404, $this->controller()->show(kind: 'touch-icon')->getStatus());
	}//end testTheControllerUploadsServesAndRemoves()

	/**
	 * Scenario: only an administrator changes the images; the image itself is public.
	 *
	 * @return void
	 */
	public function testWhoMayDoWhat(): void {
		foreach (['index', 'upload', 'remove'] as $method) {
			$attributes = (new ReflectionMethod(WorkspaceIconController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes, $method);
			$this->assertSame(Admin::class, $attributes[0]->getArguments()['settings']);
		}

		$show = new ReflectionMethod(WorkspaceIconController::class, 'show');
		$this->assertCount(1, $show->getAttributes(PublicPage::class));
		$this->assertSame(['limit' => 480, 'period' => 60], $show->getAttributes(AnonRateLimit::class)[0]->getArguments());
	}//end testWhoMayDoWhat()

	/**
	 * The listener, with the tags it emits captured.
	 *
	 * @param WorkspaceIconService $service The service.
	 * @param array<int, array<string, mixed>> $tags Filled with the emitted tags.
	 * @param LoggerInterface|null $logger The logger.
	 *
	 * @return WorkspaceIconListener
	 */
	private function listener(WorkspaceIconService $service, array &$tags, ?LoggerInterface $logger = null): WorkspaceIconListener {
		return new class($service, ($logger ?? $this->createMock(LoggerInterface::class)), $tags) extends WorkspaceIconListener {
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
			 */
			public function __construct(WorkspaceIconService $icons, LoggerInterface $logger, array &$tags) {
				parent::__construct(icons: $icons, logger: $logger);
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
	 * @spec openspec/changes/mobile-logo-and-touch-icon/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
	 */
	public function testTheListenerAddsATagPerImage(): void {
		$service = $this->service();
		$tags = [];
		$page = $this->createMock(BeforeTemplateRenderedEvent::class);
		$login = $this->createMock(BeforeLoginTemplateRenderedEvent::class);

		$this->listener(service: $service, tags: $tags)->handle(event: $page);
		$this->assertSame([], $tags, 'nothing is set, nothing is added');

		$service->store(kind: 'touch-icon', bytes: self::png(180, 180));
		$service->store(kind: 'mobile-logo', bytes: self::png(300, 80));
		$this->listener(service: $service, tags: $tags)->handle(event: $login);

		$this->assertCount(2, $tags);
		$this->assertSame('link', $tags[0]['tag']);
		$this->assertSame(['rel' => 'apple-touch-icon', 'sizes' => '180x180'], array_slice($tags[0]['attributes'], 0, 2));
		$this->assertStringStartsWith('/apps/thematiq/api/workspace-icons/touch-icon?v=', $tags[0]['attributes']['href']);
		$this->assertSame('style', $tags[1]['tag']);
		$this->assertSame(['id' => WorkspaceIconListener::STYLE_ID], $tags[1]['attributes']);
		$this->assertStringStartsWith('@media (max-width:1024px)', $tags[1]['text']);

		$tags = [];
		$this->listener(service: $service, tags: $tags)->handle(event: new Event());
		$this->assertSame([], $tags, 'another event is ignored');
	}//end testTheListenerAddsATagPerImage()

	/**
	 * Scenario: an image that cannot be read never breaks a page.
	 *
	 * @return void
	 */
	public function testTheListenerFailsOpen(): void {
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willThrowException(new RuntimeException('storage down'));
		$service = new WorkspaceIconService($appData, new ImageSniffer(), $this->createMock(IURLGenerator::class));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning');
		$tags = [];

		$this->listener(service: $service, tags: $tags, logger: $logger)->handle(event: $this->createMock(BeforeTemplateRenderedEvent::class));

		$this->assertSame([], $tags);
	}//end testTheListenerFailsOpen()
}//end class
