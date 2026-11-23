<?php

/**
 * Thematiq apps menu: the icons uploaded for external apps.
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
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;

/**
 * An uploaded icon is an SVG without script or a PNG of at most 100 kB, kept in the app data under
 * the hash of its bytes, so the same icon is stored once and its id names it for good.
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
 */
class AppMenuIcons {

	/**
	 * The app data folder.
	 *
	 * @var string
	 */
	public const FOLDER = 'menu-icons';

	/**
	 * The most bytes of an icon.
	 *
	 * @var int
	 */
	public const MAX_BYTES = 102400;

	/**
	 * The shape of an icon id.
	 *
	 * @var string
	 */
	public const ID_PATTERN = '/^[a-f0-9]{32}$/';

	/**
	 * The accepted types and their extensions.
	 *
	 * @var array<string, string>
	 */
	private const TYPES = ['image/png' => 'png', 'image/svg+xml' => 'svg'];

	/**
	 * Constructor.
	 *
	 * @param IAppData $appData The app's data store.
	 * @param ImageSniffer $sniffer The image checks.
	 */
	public function __construct(
		private readonly IAppData $appData,
		private readonly ImageSniffer $sniffer,
	) {
	}//end __construct()

	/**
	 * Check and keep an icon.
	 *
	 * @param string $bytes The file.
	 *
	 * @return string|null The icon's id, or null when it is not a PNG or a safe SVG of at most 100 kB.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	public function store(string $bytes): ?string {
		$mime = $this->sniffer->type(bytes: $bytes);
		if (strlen($bytes) > self::MAX_BYTES || $mime === null || isset(self::TYPES[$mime]) === false
			|| ($mime === 'image/svg+xml' && $this->sniffer->isSafeSvg(svg: $bytes) === false)
		) {
			return null;
		}

		$id = substr(hash('sha256', $bytes), 0, 32);
		$folder = $this->folder();
		$name = $id . '.' . self::TYPES[$mime];
		if ($folder->fileExists($name) === false) {
			$folder->newFile($name, $bytes);
		}

		return $id;
	}//end store()

	/**
	 * Read an icon.
	 *
	 * @param string $id The icon's id.
	 *
	 * @return array{bytes: string, mime: string}|null The icon, or null when there is none.
	 *
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	public function read(string $id): ?array {
		if (preg_match(self::ID_PATTERN, $id) !== 1) {
			return null;
		}

		$folder = $this->folder();
		foreach (self::TYPES as $mime => $extension) {
			try {
				return ['bytes' => $folder->getFile($id . '.' . $extension)->getContent(), 'mime' => $mime];
			} catch (NotFoundException) {
				continue;
			}
		}

		return null;
	}//end read()

	/**
	 * The icons folder.
	 *
	 * @return ISimpleFolder The folder.
	 */
	private function folder(): ISimpleFolder {
		return (new AppDataFolder())->open(appData: $this->appData, name: self::FOLDER);
	}//end folder()
}//end class
