<?php

/**
 * The workspace's mobile logo and home-screen touch icon.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Service
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\Exception\AppBrandException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IURLGenerator;

/**
 * Two images an administrator may upload for the whole workspace, apart from the logo and the
 * favicon Nextcloud already takes:
 *
 * - `mobile-logo`: drawn in the header below 1024 px, the width at which Nextcloud's header goes
 *   narrow. PNG, JPEG, WebP or an SVG without script.
 * - `touch-icon`: the icon a phone shows when the workspace is added to its home screen. A square
 *   PNG of at least 120 px, because iOS reads only PNG. Nextcloud otherwise uses its favicon.
 *
 * Both live in app data `workspace-icons/<kind>.<ext>`, at most 1 MB, the type read from the
 * first bytes.
 *
 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
 */
class WorkspaceIconService {

	/**
	 * The app data folder.
	 *
	 * @var string
	 */
	public const FOLDER = 'workspace-icons';

	/**
	 * The kinds and the types each takes.
	 *
	 * @var array<string, array<int, string>>
	 */
	public const KINDS = [
		'mobile-logo' => ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
		'touch-icon' => ['image/png'],
	];

	/**
	 * The largest image.
	 *
	 * @var int
	 */
	public const MAX_BYTES = (1024 * 1024);

	/**
	 * The smallest side of a touch icon.
	 *
	 * @var int
	 */
	public const MIN_TOUCH_ICON = 120;

	/**
	 * The header logo on narrow screens. Two ids and a class: it outranks every design system's
	 * own rule for the header logo and Nextcloud's, and yields to a brand per app, whose rule
	 * starts with `body`.
	 *
	 * @var string
	 */
	private const HEADER_LOGO_SELECTOR = '#header #nextcloud .logo';

	/**
	 * The extension of each type.
	 *
	 * @var array<string, string>
	 */
	private const EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

	/**
	 * Cache key of the stored images' version stamps.
	 *
	 * @var string
	 */
	private const STAMPS_KEY = 'stamps';

	/**
	 * How long the version stamps are kept, in seconds. store() and remove() empty them at once;
	 * the lifetime only bounds a change made outside this service (another tool writing app data).
	 *
	 * @var int
	 */
	private const STAMPS_TTL = 600;

	/**
	 * The stamps cache, created on first use; null when no cache factory was given.
	 *
	 * @var ICache|null
	 */
	private ?ICache $stampCache = null;

	/**
	 * Constructor.
	 *
	 * @param IAppData $appData The app's data store.
	 * @param ImageSniffer $sniffer The image checks.
	 * @param IURLGenerator $urls Builds the image's address.
	 * @param ICacheFactory|null $caches Keeps the images' version stamps between page renders; null reads the files every time.
	 */
	public function __construct(
		private readonly IAppData $appData,
		private readonly ImageSniffer $sniffer,
		private readonly IURLGenerator $urls,
		private readonly ?ICacheFactory $caches = null,
	) {
	}//end __construct()

	/**
	 * Validate and store an image, replacing the previous one.
	 *
	 * @param string $kind `mobile-logo` or `touch-icon`.
	 * @param string $bytes The image.
	 *
	 * @return array{kind: string, mime: string, size: int} What was stored.
	 *
	 * @throws AppBrandException 404 for an unknown kind, 413 when too large, 422 when not an accepted image.
	 *
	 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
	 */
	public function store(string $kind, string $bytes): array {
		$this->known(kind: $kind);
		if (strlen($bytes) > self::MAX_BYTES) {
			throw new AppBrandException(reason: AppBrandException::TOO_LARGE, status: 413);
		}

		$mime = $this->sniffer->type(bytes: $bytes);
		if ($mime === null || in_array($mime, self::KINDS[$kind], true) === false
			|| ($mime === 'image/svg+xml' && $this->sniffer->isSafeSvg(svg: $bytes) === false)
			|| ($kind === 'touch-icon' && $this->isSquareEnough(bytes: $bytes) === false)
		) {
			throw new AppBrandException(reason: AppBrandException::BAD_IMAGE);
		}

		$this->remove(kind: $kind);
		$this->folder()->newFile($kind . '.' . self::EXTENSIONS[$mime], $bytes);
		$this->stamps()?->remove(self::STAMPS_KEY);

		return ['kind' => $kind, 'mime' => $mime, 'size' => strlen($bytes)];
	}//end store()

	/**
	 * Read an image.
	 *
	 * @param string $kind `mobile-logo` or `touch-icon`.
	 *
	 * @return array{bytes: string, mime: string}|null The image, or null when none is stored.
	 *
	 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
	 */
	public function read(string $kind): ?array {
		if (isset(self::KINDS[$kind]) === false) {
			return null;
		}

		$folder = $this->folder();
		foreach (self::KINDS[$kind] as $mime) {
			try {
				return ['bytes' => $folder->getFile($kind . '.' . self::EXTENSIONS[$mime])->getContent(), 'mime' => $mime];
			} catch (NotFoundException $e) {
				continue;
			}
		}

		return null;
	}//end read()

	/**
	 * Remove an image whatever its type.
	 *
	 * @param string $kind `mobile-logo` or `touch-icon`.
	 *
	 * @return void
	 *
	 * @throws AppBrandException 404 for an unknown kind.
	 *
	 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
	 */
	public function remove(string $kind): void {
		$this->known(kind: $kind);
		$folder = $this->folder();
		foreach (self::EXTENSIONS as $extension) {
			if ($folder->fileExists($kind . '.' . $extension) === true) {
				$folder->getFile($kind . '.' . $extension)->delete();
			}
		}

		$this->stamps()?->remove(self::STAMPS_KEY);
	}//end remove()

	/**
	 * Which images are set, by kind, with the address each is served at.
	 *
	 * @return array<string, string|null> Kind to address, or null when not set.
	 *
	 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-an-administrator-sets-a-mobile-logo-and-a-touch-icon
	 */
	public function status(): array {
		$out = [];
		foreach ($this->versionStamps() as $kind => $stamp) {
			$out[$kind] = null;
			if ($stamp !== '') {
				$out[$kind] = $this->urls->linkToRoute('thematiq.workspaceIcon.show', ['kind' => $kind]) . '?v=' . $stamp;
			}
		}

		return $out;
	}//end status()

	/**
	 * The version stamp of each kind's stored image, '' when none is stored.
	 *
	 * Every page render asks (WorkspaceIconListener), so the stamps are kept in the
	 * distributed cache instead of opening and hashing the app data files each time.
	 *
	 * @return array<string, string> Kind to stamp.
	 *
	 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
	 */
	private function versionStamps(): array {
		$kept = $this->stamps()?->get(self::STAMPS_KEY);
		if (is_array($kept) === true && array_keys($kept) === array_keys(self::KINDS)) {
			return $kept;
		}

		$stamps = [];
		foreach (array_keys(self::KINDS) as $kind) {
			$image = $this->read(kind: $kind);
			$stamps[$kind] = '';
			if ($image !== null) {
				$stamps[$kind] = substr(hash('sha256', $image['bytes']), 0, 8);
			}
		}

		$this->stamps()?->set(self::STAMPS_KEY, $stamps, self::STAMPS_TTL);
		return $stamps;
	}//end versionStamps()

	/**
	 * The distributed cache for the version stamps, or null without a cache factory.
	 *
	 * @return ICache|null The cache.
	 */
	private function stamps(): ?ICache {
		if ($this->stampCache === null && $this->caches !== null) {
			$this->stampCache = $this->caches->createDistributed('thematiq-workspace-icons');
		}

		return $this->stampCache;
	}//end stamps()

	/**
	 * The stylesheet that draws the mobile logo below 1024 px.
	 *
	 * @param string $url The mobile logo's address.
	 *
	 * @return string The stylesheet.
	 *
	 * @spec openspec/specs/mobile-logo-and-touch-icon/spec.md#requirement-the-mobile-logo-and-the-touch-icon-reach-every-page
	 */
	public function mobileLogoCss(string $url): string {
		$url = (string)preg_replace('/[^A-Za-z0-9\/._?=&%-]/', '', $url);

		return '@media (max-width:1024px){' . self::HEADER_LOGO_SELECTOR . '{'
			. 'background-image:url(' . $url . ') !important;'
			. 'background-color:transparent !important;'
			. '-webkit-mask:none !important;'
			. 'mask:none !important;'
			. 'filter:none !important}}';
	}//end mobileLogoCss()

	/**
	 * Refuse a kind this class does not keep.
	 *
	 * @param string $kind The kind.
	 *
	 * @return void
	 *
	 * @throws AppBrandException 404.
	 */
	private function known(string $kind): void {
		if (isset(self::KINDS[$kind]) === false) {
			throw new AppBrandException(reason: 'unknown_kind', status: 404);
		}
	}//end known()

	/**
	 * Whether a PNG is square and large enough for a home screen.
	 *
	 * @param string $bytes The image.
	 *
	 * @return bool True when it is.
	 */
	private function isSquareEnough(string $bytes): bool {
		// The signature and the IHDR chunk hold the size; anything shorter has none to read.
		if (strlen($bytes) < 24) {
			return false;
		}

		$size = getimagesizefromstring($bytes);
		if ($size === false) {
			return false;
		}

		return ($size[0] === $size[1] && $size[0] >= self::MIN_TOUCH_ICON);
	}//end isSquareEnough()

	/**
	 * The app data folder, created on first use.
	 *
	 * @return ISimpleFolder The folder.
	 */
	private function folder(): ISimpleFolder {
		return (new AppDataFolder())->open(appData: $this->appData, name: self::FOLDER);
	}//end folder()
}//end class
