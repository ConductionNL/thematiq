<?php

/**
 * Thematiq Theme Gallery Service.
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
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reads the theme gallery index: an opt-in, bounded request to one configurable URL.
 *
 * Off by default. While off, nothing is requested. When on, the index is read when an
 * administrator opens the Gallery block, at most once an hour, and revalidated with its
 * ETag after that. Requests carry no cookies or instance data and time out after 10 s.
 * Entries GalleryEntryValidator refuses are not listed.
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */
class ThemeGalleryService {

	/**
	 * App config key: 'yes' when the gallery is on.
	 *
	 * @var string
	 */
	public const CONFIG_ENABLED = 'gallery_enabled';

	/**
	 * App config key: the index URL, for a mirror.
	 *
	 * @var string
	 */
	public const CONFIG_INDEX_URL = 'gallery_index_url';

	/**
	 * App config key: the cached index, `{fetchedAt, etag, entries}`.
	 *
	 * @var string
	 */
	private const CONFIG_CACHE = 'gallery_index_cache';

	/**
	 * The index this repository publishes.
	 *
	 * @var string
	 */
	public const DEFAULT_INDEX_URL = 'https://raw.githubusercontent.com/ConductionNL/thematiq/development/gallery/index.json';

	/**
	 * How long a read index is used without asking again.
	 *
	 * @var integer
	 */
	private const CACHE_SECONDS = 3600;

	/**
	 * Request timeout in seconds.
	 *
	 * @var integer
	 */
	public const TIMEOUT_SECONDS = 10;

	/**
	 * Constructor.
	 *
	 * @param IConfig               $config        App config.
	 * @param IClientService        $clientService The HTTP client service.
	 * @param CustomTokenSetService $customSets    The installed custom sets, for provenance.
	 * @param GalleryEntryValidator $entries       Decides which index entries may be listed.
	 * @param ITimeFactory          $time          The clock.
	 * @param LoggerInterface       $logger        The logger.
	 */
	public function __construct(
		private IConfig $config,
		private IClientService $clientService,
		private CustomTokenSetService $customSets,
		private GalleryEntryValidator $entries,
		private ITimeFactory $time,
		private LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Whether the gallery is on.
	 *
	 * @return boolean True when an administrator turned it on.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-the-gallery-is-opt-in-and-disclosed
	 */
	public function isEnabled(): bool {
		return $this->config->getAppValue(Application::APP_ID, self::CONFIG_ENABLED, 'no') === 'yes';
	}//end isEnabled()

	/**
	 * Turn the gallery on or off.
	 *
	 * @param boolean $enabled The new state.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-the-gallery-is-opt-in-and-disclosed
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) - the administrator's toggle, stored as is.
	 */
	public function setEnabled(bool $enabled): void {
		$value = 'no';
		if ($enabled === true) {
			$value = 'yes';
		}

		$this->config->setAppValue(Application::APP_ID, self::CONFIG_ENABLED, $value);
	}//end setEnabled()

	/**
	 * The index URL: the configured mirror, or the default.
	 *
	 * @return string The URL.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-the-gallery-is-opt-in-and-disclosed
	 */
	public function getIndexUrl(): string {
		$url = trim($this->config->getAppValue(Application::APP_ID, self::CONFIG_INDEX_URL, ''));
		if ($url === '') {
			return self::DEFAULT_INDEX_URL;
		}

		return $url;
	}//end getIndexUrl()

	/**
	 * What the Gallery block shows. Makes no request while the gallery is off.
	 *
	 * @return array{enabled: bool, host: string, reachable: bool|null, entries: array<int, array<string, mixed>>}
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-an-administrator-browses-the-gallery
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-updates-are-offered-never-applied
	 */
	public function browse(): array {
		$result = [
			'enabled' => $this->isEnabled(),
			'host' => (string)parse_url($this->getIndexUrl(), PHP_URL_HOST),
			'reachable' => null,
			'entries' => [],
		];
		if ($result['enabled'] === false) {
			return $result;
		}

		$entries = $this->entries();
		$result['reachable'] = ($entries !== null);
		foreach (($entries ?? []) as $entry) {
			$installed = $this->installedFor(galleryId: $entry['id']);
			$entry['installed'] = null;
			$entry['updateAvailable'] = false;
			if ($installed !== null) {
				$entry['installed'] = (string)$installed['id'];
				$entry['updateAvailable'] = (($installed['provenance']['sha256'] ?? null) !== $entry['sha256']);
			}

			$result['entries'][] = $entry;
		}

		return $result;
	}//end browse()

	/**
	 * One valid index entry by id, or null when the gallery is off, unreachable or lacks it.
	 *
	 * @param string $galleryId The entry id.
	 *
	 * @return array<string, mixed>|null The entry.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
	 */
	public function findEntry(string $galleryId): ?array {
		if ($this->isEnabled() === false) {
			return null;
		}

		foreach (($this->entries() ?? []) as $entry) {
			if ($entry['id'] === $galleryId) {
				return $entry;
			}
		}

		return null;
	}//end findEntry()

	/**
	 * The installed custom set that came from this gallery entry, if any.
	 *
	 * @param string $galleryId The entry id.
	 *
	 * @return array<string, mixed>|null The custom set as CustomTokenSetService::list() returns it.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-updates-are-offered-never-applied
	 */
	public function installedFor(string $galleryId): ?array {
		foreach ($this->customSets->list() as $set) {
			if (($set['provenance']['galleryId'] ?? null) === $galleryId) {
				return $set;
			}
		}

		return null;
	}//end installedFor()

	/**
	 * The valid entries of the index, from the cache or the network; null when unreachable.
	 *
	 * @return array<int, array<string, mixed>>|null The entries.
	 */
	private function entries(): ?array {
		$raw = $this->readIndex();
		if ($raw === null) {
			return null;
		}

		$entries = [];
		foreach ($raw as $item) {
			$entry = $this->entries->validate(raw: $item);
			if ($entry !== null) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}//end entries()

	/**
	 * The raw `entries` list: cached for an hour, then revalidated with the ETag.
	 *
	 * @return array<int, mixed>|null The raw entries, or null when the index cannot be read.
	 */
	private function readIndex(): ?array {
		$now = $this->time->getTime();
		$cache = $this->readCache();
		if ($cache !== null && ($now - (int)($cache['fetchedAt'] ?? 0)) < self::CACHE_SECONDS) {
			return $cache['entries'];
		}

		$response = $this->fetch(etag: (string)($cache['etag'] ?? ''));
		if ($response === null) {
			return null;
		}

		if ($response['status'] === 304 && $cache !== null) {
			$cache['fetchedAt'] = $now;
			$this->writeCache(cache: $cache);
			return $cache['entries'];
		}

		$decoded = json_decode($response['body'], true);
		if ($response['status'] !== 200 || is_array($decoded) === false || is_array($decoded['entries'] ?? null) === false) {
			$this->logger->info('thematiq gallery: the index answered {status} without an entries list', ['status' => $response['status']]);
			return null;
		}

		$cache = [
			'url' => $this->getIndexUrl(),
			'fetchedAt' => $now,
			'etag' => $response['etag'],
			'entries' => array_values($decoded['entries']),
		];
		$this->writeCache(cache: $cache);

		return $cache['entries'];
	}//end readIndex()

	/**
	 * The cached index for the current URL, or null.
	 *
	 * @return array{url: string, fetchedAt: int, etag: string, entries: array<int, mixed>}|null The cache.
	 */
	private function readCache(): ?array {
		$cache = json_decode($this->config->getAppValue(Application::APP_ID, self::CONFIG_CACHE, ''), true);
		if (is_array($cache) === false || ($cache['url'] ?? null) !== $this->getIndexUrl() || is_array($cache['entries'] ?? null) === false) {
			return null;
		}

		return $cache;
	}//end readCache()

	/**
	 * Store the cache.
	 *
	 * @param array<string, mixed> $cache The cache.
	 *
	 * @return void
	 */
	private function writeCache(array $cache): void {
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_CACHE, (string)json_encode($cache, JSON_UNESCAPED_SLASHES));
	}//end writeCache()

	/**
	 * One bounded GET of the index.
	 *
	 * @param string $etag The ETag to revalidate, or ''.
	 *
	 * @return array{status: int, body: string, etag: string}|null The answer, or null when the host did not answer.
	 */
	private function fetch(string $etag): ?array {
		$headers = ['Accept' => 'application/json'];
		if ($etag !== '') {
			$headers['If-None-Match'] = $etag;
		}

		try {
			$response = $this->clientService->newClient()->get(
				$this->getIndexUrl(),
				['timeout' => self::TIMEOUT_SECONDS, 'headers' => $headers]
			);
		} catch (Throwable $e) {
			$this->logger->info('thematiq gallery: the index could not be read: {message}', ['message' => $e->getMessage()]);
			return null;
		}

		return [
			'status' => $response->getStatusCode(),
			'body' => (string)$response->getBody(),
			'etag' => (string)$response->getHeader('ETag'),
		];
	}//end fetch()
}//end class
