<?php

/**
 * Thematiq Gallery Install Service.
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
use OCA\Thematiq\Service\Exception\GalleryException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use RuntimeException;
use Throwable;

/**
 * Installs a theme gallery entry: download, compare the SHA-256 with the index, then the
 * same path an upload takes (TokenSetConverterService, the CSS whitelist in
 * CustomTokenSetValidator, CustomTokenSetService::store()), with the entry's provenance on
 * the stored set. A mismatch or a refusal stores nothing. An update replaces the set under
 * the id it already had and keeps it active when it was.
 *
 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - an install IS the upload path plus a download: it needs every class the
 *   upload controller uses, and routing it through the controller would put HTTP concerns in a service.
 */
class GalleryInstallService {

	/**
	 * Constructor.
	 *
	 * @param ThemeGalleryService      $gallery       The index.
	 * @param IClientService           $clientService The HTTP client service.
	 * @param TokenSetConverterService $converter     The converter every upload runs through.
	 * @param CustomTokenSetValidator  $validator     The CSS whitelist.
	 * @param CssParserService         $cssParser     The CSS parser.
	 * @param CustomTokenSetService    $customSets    Custom set storage.
	 * @param ThemingAuditService      $audit         The audit trail.
	 * @param IConfig                  $config        App config, for the active token set.
	 * @param IL10N                    $l10n          Translations.
	 * @param ITimeFactory             $time          The clock.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) - see the class comment: the upload path's own collaborators.
	 */
	public function __construct(
		private ThemeGalleryService $gallery,
		private IClientService $clientService,
		private TokenSetConverterService $converter,
		private CustomTokenSetValidator $validator,
		private CssParserService $cssParser,
		private CustomTokenSetService $customSets,
		private ThemingAuditService $audit,
		private IConfig $config,
		private IL10N $l10n,
		private ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * Install or update one gallery entry.
	 *
	 * @param string $galleryId The entry id.
	 *
	 * @return array{id: string, updated: bool, imported: int, warnings: array<int, array<string, mixed>>}
	 *
	 * @throws GalleryException 404 unknown entry, 502 download failed, 422 mismatch or refused by the upload path.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-updates-are-offered-never-applied
	 */
	public function install(string $galleryId): array {
		$entry = $this->gallery->findEntry(galleryId: $galleryId);
		if ($entry === null) {
			throw new GalleryException(message: $this->l10n->t('This house style is not in the gallery.'), code: 404);
		}

		$content = $this->download(url: (string)$entry['fileUrl']);
		if (hash_equals((string)$entry['sha256'], hash('sha256', $content)) === false) {
			throw new GalleryException(
				message: $this->l10n->t('The downloaded file does not match the gallery index, so nothing was installed.'),
				code: 422
			);
		}

		$existing = $this->gallery->installedFor(galleryId: $galleryId);
		$name = (string)$entry['name'];
		if ($existing !== null) {
			$name = (string)($existing['name'] ?? $name);
		}

		$prepared = $this->prepare(name: $name, content: $content, fileUrl: (string)$entry['fileUrl']);

		$wasActive = false;
		if ($existing !== null) {
			$wasActive = ($this->config->getAppValue(Application::APP_ID, 'token_set', '') === $existing['id']);
			$this->customSets->delete(id: (string)$existing['id']);
		}

		try {
			$stored = $this->customSets->store(
				displayName: $name,
				description: (string)$entry['description'],
				declarations: $prepared['accepted'],
				version: ($prepared['converted']['manifestEntry']['upstreamVersion'] ?? null),
				importWarnings: ($prepared['converted']['importWarnings'] ?? []),
				css: $prepared['converted']['css'],
				theming: ($prepared['converted']['manifestEntry']['theming'] ?? []),
				logoAsset: ($prepared['converted']['logoAsset'] ?? null),
				provenance: [
					'galleryId' => $galleryId,
					'sourceUrl' => (string)$entry['sourceUrl'],
					'licence' => (string)$entry['licence'],
					'sha256' => (string)$entry['sha256'],
					'installedOn' => gmdate('Y-m-d\TH:i:s\Z', $this->time->getTime()),
				]
			);
		} catch (RuntimeException $e) {
			throw new GalleryException(message: $e->getMessage(), code: 422, previous: $e);
		}

		if ($wasActive === true) {
			$this->config->setAppValue(Application::APP_ID, 'token_set', $stored['id']);
		}

		$this->audit->log(
			action: 'custom_set_uploaded',
			context: [
				'id' => $stored['id'],
				'name' => $name,
				'galleryId' => $galleryId,
				'declarationCount' => (int)($prepared['converted']['imported'] ?? 0),
				'contentHash' => 'sha256:' . substr((string)$entry['sha256'], 0, 12),
			]
		);

		return [
			'id' => $stored['id'],
			'updated' => ($existing !== null),
			'imported' => (int)($prepared['converted']['imported'] ?? 0),
			'warnings' => $stored['warnings'],
		];
	}//end install()

	/**
	 * Download the entry's file, bounded like an upload.
	 *
	 * @param string $url The file URL.
	 *
	 * @return string The body.
	 *
	 * @throws GalleryException 502 when it cannot be read, 422 when it is larger than an upload may be.
	 */
	private function download(string $url): string {
		try {
			$response = $this->clientService->newClient()->get($url, ['timeout' => ThemeGalleryService::TIMEOUT_SECONDS]);
		} catch (Throwable $e) {
			throw new GalleryException(message: $this->l10n->t('The file could not be downloaded. Try again later.'), code: 502, previous: $e);
		}

		if ($response->getStatusCode() !== 200) {
			throw new GalleryException(message: $this->l10n->t('The file could not be downloaded. Try again later.'), code: 502);
		}

		$body = (string)$response->getBody();
		if (strlen($body) > CustomTokenSetValidator::MAX_SIZE) {
			throw new GalleryException(message: $this->l10n->t('The file is larger than a token set may be, so nothing was installed.'), code: 422);
		}

		return $body;
	}//end download()

	/**
	 * Convert and validate exactly as an upload is, before anything is written.
	 *
	 * @param string $name    The display name the set is stored under.
	 * @param string $content The downloaded file.
	 * @param string $fileUrl Its URL, whose file name tells the converter nothing it relies on.
	 *
	 * @return array{converted: array<string, mixed>, accepted: array<string, string>}
	 *
	 * @throws GalleryException 422 when the upload path refuses the file.
	 */
	private function prepare(string $name, string $content, string $fileUrl): array {
		$slug = $this->customSets->slugify(name: $name);
		try {
			$converted = $this->converter->convert(
				content: $content,
				slug: $slug,
				displayName: $name,
				sourceName: basename((string)parse_url($fileUrl, PHP_URL_PATH)),
				assetName: CustomTokenSetService::ID_PREFIX . $slug
			);
		} catch (RuntimeException $e) {
			throw new GalleryException(message: $this->refused(reason: $e->getMessage()), code: 422, previous: $e);
		}

		if ((int)($converted['imported'] ?? 0) === 0) {
			throw new GalleryException(message: $this->refused(reason: $this->l10n->t('nothing in it maps onto a Nextcloud token')), code: 422);
		}

		$css = (string)$converted['css'];
		if ($this->validator->hasDisallowedSelector(css: $css) === true) {
			throw new GalleryException(message: $this->refused(reason: $this->l10n->t('it contains a selector other than :root')), code: 422);
		}

		$split = $this->validator->validateDeclarations(declarations: $this->cssParser->parseRootBlock(css: $css), slug: $slug);
		if ($split === null) {
			$error = $this->validator->getLastError();
			throw new GalleryException(message: $this->refused(reason: (string)($error['message'] ?? '')), code: 422);
		}

		return ['converted' => $converted, 'accepted' => $split['accepted']];
	}//end prepare()

	/**
	 * The refusal sentence.
	 *
	 * @param string $reason Why the upload path refused the file.
	 *
	 * @return string The message.
	 */
	private function refused(string $reason): string {
		return $this->l10n->t('The file was refused, so nothing was installed: {reason}', ['reason' => $reason]);
	}//end refused()
}//end class
