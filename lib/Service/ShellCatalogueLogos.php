<?php

/**
 * The logo of each shipped token set, and what the repository says about it.
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
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

/**
 * The logo block of the shell catalogue.
 *
 * `redistributable` is `true` only where this repository itself says the
 * file is ours, `false` only where it says it may not be passed on, and
 * `null` everywhere else.
 *
 * @spec openspec/specs/shell-catalogue/spec.md#requirement-a-logo-is-redistributable-only-when-the-repository-says-so
 */
final class ShellCatalogueLogos {

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
	 * @param ReuseLicences $licences Reads `REUSE.toml` and the repository's statements.
	 */
	public function __construct(
		private readonly ReuseLicences $licences,
	) {
	}//end __construct()

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
	 * @return array{licence: string, redistributable: bool, basis: string}|null Null when not restricted.
	 */
	private function restrictedLogo(CatalogueEvidence $evidence, string $id): ?array {
		$restricted = (self::RESTRICTED_LOGOS[$id] ?? null);
		if ($restricted === null || $this->licences->stillSays(appPath: $evidence->appPath, statement: $restricted) === false) {
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
	 * @return array{licence: string, redistributable: bool, basis: string, scope: string}|null Null when the description says not.
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
