<?php

/**
 * Every shipped token set as shell roles, with what the repository says about its logo and fonts.
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
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCA\Thematiq\Service\RuntimeFile\SetFileReader;

/**
 * Builds the shell catalogue: one entry per `css/tokens/<id>.css`, each with
 * the eighteen roles {@see ShellRoleMapper} gives the shell tokens endpoint,
 * and a statement about the set's logo and fonts that another product can act
 * on without reading this repository.
 *
 * It reads files only: no instance, no administrator's values, no uploads. The
 * same tree always gives the same catalogue.
 *
 * `redistributable` is the careful part. It is `true` only where this
 * repository itself says the file is ours or carries a licence that allows
 * passing it on, `false` only where the repository says it may not be, and
 * `null` everywhere else. The default licence in `REUSE.toml` is not such a
 * statement for an organisation's logo: it labels every file nobody claimed,
 * and nobody wrote down whose artwork a municipality's logo is.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
 */
class ShellCatalogueService {

	/**
	 * The version of the catalogue's shape.
	 *
	 * @var int
	 */
	public const VERSION = 1;

	/**
	 * Logos the repository says may not be passed on: set id => where it says so.
	 *
	 * The statement is only used while `quote` is still in `source`.
	 *
	 * @var array<string, array{source: string, quote: string, licence: string}>
	 */
	private const RESTRICTED_LOGOS = [
		'rijkshuisstijl' => [
			'source' => 'docs/reference/assets.md',
			'quote' => 'Gebruik hiervan is strikt verboden, behalve voor het ontwikkelen van websites en apps voor de Rijksoverheid',
			'licence' => 'Copyright Rijksoverheid: use is forbidden outside sites and apps for the Rijksoverheid',
		],
	];

	/**
	 * What a set's description says when the organisation is ours or made up.
	 *
	 * @var string
	 */
	private const OWN_SET_PATTERN = "/does not exist|Not a real organisation|Conduction's own brand/i";

	/**
	 * What a logo file says about itself when it is ours: a made-up mark, or a stand-in for an organisation's own.
	 *
	 * @var string
	 */
	private const OWN_FILE_PATTERN = '/Fictional example logo|logo placeholder/i';

	/**
	 * What a dark logo says when it was made from the light one.
	 *
	 * @var string
	 */
	private const DERIVED_PATTERN = '/Dark-surface variant of [\w.-]+, derived mechanically/';

	/**
	 * Constructor.
	 *
	 * @param ShippedTokenSetAuditService $declarations Resolves a set's declarations.
	 * @param ShellRoleMapper $mapper Maps them onto the eighteen roles.
	 * @param ShellCatalogueFonts $fonts Builds the font list.
	 * @param ReuseLicences $licences Reads `REUSE.toml`.
	 * @param ShellCatalogueVocabulary $vocabulary Picks a set's own vocabulary.
	 * @param SetFileReader $files Reads a set's files from the release or the store.
	 */
	public function __construct(
		private readonly ShippedTokenSetAuditService $declarations,
		private readonly ShellRoleMapper $mapper,
		private readonly ShellCatalogueFonts $fonts = new ShellCatalogueFonts(),
		private readonly ReuseLicences $licences = new ReuseLicences(),
		private readonly ShellCatalogueVocabulary $vocabulary = new ShellCatalogueVocabulary(),
		private readonly SetFileReader $files = new SetFileReader(),
	) {
	}//end __construct()

	/**
	 * The catalogue of every shipped set.
	 *
	 * `generated_from` is a digest of the sets and fonts below it, unless the
	 * caller names a source: the catalogue is committed, and a version or a
	 * commit written into it would make it stale on the next release.
	 *
	 * @param string $appPath The app root path.
	 * @param string|null $generatedFrom What the catalogue was made from, or null for the digest.
	 *
	 * The catalogue: `version`, `generated_from`, `sets` and `fonts`.
	 *
	 * @return array{version: int, generated_from: string, sets: array<int, array<string, mixed>>, fonts: array<int, array<string, mixed>>}
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
	 */
	public function build(string $appPath, ?string $generatedFrom = null): array {
		$evidence = new CatalogueEvidence(
			appPath: $appPath,
			manifest: $this->manifest(appPath: $appPath),
			annotations: $this->licences->annotations(appPath: $appPath)
		);

		$sets = $this->setsOf(evidence: $evidence);
		$fonts = $this->fonts->fontsOf(evidence: $evidence, families: $this->familiesOf(sets: $sets));
		$digest = 'sets:' . substr(hash('sha256', (string)json_encode([$sets, $fonts])), 0, 16);

		return [
			'version' => self::VERSION,
			'generated_from' => ($generatedFrom ?? $digest),
			'sets' => $sets,
			'fonts' => $fonts,
		];
	}//end build()

	/**
	 * The ids of the shipped sets: one per `css/tokens/<id>.css`, sorted.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return string[] The ids.
	 */
	private function shippedIds(string $appPath): array {
		$ids = [];
		$files = glob($appPath . '/css/tokens/*.css');
		if ($files === false) {
			$files = [];
		}

		foreach ($files as $file) {
			$ids[] = basename($file, '.css');
		}

		sort($ids, SORT_STRING);

		return $ids;
	}//end shippedIds()

	/**
	 * The catalogue's sets, in id order, each with its logo block.
	 *
	 * @param CatalogueEvidence $evidence The app root, the manifest and the `REUSE.toml` annotations.
	 *
	 * @return array<int, array<string, mixed>> The sets.
	 */
	private function setsOf(CatalogueEvidence $evidence): array {
		$sets = [];
		foreach ($this->shippedIds(appPath: $evidence->appPath) as $id) {
			$name = $this->nameOf(id: $id, meta: $evidence->metaOf(id: $id));
			$set = $this->entry(appPath: $evidence->appPath, id: $id, name: $name, store: null);
			$logo = ['logo' => $this->logoOf(evidence: $evidence, id: $id)];
			$sets[] = (array_slice($set, 0, 3, true) + $logo + array_slice($set, 3, null, true));
		}

		return $sets;
	}//end setsOf()

	/**
	 * The font families the sets name, each with the ids of the sets that name it.
	 *
	 * @param array<int, array<string, mixed>> $sets The catalogue's sets.
	 *
	 * @return array<string, string[]> Family => set ids, in set order.
	 */
	private function familiesOf(array $sets): array {
		$families = [];
		foreach ($sets as $set) {
			foreach ($this->fonts->familiesIn(stack: $set['tokens']['font-family']) as $family) {
				$families[$family][] = $set['id'];
			}
		}

		return $families;
	}//end familiesOf()

	/**
	 * One set in the shape the catalogue and the OpenProject plugin's set file share:
	 * `id`, `name`, the eighteen `tokens`, and the optional `vocabulary` and `dark`. The
	 * caller adds the `logo`.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The set id.
	 * @param string $name The set's name.
	 * @param RuntimeFileStore|null $store Where uploaded sets live; null for the release only.
	 *
	 * @return array<string, mixed> The entry.
	 *
	 * @spec openspec/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
	 */
	public function entry(string $appPath, string $id, string $name, ?RuntimeFileStore $store): array {
		$light = $this->declarations->resolveDeclarations(appPath: $appPath, id: $id, theming: []);
		$tokens = $this->mapper->map(tokens: $light);
		$set = [
			'id' => $id,
			'name' => $name,
			'tokens' => $tokens,
		];

		// Optional: the set's own tokens beyond the roles, so another product can carry them.
		$css = (string)$this->files->read(appPath: $appPath, name: 'css/tokens/' . $id . '.css', store: $store);
		$vocabulary = $this->vocabulary->ofSet(css: $css, resolved: $light);
		if ($vocabulary !== []) {
			$set['vocabulary'] = $vocabulary;
		}

		// Optional: only a set that ships a dark variant has dark roles.
		$dark = $this->darkDeclarations(appPath: $appPath, id: $id, store: $store);
		if ($dark !== []) {
			// A set that names no page background draws on Nextcloud's own,
			// which is white in light and dark in dark. The light
			// declarations carry the white; a dark page must not inherit it.
			if (isset($dark['--nldesign-color-background']) === false) {
				$dark['--nldesign-color-background'] = DarkPaletteService::NEXTCLOUD_DARK_MAIN_BACKGROUND;
			}

			$set['dark'] = ['tokens' => $this->mapper->mapDark(tokens: array_merge($light, $dark))];

			// The dark variant's own values for the vocabulary, as its file states them.
			$darkVocabulary = $this->vocabulary->ofDeclarations(declarations: $dark);
			if ($darkVocabulary !== []) {
				$set['dark']['vocabulary'] = $darkVocabulary;
			}
		}

		return $set;
	}//end entry()

	/**
	 * The catalogue as the file that is committed: pretty printed, one trailing newline.
	 *
	 * @param array<string, mixed> $catalogue The catalogue.
	 *
	 * @return string The JSON.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-the-catalogue-is-reproducible
	 */
	public function render(array $catalogue): string {
		return json_encode($catalogue, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . "\n";
	}//end render()

	/**
	 * The repository-relative files another product may copy: the logos marked redistributable.
	 *
	 * @param array<string, mixed> $catalogue The catalogue.
	 *
	 * @return string[] The paths, sorted.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
	 */
	public function redistributableLogos(array $catalogue): array {
		$files = [];
		foreach ($catalogue['sets'] as $set) {
			if ($set['logo']['redistributable'] !== true) {
				continue;
			}

			foreach ([$set['logo']['file'], $set['logo']['inverse_file']] as $file) {
				if ($file !== null) {
					$files[] = $file;
				}
			}
		}

		sort($files, SORT_STRING);

		return $files;
	}//end redistributableLogos()

	/**
	 * What a set's dark variant declares, or nothing when it ships none.
	 *
	 * Read from the committed `css/tokens/dark/<id>.css`, the file a dark
	 * page gets: {@see DarkPaletteService} wrote it (or, for the high contrast
	 * set, a person did), so these are thematiq's own dark values and no
	 * second derivation. The file states its declarations twice, for the
	 * system preference and for Nextcloud's explicit dark theme; the explicit
	 * block is read. A token the dark variant leaves out keeps its light
	 * value, as it does in the browser.
	 *
	 * @param string $appPath The app root path.
	 * @param string $id The set id.
	 * @param RuntimeFileStore|null $store Where uploaded sets live.
	 *
	 * @return array<string, string> Declaration name => dark value.
	 */
	private function darkDeclarations(string $appPath, string $id, ?RuntimeFileStore $store): array {
		$css = (string)$this->files->read(appPath: $appPath, name: 'css/tokens/dark/' . $id . '.css', store: $store);
		$start = strpos($css, 'body[data-theme-dark]');
		if ($start === false) {
			return [];
		}

		$open = strpos($css, '{', $start);
		$close = strpos($css, '}', (int)$open);
		if ($open === false || $close === false) {
			return [];
		}

		preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', substr($css, ($open + 1), ($close - $open - 1)), $matches, PREG_SET_ORDER);

		$declared = [];
		foreach ($matches as $match) {
			$declared[$match[1]] = trim($match[2]);
		}

		return $declared;
	}//end darkDeclarations()

	/**
	 * The entries of `token-sets.json`, by id.
	 *
	 * @param string $appPath The app root path.
	 *
	 * @return array<string, array<string, mixed>> The entries.
	 */
	private function manifest(string $appPath): array {
		$decoded = json_decode($this->licences->read(path: $appPath . '/token-sets.json'), true);
		if (is_array($decoded) === false) {
			return [];
		}

		$entries = [];
		foreach ($decoded as $entry) {
			if (is_array($entry) === true && is_string(($entry['id'] ?? null)) === true) {
				$entries[$entry['id']] = $entry;
			}
		}

		return $entries;
	}//end manifest()

	/**
	 * A set's display name: the manifest's, else the id spelled out, as the endpoint does.
	 *
	 * @param string $id The set id.
	 * @param array<string, mixed> $meta The set's manifest entry.
	 *
	 * @return string The name.
	 */
	private function nameOf(string $id, array $meta): string {
		$name = ($meta['name'] ?? null);
		if (is_string($name) === true && $name !== '') {
			return $name;
		}

		return ucwords(str_replace('-', ' ', $id));
	}//end nameOf()

	/**
	 * The logo block of one set.
	 *
	 * @param CatalogueEvidence $evidence The app root, the set's manifest entry and the `REUSE.toml` annotations.
	 * @param string $id The set id.
	 *
	 * @return array{file: string|null, inverse_file: string|null, licence: string|null, redistributable: bool|null, basis: string|null} The block.
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-every-shipped-set-is-in-the-catalogue
	 */
	public function logoOf(CatalogueEvidence $evidence, string $id): array {
		$logo = new CatalogueLogo(
			id: $id,
			file: $this->logoFile(appPath: $evidence->appPath, name: $id),
			inverse: $this->logoFile(appPath: $evidence->appPath, name: $id . '-dark')
		);
		if ($logo->file === null) {
			return ['file' => null, 'inverse_file' => $logo->inverse, 'licence' => null, 'redistributable' => null, 'basis' => null];
		}

		return $this->logoBlock(evidence: $evidence, logo: $logo);
	}//end logoOf()

	/**
	 * The logo block of a set that has a light logo.
	 *
	 * @param CatalogueEvidence $evidence The app root, the set's manifest entry and the `REUSE.toml` annotations.
	 * @param CatalogueLogo $logo The set's logo files; the light one is there.
	 *
	 * @return array{file: string|null, inverse_file: string|null, licence: string|null, redistributable: bool|null, basis: string|null} The block.
	 */
	private function logoBlock(CatalogueEvidence $evidence, CatalogueLogo $logo): array {
		$verdict = $this->logoVerdict(evidence: $evidence, logo: $logo);

		// A dark logo stands with the light one when it says it was made from
		// it, or when the reason holds for the whole set. A reason read from
		// the light file alone does not cover a second drawing.
		if ($logo->inverse !== null && $verdict['redistributable'] === true && $verdict['scope'] === 'file'
			&& $this->inverseSaysNothing(appPath: $evidence->appPath, inverse: $logo->inverse) === true
		) {
			$verdict = $this->unclaimedInverse(licence: $verdict['licence']);
		}

		return [
			'file' => $logo->file,
			'inverse_file' => $logo->inverse,
			'licence' => $verdict['licence'],
			'redistributable' => $verdict['redistributable'],
			'basis' => $verdict['basis'],
		];
	}//end logoBlock()

	/**
	 * The verdict for a set whose light logo says it is ours and whose dark logo says nothing about itself.
	 *
	 * @param string|null $licence The licence of the light logo.
	 *
	 * @return array{licence: string|null, redistributable: null, basis: string} The verdict.
	 */
	private function unclaimedInverse(?string $licence): array {
		return [
			'licence' => $licence,
			'redistributable' => null,
			'basis' => 'The light logo says it is ours; the dark logo says nothing about itself.',
		];
	}//end unclaimedInverse()

	/**
	 * Whether a dark logo says neither that it was made from the light one nor that it is ours.
	 *
	 * @param string $appPath The app root path.
	 * @param string $inverse The dark logo's repository-relative path.
	 *
	 * @return bool True when the file says nothing about itself.
	 */
	private function inverseSaysNothing(string $appPath, string $inverse): bool {
		return preg_match(self::DERIVED_PATTERN, (string)file_get_contents($appPath . '/' . $inverse)) !== 1
			&& preg_match(self::OWN_FILE_PATTERN, (string)file_get_contents($appPath . '/' . $inverse)) !== 1;
	}//end inverseSaysNothing()

	/**
	 * What the repository says about one set's logo.
	 *
	 * @param CatalogueEvidence $evidence The app root, the set's manifest entry and the `REUSE.toml` annotations.
	 * @param CatalogueLogo $logo The set's logo files; the light one is there.
	 *
	 * @return array{licence: string|null, redistributable: bool|null, basis: string, scope?: string} The verdict.
	 */
	private function logoVerdict(CatalogueEvidence $evidence, CatalogueLogo $logo): array {
		$restricted = $this->restrictedLogo(evidence: $evidence, id: $logo->id);
		if ($restricted !== null) {
			return $restricted;
		}

		$file = (string)$logo->file;
		$licence = $this->licences->licenceOf(file: $file, annotations: $evidence->annotations)['licence'];
		if ($licence === null) {
			return $this->unclaimedLogo(licence: null);
		}

		return ($this->ownSetLogo(meta: $evidence->metaOf(id: $logo->id), licence: $licence)
			?? $this->ownFileLogo(contents: (string)file_get_contents($evidence->appPath . '/' . $file), licence: $licence)
			?? $this->unclaimedLogo(licence: $licence));
	}//end logoVerdict()

	/**
	 * The verdict for a logo the repository says may not be passed on, while it still says so.
	 *
	 * @param CatalogueEvidence $evidence The app root, the set's manifest entry and the `REUSE.toml` annotations.
	 * @param string $id The set id.
	 *
	 * @return array{licence: string, redistributable: bool, basis: string}|null The verdict, or null when it is not restricted.
	 */
	private function restrictedLogo(CatalogueEvidence $evidence, string $id): ?array {
		$restricted = (self::RESTRICTED_LOGOS[$id] ?? null);
		if ($restricted === null || str_contains($this->licences->read(path: $evidence->appPath . '/' . $restricted['source']), $restricted['quote']) === false) {
			return null;
		}

		return [
			'licence' => $restricted['licence'],
			'redistributable' => false,
			'basis' => $restricted['source'] . ': "' . $restricted['quote'] . '"',
		];
	}//end restrictedLogo()

	/**
	 * The verdict for a logo of a set `token-sets.json` describes as ours or made up.
	 *
	 * @param array<string, mixed> $meta The set's manifest entry.
	 * @param string $licence The licence `REUSE.toml` gives the file.
	 *
	 * @return array{licence: string, redistributable: bool, basis: string, scope: string}|null The verdict, or null when the description says no such thing.
	 */
	private function ownSetLogo(array $meta, string $licence): ?array {
		if (preg_match(self::OWN_SET_PATTERN, (string)($meta['description'] ?? ''), $match) !== 1) {
			return null;
		}

		return [
			'licence' => $licence,
			'redistributable' => true,
			'basis' => 'token-sets.json describes the set as ours or made up ("' . $match[0] . '"); REUSE.toml: ' . $licence . '.',
			'scope' => 'set',
		];
	}//end ownSetLogo()

	/**
	 * The verdict for a logo file that says it is our own drawing.
	 *
	 * @param string $contents The logo file's contents.
	 * @param string $licence The licence `REUSE.toml` gives the file.
	 *
	 * @return array{licence: string, redistributable: bool, basis: string, scope: string}|null The verdict, or null when the file says no such thing.
	 */
	private function ownFileLogo(string $contents, string $licence): ?array {
		if (preg_match(self::OWN_FILE_PATTERN, $contents, $match) !== 1) {
			return null;
		}

		return [
			'licence' => $licence,
			'redistributable' => true,
			'basis' => 'The file says it is our own drawing ("' . $match[0] . '"), not the organisation\'s artwork; REUSE.toml: ' . $licence . '.',
			'scope' => 'file',
		];
	}//end ownFileLogo()

	/**
	 * The verdict for a logo nothing in the repository makes a statement about.
	 *
	 * @param string|null $licence The licence `REUSE.toml` gives the file, or null.
	 *
	 * @return array{licence: string|null, redistributable: null, basis: string} The verdict.
	 */
	private function unclaimedLogo(?string $licence): array {
		$stated = null;
		if ($licence !== null) {
			$stated = $licence . ' by the repository default in REUSE.toml; the holder of the artwork is not stated';
		}

		return [
			'licence' => $stated,
			'redistributable' => null,
			'basis' => 'Nothing in the repository says whose artwork this is or that it may be passed on.',
		];
	}//end unclaimedLogo()

	/**
	 * The first `img/logos/<name>.<ext>` there is, in the order the header draws it.
	 *
	 * @param string $appPath The app root path.
	 * @param string $name The file name without extension.
	 *
	 * @return string|null The repository-relative path, or null.
	 */
	private function logoFile(string $appPath, string $name): ?string {
		foreach (LogoLayerService::LOGO_EXTENSIONS as $extension) {
			$candidate = 'img/logos/' . $name . '.' . $extension;
			if (is_file($appPath . '/' . $candidate) === true) {
				return $candidate;
			}
		}

		return null;
	}//end logoFile()

}//end class
