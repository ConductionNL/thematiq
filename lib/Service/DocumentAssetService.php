<?php

/**
 * Thematiq document assets: a print logo, a cover image and a footer line.
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
 * @spec openspec/specs/document-house-style/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use RuntimeException;

/**
 * The optional assets of the document house style, stored in app data
 * `documents/`: a document logo (often a print version of the logo) and a
 * cover image, each PNG, JPEG, WebP or SVG up to 2 MB. An SVG with script,
 * event handlers, foreign objects or `javascript:` addresses is refused.
 * One free footer line, at most 200 characters, is kept in app config.
 *
 * @spec openspec/specs/document-house-style/spec.md
 */
class DocumentAssetService {

	/**
	 * The asset kinds.
	 *
	 * @var array<int, string>
	 */
	public const KINDS = ['logo', 'cover'];

	/**
	 * The largest asset.
	 *
	 * @var int
	 */
	public const MAX_BYTES = (2 * 1024 * 1024);

	/**
	 * The longest footer line.
	 *
	 * @var int
	 */
	public const MAX_FOOTER_LINE = 200;

	/**
	 * The app data folder.
	 *
	 * @var string
	 */
	public const FOLDER = 'documents';

	/**
	 * App config key: asset metadata by kind.
	 *
	 * @var string
	 */
	public const KEY_ASSETS = 'document_style_assets';

	/**
	 * App config key: the extra footer line.
	 *
	 * @var string
	 */
	public const KEY_FOOTER_LINE = 'document_style_footer_line';

	/**
	 * Accepted types and their file extension.
	 *
	 * @var array<string, string>
	 */
	private const TYPES = [
		'image/png' => 'png',
		'image/jpeg' => 'jpg',
		'image/webp' => 'webp',
		'image/svg+xml' => 'svg',
	];

	/**
	 * Constructor.
	 *
	 * @param IAppData $appData The app's data store.
	 * @param IConfig  $config  App config.
	 */
	public function __construct(
		private readonly IAppData $appData,
		private readonly IConfig $config,
	) {
	}//end __construct()

	/**
	 * The metadata of the stored assets.
	 *
	 * @return array<string, array{mime: string, size: int, uploadedAt: int}> Metadata by kind.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function getAssets(): array {
		$assets = json_decode((string)$this->config->getAppValue(Application::APP_ID, self::KEY_ASSETS, '{}'), true);
		if (is_array($assets) === false) {
			return [];
		}

		return array_intersect_key($assets, array_flip(self::KINDS));
	}//end getAssets()

	/**
	 * Validate and store an asset, replacing the previous one of its kind.
	 *
	 * @param string $kind  `logo` or `cover`.
	 * @param string $bytes The uploaded file.
	 *
	 * @return array{mime: string, size: int, uploadedAt: int} The stored metadata.
	 *
	 * @throws RuntimeException Code 404 for an unknown kind, 413 when too large, 422 for a refused file.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function store(string $kind, string $bytes): array {
		$this->assertKind(kind: $kind);
		if (strlen($bytes) > self::MAX_BYTES) {
			throw new RuntimeException(message: 'The file is larger than 2 MB.', code: 413);
		}

		$mime = $this->detectType(bytes: $bytes);
		if ($mime === null) {
			throw new RuntimeException(message: 'Upload a PNG, JPEG, WebP or SVG image.', code: 422);
		}

		if ($mime === 'image/svg+xml' && $this->isSafeSvg(svg: $bytes) === false) {
			throw new RuntimeException(message: 'The SVG contains script or other active content.', code: 422);
		}

		$folder = $this->folder();
		$this->removeFiles(folder: $folder, kind: $kind);
		$folder->newFile($kind . '.' . self::TYPES[$mime], $bytes);

		$assets = $this->getAssets();
		$assets[$kind] = ['mime' => $mime, 'size' => strlen($bytes), 'uploadedAt' => time()];
		$this->config->setAppValue(Application::APP_ID, self::KEY_ASSETS, (string)json_encode($assets));

		return $assets[$kind];
	}//end store()

	/**
	 * Remove an asset.
	 *
	 * @param string $kind `logo` or `cover`.
	 *
	 * @return void
	 *
	 * @throws RuntimeException Code 404 for an unknown kind.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function delete(string $kind): void {
		$this->assertKind(kind: $kind);
		$this->removeFiles(folder: $this->folder(), kind: $kind);
		$assets = $this->getAssets();
		unset($assets[$kind]);
		$this->config->setAppValue(Application::APP_ID, self::KEY_ASSETS, (string)json_encode($assets));
	}//end delete()

	/**
	 * Read an asset.
	 *
	 * @param string $kind `logo` or `cover`.
	 *
	 * @return array{bytes: string, mime: string}|null The file, or null when there is none.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function read(string $kind): ?array {
		$meta = ($this->getAssets()[$kind] ?? null);
		if (is_array($meta) === false || isset(self::TYPES[$meta['mime'] ?? '']) === false) {
			return null;
		}

		try {
			$file = $this->folder()->getFile($kind . '.' . self::TYPES[$meta['mime']]);

			return ['bytes' => $file->getContent(), 'mime' => $meta['mime']];
		} catch (NotFoundException $e) {
			return null;
		}
	}//end read()

	/**
	 * The extra footer line.
	 *
	 * @return string The line, empty when unset.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function getFooterLine(): string {
		return (string)$this->config->getAppValue(Application::APP_ID, self::KEY_FOOTER_LINE, '');
	}//end getFooterLine()

	/**
	 * Set the extra footer line. Stored as text; every reader escapes it.
	 *
	 * @param string $line The line.
	 *
	 * @return string The stored line.
	 *
	 * @throws RuntimeException Code 422 when the line is too long or holds a line break.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function setFooterLine(string $line): string {
		$line = trim($line);
		if (mb_strlen($line) > self::MAX_FOOTER_LINE || preg_match('/[\r\n]/', $line) === 1) {
			throw new RuntimeException(message: 'The footer line is one line of at most 200 characters.', code: 422);
		}

		$this->config->setAppValue(Application::APP_ID, self::KEY_FOOTER_LINE, $line);

		return $line;
	}//end setFooterLine()

	/**
	 * The `documentStyle` section of the configuration bundle: the footer line as a
	 * value, the images as metadata only (like fonts, the files stay behind).
	 *
	 * @return array{footerLine: string, binariesIncluded: bool, assets: array<string, mixed>} The section.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function exportBundle(): array {
		return ['footerLine' => $this->getFooterLine(), 'binariesIncluded' => false, 'assets' => $this->getAssets()];
	}//end exportBundle()

	/**
	 * Validate the bundle's `documentStyle` section. Absent (an older bundle) leaves the footer line alone.
	 *
	 * @param mixed $section The section, or null when absent.
	 *
	 * @return array{errors: array<int, string>, value: string|null} The errors and the footer line to apply.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function validateBundle(mixed $section): array {
		if ($section === null) {
			return ['errors' => [], 'value' => null];
		}

		$line = null;
		if (is_array($section) === true && is_string($section['footerLine'] ?? null) === true) {
			$line = trim($section['footerLine']);
		}

		if ($line === null || mb_strlen($line) > self::MAX_FOOTER_LINE || preg_match('/[\r\n]/', $line) === 1) {
			return ['errors' => ['"documentStyle.footerLine" must be one line of at most 200 characters.'], 'value' => null];
		}

		return ['errors' => [], 'value' => $line];
	}//end validateBundle()

	/**
	 * The image type of a file from its first bytes.
	 *
	 * @param string $bytes The file.
	 *
	 * @return string|null The MIME type, or null when not accepted.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function detectType(string $bytes): ?string {
		$signatures = [
			"\x89PNG\r\n\x1a\n" => 'image/png',
			"\xFF\xD8\xFF" => 'image/jpeg',
		];
		foreach ($signatures as $magic => $mime) {
			if (str_starts_with($bytes, $magic) === true) {
				return $mime;
			}
		}

		if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
			return 'image/webp';
		}

		if (preg_match('/^\s*(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!DOCTYPE[^>]*>\s*)?<svg[\s>]/is', $bytes) === 1) {
			return 'image/svg+xml';
		}

		return null;
	}//end detectType()

	/**
	 * Whether an SVG holds no active content.
	 *
	 * @param string $svg The SVG text.
	 *
	 * @return bool True when safe to serve as an image.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function isSafeSvg(string $svg): bool {
		$patterns = [
			'/<\s*script/i',
			'/<\s*foreignObject/i',
			'/\son[a-z]+\s*=/i',
			'/javascript\s*:/i',
			'/<!ENTITY/i',
			'/href\s*=\s*["\']\s*data:text\/html/i',
		];
		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $svg) === 1) {
				return false;
			}
		}

		return true;
	}//end isSafeSvg()

	/**
	 * Refuse an unknown kind.
	 *
	 * @param string $kind The kind.
	 *
	 * @return void
	 *
	 * @throws RuntimeException Code 404 for an unknown kind.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function assertKind(string $kind): void {
		if (in_array($kind, self::KINDS, true) === false) {
			throw new RuntimeException(message: 'Unknown document asset.', code: 404);
		}
	}//end assertKind()

	/**
	 * Remove every stored file of a kind, whatever its extension.
	 *
	 * @param ISimpleFolder $folder The folder.
	 * @param string $kind The kind.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function removeFiles(ISimpleFolder $folder, string $kind): void {
		foreach (self::TYPES as $extension) {
			if ($folder->fileExists($kind . '.' . $extension) === true) {
				$folder->getFile($kind . '.' . $extension)->delete();
			}
		}
	}//end removeFiles()

	/**
	 * The app data folder, created on first use.
	 *
	 * @return ISimpleFolder The folder.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function folder(): ISimpleFolder {
		try {
			return $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder(self::FOLDER);
		}
	}//end folder()
}//end class
