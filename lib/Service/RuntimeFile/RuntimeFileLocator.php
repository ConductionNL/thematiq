<?php

/**
 * Find a token set's file, an override file or an image, shipped or runtime.
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
 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service\RuntimeFile;

use OCA\Thematiq\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\ITempManager;
use OCP\IURLGenerator;
use RuntimeException;

/**
 * One answer to "where is this file", for every reader.
 *
 * A name such as `css/tokens/utrecht.css` is either SHIPPED, in the release's
 * app directory, or RUNTIME, written after install and kept in the
 * {@see RuntimeFileStore}.
 *
 * A shipped set's own files (its stylesheet, its dark variant and its logo,
 * for every id in `token-sets.json`) are PROTECTED: they are only ever read
 * from the app directory, so nothing stored at runtime can stand in for them.
 * Every other name is read from the store first and from the app directory
 * second. The second read only matters until the
 * {@see \OCA\Thematiq\Repair\MoveRuntimeFilesToAppData} repair step has moved an
 * older install's files; reading the app directory first would keep serving a
 * stale copy wherever that move could not delete it, while every save went to
 * the store. Writing is not offered here at all; writers go to the store.
 *
 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
 */
class RuntimeFileLocator {

	/**
	 * Constructor.
	 *
	 * @param IAppManager      $appManager   Resolves the app directory.
	 * @param RuntimeFileStore $store        Where runtime files live.
	 * @param IURLGenerator    $urlGenerator Builds file and route URLs.
	 * @param ITempManager     $tempManager  Hands out temporary files for consumers that need a path.
	 * @param RuntimeFileNames $names        The name policy.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly RuntimeFileStore $store,
		private readonly IURLGenerator $urlGenerator,
		private readonly ITempManager $tempManager,
		private readonly RuntimeFileNames $names=new RuntimeFileNames(),
	) {
	}//end __construct()

	/**
	 * The store runtime files are written to.
	 *
	 * @return RuntimeFileStore The store.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function store(): RuntimeFileStore {
		return $this->store;
	}//end store()

	/**
	 * The ids of the token sets the release ships, read once per request.
	 *
	 * @var array<string, true>|null
	 */
	private ?array $shippedIds = null;

	/**
	 * Whether a name is a file the app directory holds.
	 *
	 * @param string $name An app-relative name.
	 *
	 * @return bool True when it does.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function isShipped(string $name): bool {
		return is_file($this->appPath() . '/' . ltrim($name, '/'));
	}//end isShipped()

	/**
	 * Whether a name belongs to a shipped set and may only come from the release.
	 *
	 * @param string $name An app-relative name.
	 *
	 * @return bool True for a shipped set's stylesheet, dark variant or logo.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function isProtected(string $name): bool {
		$name = ltrim($name, '/');
		if (preg_match('#^css/tokens/(?:dark/)?([a-z0-9-]+)\.css$#', $name, $match) !== 1
			&& preg_match('#^img/logos/([a-z0-9-]+)\.[a-z]+$#', $name, $match) !== 1
		) {
			return false;
		}

		return isset($this->shippedIds()[$match[1]]);
	}//end isProtected()

	/**
	 * The ids in the release's `token-sets.json`.
	 *
	 * @return array<string, true> Ids as keys.
	 */
	private function shippedIds(): array {
		if ($this->shippedIds !== null) {
			return $this->shippedIds;
		}

		$this->shippedIds = [];
		$manifest = $this->appPath() . '/token-sets.json';
		if (is_file($manifest) === false) {
			return $this->shippedIds;
		}

		$sets = json_decode((string)file_get_contents($manifest), true);
		if (is_array($sets) === false) {
			return $this->shippedIds;
		}

		foreach ($sets as $set) {
			if (is_array($set) === true && isset($set['id']) === true) {
				$this->shippedIds[(string)$set['id']] = true;
			}
		}

		return $this->shippedIds;
	}//end shippedIds()

	/**
	 * Whether a name is a runtime file the store holds, and not a protected shipped one.
	 *
	 * @param string $name An app-relative name.
	 *
	 * @return bool True when the store holds it and it may be served from there.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function inStore(string $name): bool {
		return $this->names->isAllowed(name: $name) === true
			&& $this->isProtected(name: $name) === false
			&& $this->store->exists(name: $name) === true;
	}//end inStore()

	/**
	 * Whether a file exists, shipped or runtime.
	 *
	 * @param string $name An app-relative name.
	 *
	 * @return bool True when either holds it.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function exists(string $name): bool {
		return $this->inStore(name: $name) === true || $this->isShipped(name: $name) === true;
	}//end exists()

	/**
	 * Read a file, shipped or runtime.
	 *
	 * @param string $name An app-relative name.
	 *
	 * @return string|null The content, or null when neither holds it.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function read(string $name): ?string {
		if ($this->inStore(name: $name) === true) {
			return $this->store->read(name: $name);
		}

		if ($this->isShipped(name: $name) === false) {
			return null;
		}

		$content = file_get_contents($this->appPath() . '/' . ltrim($name, '/'));
		if ($content === false) {
			return null;
		}

		return $content;
	}//end read()

	/**
	 * The names in one directory, shipped and runtime, without duplicates.
	 *
	 * @param string $directory An app-relative directory such as `css/tokens`.
	 * @param string $suffix    Keep only names ending in this, such as `.css`.
	 *
	 * @return array<int, string> Full app-relative names, sorted.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function listDirectory(string $directory, string $suffix = ''): array {
		$directory = trim($directory, '/');
		$names = [];
		$paths = glob($this->appPath() . '/' . $directory . '/*');
		if ($paths === false) {
			$paths = [];
		}

		foreach ($paths as $path) {
			if (is_file($path) === true) {
				$names[] = $directory . '/' . basename($path);
			}
		}

		foreach ($this->store->listDirectory(directory: $directory) as $name) {
			if ($this->isProtected(name: $name) === false) {
				$names[] = $name;
			}
		}

		if ($suffix !== '') {
			$names = array_filter($names, static fn (string $name): bool => str_ends_with($name, $suffix));
		}

		$names = array_values(array_unique($names));
		sort($names);

		return $names;
	}//end listDirectory()

	/**
	 * The URL a browser loads a file from.
	 *
	 * @param string $name An app-relative name.
	 *
	 * @return string|null An absolute path URL, or null when neither holds the file.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function url(string $name): ?string {
		if ($this->inStore(name: $name) === true) {
			return $this->routeUrl(name: $name);
		}

		if ($this->isShipped(name: $name) === false) {
			return null;
		}

		return $this->urlGenerator->linkTo(Application::APP_ID, ltrim($name, '/'));
	}//end url()

	/**
	 * The route URL of a runtime file, with its revision for cache busting.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return string The URL.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function routeUrl(string $name): string {
		return $this->urlGenerator->linkToRoute('thematiq.runtime_file.serve', ['name' => $name])
			. '?v=' . $this->store->revision(name: $name);
	}//end routeUrl()

	/**
	 * A filesystem path holding the file, for a consumer that only takes a path.
	 *
	 * Nextcloud's `ImageManager::updateImage()` reads an uploaded image from a
	 * path. A shipped file already has one; a runtime file is copied into a
	 * temporary file that Nextcloud cleans up at the end of the request.
	 *
	 * @param string $name An app-relative name.
	 *
	 * @return string|null The path, or null when neither holds the file.
	 *
	 * @throws RuntimeException When a temporary copy cannot be made.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function localPath(string $name): ?string {
		if ($this->inStore(name: $name) === false) {
			if ($this->isShipped(name: $name) === false) {
				return null;
			}

			return $this->appPath() . '/' . ltrim($name, '/');
		}

		$content = $this->store->read(name: $name);
		if ($content === null) {
			return null;
		}

		$extension = (string)pathinfo($name, PATHINFO_EXTENSION);
		$postfix = '';
		if ($extension !== '') {
			$postfix = '.' . $extension;
		}

		$path = $this->tempManager->getTemporaryFile($postfix);
		if ($path === false || file_put_contents($path, $content) === false) {
			throw new RuntimeException('Could not make a temporary copy of ' . $name);
		}

		return $path;
	}//end localPath()

	/**
	 * The content type a runtime file is served with.
	 *
	 * @param string $name An allowed name.
	 *
	 * @return string The MIME type.
	 *
	 * @spec openspec/changes/runtime-files-in-appdata/specs/runtime-file-storage/spec.md
	 */
	public function contentType(string $name): string {
		return $this->names->contentType(name: $name);
	}//end contentType()

	/**
	 * The app directory.
	 *
	 * @return string The absolute path.
	 */
	private function appPath(): string {
		return rtrim($this->appManager->getAppPath(Application::APP_ID), '/');
	}//end appPath()
}//end class
