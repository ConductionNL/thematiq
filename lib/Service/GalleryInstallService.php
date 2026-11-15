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
	 * @param GalleryDownloadSources $downloads The gallery index and the HTTP client service.
	 * @param TokenSetUploadPipeline $pipeline The converter, the CSS whitelist and the CSS parser.
	 * @param GalleryInstallRecords $records Custom set storage, the audit trail, the app config and the clock.
	 * @param IL10N $l10n Translations.
	 */
	public function __construct(
		private readonly GalleryDownloadSources $downloads,
		private readonly TokenSetUploadPipeline $pipeline,
		private readonly GalleryInstallRecords $records,
		private IL10N $l10n,
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
		$install = $this->prepareInstall(galleryId: $galleryId);
		$install['wasActive'] = $this->retire(existing: $install['existing']);
		$stored = $this->storeInstall(install: $install);
		$this->recordInstall(install: $install, stored: $stored);

		return [
			'id' => $stored['id'],
			'updated' => ($install['existing'] !== null),
			'imported' => (int)($install['prepared']['converted']['imported'] ?? 0),
			'warnings' => $stored['warnings'],
		];
	}//end install()

	/**
	 * Find the entry, download and check its file, and convert it, before anything is changed.
	 *
	 * @param string $galleryId The entry id.
	 *
	 * @return array{galleryId: string, entry: array<string, mixed>, existing: array<string, mixed>|null, name: string, prepared: array<string, mixed>}
	 *
	 * @throws GalleryException 404 unknown entry, 502 download failed, 422 mismatch or refused by the upload path.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
	 */
	private function prepareInstall(string $galleryId): array {
		$entry = $this->entryOrFail(galleryId: $galleryId);
		$content = $this->downloadVerified(entry: $entry);
		$existing = $this->downloads->gallery->installedFor(galleryId: $galleryId);
		$name = $this->nameFor(entry: $entry, existing: $existing);

		return [
			'galleryId' => $galleryId,
			'entry' => $entry,
			'existing' => $existing,
			'name' => $name,
			'prepared' => $this->prepare(name: $name, file: new GalleryFile(content: $content, fileUrl: (string)$entry['fileUrl'])),
		];
	}//end prepareInstall()

	/**
	 * The gallery entry of this id.
	 *
	 * @param string $galleryId The entry id.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws GalleryException 404 when the gallery has no such entry.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
	 */
	private function entryOrFail(string $galleryId): array {
		$entry = $this->downloads->gallery->findEntry(galleryId: $galleryId);
		if ($entry === null) {
			throw new GalleryException(message: $this->l10n->t('This house style is not in the gallery.'), code: 404);
		}

		return $entry;
	}//end entryOrFail()

	/**
	 * Download an entry's file and check it against the checksum the gallery index gives.
	 *
	 * @param array<string, mixed> $entry The gallery entry.
	 *
	 * @return string The file content.
	 *
	 * @throws GalleryException 502 download failed, 422 too large or mismatch.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
	 */
	private function downloadVerified(array $entry): string {
		$content = $this->download(url: (string)$entry['fileUrl']);
		if (hash_equals((string)$entry['sha256'], hash('sha256', $content)) === false) {
			throw new GalleryException(
				message: $this->l10n->t('The downloaded file does not match the gallery index, so nothing was installed.'),
				code: 422
			);
		}

		return $content;
	}//end downloadVerified()

	/**
	 * The name the set is stored under: the installed set's own name on an update, else the entry's.
	 *
	 * @param array<string, mixed> $entry The gallery entry.
	 * @param array<string, mixed>|null $existing The installed set, if any.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-updates-are-offered-never-applied
	 */
	private function nameFor(array $entry, ?array $existing): string {
		$name = (string)$entry['name'];
		if ($existing !== null) {
			$name = (string)($existing['name'] ?? $name);
		}

		return $name;
	}//end nameFor()

	/**
	 * Delete the installed set an update replaces.
	 *
	 * @param array<string, mixed>|null $existing The installed set, if any.
	 *
	 * @return bool Whether it was the active token set.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-updates-are-offered-never-applied
	 */
	private function retire(?array $existing): bool {
		if ($existing === null) {
			return false;
		}

		$wasActive = ($this->records->config->getAppValue(Application::APP_ID, 'token_set', '') === $existing['id']);
		$this->records->customSets->delete(id: (string)$existing['id']);

		return $wasActive;
	}//end retire()

	/**
	 * Store the converted set through the upload path.
	 *
	 * @param array<string, mixed> $install The prepared install.
	 *
	 * @return array{id: string, warnings: array<int, array<string, mixed>>}
	 *
	 * @throws GalleryException 422 when the upload path refuses the set.
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
	 */
	private function storeInstall(array $install): array {
		try {
			return $this->records->customSets->store(set: new NewCustomTokenSet(fields: $this->storeArguments(install: $install)));
		} catch (RuntimeException $e) {
			throw new GalleryException(message: $e->getMessage(), code: 422, previous: $e);
		}
	}//end storeInstall()

	/**
	 * The named arguments {@see CustomTokenSetService::store()} gets for a prepared install.
	 *
	 * @param array<string, mixed> $install The prepared install.
	 *
	 * @return array<string, mixed>
	 */
	private function storeArguments(array $install): array {
		$converted = $install['prepared']['converted'];

		return [
			'displayName' => $install['name'],
			'description' => (string)$install['entry']['description'],
			'declarations' => $install['prepared']['accepted'],
			'version' => ($converted['manifestEntry']['upstreamVersion'] ?? null),
			'importWarnings' => ($converted['importWarnings'] ?? []),
			'css' => $converted['css'],
			'theming' => ($converted['manifestEntry']['theming'] ?? []),
			'logoAsset' => ($converted['logoAsset'] ?? null),
		];
	}//end storeArguments()

	/**
	 * Record where the stored set came from, make it active again on an update of the active set, and audit it.
	 *
	 * @param array<string, mixed> $install The prepared install.
	 * @param array{id: string, warnings: array<int, array<string, mixed>>} $stored The stored set.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
	 */
	private function recordInstall(array $install, array $stored): void {
		$this->recordProvenance(id: $stored['id'], provenance: $this->provenanceOf(install: $install));

		if ($install['wasActive'] === true) {
			$this->records->config->setAppValue(Application::APP_ID, 'token_set', $stored['id']);
		}

		$this->records->audit->log(action: 'custom_set_uploaded', context: $this->auditContext(install: $install, id: $stored['id']));
	}//end recordInstall()

	/**
	 * Where an installed set came from: gallery id, source, licence, checksum and install date.
	 *
	 * @param array<string, mixed> $install The prepared install.
	 *
	 * @return array<string, string>
	 */
	private function provenanceOf(array $install): array {
		$entry = $install['entry'];

		return [
			'galleryId' => $install['galleryId'],
			'sourceUrl' => (string)$entry['sourceUrl'],
			'licence' => (string)$entry['licence'],
			'sha256' => (string)$entry['sha256'],
			'installedOn' => gmdate('Y-m-d\TH:i:s\Z', $this->records->time->getTime()),
		];
	}//end provenanceOf()

	/**
	 * What the audit log records about an install.
	 *
	 * @param array<string, mixed> $install The prepared install.
	 * @param string $id The stored set id.
	 *
	 * @return array<string, mixed>
	 */
	private function auditContext(array $install, string $id): array {
		return [
			'id' => $id,
			'name' => $install['name'],
			'galleryId' => $install['galleryId'],
			'declarationCount' => (int)($install['prepared']['converted']['imported'] ?? 0),
			'contentHash' => 'sha256:' . substr((string)$install['entry']['sha256'], 0, 12),
		];
	}//end auditContext()

	/**
	 * Add where the set came from to its manifest entry, which the custom token sets list shows.
	 * Rewrites the stored file and entry verbatim through CustomTokenSetService::replace().
	 *
	 * @param string $id The stored custom set id.
	 * @param array<string, string> $provenance Gallery id, source, licence, checksum, install date.
	 *
	 * @return void
	 */
	private function recordProvenance(string $id, array $provenance): void {
		$entry = ($this->records->customSets->getManifest()[$id] ?? []);
		$css = $this->records->customSets->getRawContent(id: $id);
		if (is_array($entry) === false || $css === null) {
			return;
		}

		$entry['provenance'] = $provenance;
		$this->records->customSets->replace(id: $id, entry: $entry, css: $css);
	}//end recordProvenance()

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
			$response = $this->downloads->clientService->newClient()->get($url, ['timeout' => ThemeGalleryService::TIMEOUT_SECONDS]);
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
	 * @param string $name The display name the set is stored under.
	 * @param GalleryFile $file The downloaded file and its URL, whose file name tells the converter nothing it relies on.
	 *
	 * @return array{converted: array<string, mixed>, accepted: array<string, string>}
	 *
	 * @throws GalleryException 422 when the upload path refuses the file.
	 */
	private function prepare(string $name, GalleryFile $file): array {
		$slug = $this->records->customSets->slugify(name: $name);
		$converted = $this->converted(set: new GallerySetName(name: $name, slug: $slug), file: $file);
		$css = $this->checkedCss(converted: $converted);

		return ['converted' => $converted, 'accepted' => $this->acceptedDeclarations(css: $css, slug: $slug)];
	}//end prepare()

	/**
	 * The file converted into a token set.
	 *
	 * @param GallerySetName $set The set's name and slug.
	 * @param GalleryFile $file The verified file.
	 *
	 * @return array<string, mixed> The conversion.
	 *
	 * @throws GalleryException 422 when the converter refuses the file.
	 */
	private function converted(GallerySetName $set, GalleryFile $file): array {
		try {
			return $this->pipeline->converter->convert(
				content: $file->content,
				slug: $set->slug,
				displayName: $set->name,
				options: [
					'sourceName' => basename((string)parse_url($file->fileUrl, PHP_URL_PATH)),
					'assetName' => CustomTokenSetId::PREFIX . $set->slug,
				]
			);
		} catch (RuntimeException $e) {
			throw new GalleryException(message: $this->refused(reason: $e->getMessage()), code: 422, previous: $e);
		}
	}//end converted()

	/**
	 * The converted CSS, when it maps at least one token and holds no selector other than `:root`.
	 *
	 * @param array<string, mixed> $converted The conversion.
	 *
	 * @return string The CSS.
	 *
	 * @throws GalleryException 422 otherwise.
	 */
	private function checkedCss(array $converted): string {
		if ((int)($converted['imported'] ?? 0) === 0) {
			throw new GalleryException(message: $this->refused(reason: $this->l10n->t('nothing in it maps onto a Nextcloud token')), code: 422);
		}

		$css = (string)$converted['css'];
		if ($this->pipeline->validator->hasDisallowedSelector(css: $css) === true) {
			throw new GalleryException(message: $this->refused(reason: $this->l10n->t('it contains a selector other than :root')), code: 422);
		}

		return $css;
	}//end checkedCss()

	/**
	 * The declarations the custom set validator accepts.
	 *
	 * @param string $css The converted CSS.
	 * @param string $slug The set's slug.
	 *
	 * @return array<string, string> The accepted declarations.
	 *
	 * @throws GalleryException 422 when the validator refuses them.
	 */
	private function acceptedDeclarations(string $css, string $slug): array {
		$split = $this->pipeline->validator->validateDeclarations(declarations: $this->pipeline->cssParser->parseRootBlock(css: $css), slug: $slug);
		if ($split === null) {
			$error = $this->pipeline->validator->getLastError();
			throw new GalleryException(message: $this->refused(reason: (string)($error['message'] ?? '')), code: 422);
		}

		return $split['accepted'];
	}//end acceptedDeclarations()

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
