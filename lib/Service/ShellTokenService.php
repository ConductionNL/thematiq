<?php

/**
 * The active token set as the shell tokens document.
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
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCP\IConfig;
use OCP\IURLGenerator;
use Throwable;

/**
 * Puts together what `GET /api/shell-tokens` answers: which set is active,
 * its eighteen shell roles, its logo, and a revision that moves with any of
 * them.
 *
 * It resolves nothing itself. The declarations come from
 * {@see ShippedTokenSetAuditService::resolveDeclarations()} (defaults, then
 * the set, `var()` chains resolved), the stock set's live values from
 * {@see StockTokensService}, the administrator's own values from
 * {@see CustomOverridesService}, and the roles from {@see ShellRoleMapper}.
 *
 * The document holds theme values only: no user, no group, no setting.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */
class ShellTokenService {

	/**
	 * The version of the document's shape.
	 *
	 * @var int
	 */
	public const VERSION = 1;

	/**
	 * Constructor.
	 *
	 * @param ActiveTokenSetService $activeSet Says which set is active.
	 * @param TokenSetService $tokenSets Says whether that set still exists, and where the app is.
	 * @param DesignSystemService $designSystems The set's name.
	 * @param ShippedTokenSetAuditService $declarations Resolves the set's declarations.
	 * @param StockTokensService $stockTokens The stock set's values on this instance.
	 * @param CustomOverridesService $overrides The administrator's own values.
	 * @param ShellRoleMapper $mapper Maps the declarations onto the roles.
	 * @param LogoLayerService $logos Finds the set's logo files.
	 * @param RuntimeFileLocator $files Turns a logo file into its URL.
	 * @param IConfig $config Reads whether Nextcloud theming holds an uploaded logo.
	 * @param IURLGenerator $urlGenerator Makes URLs absolute.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) - each collaborator owns one part of the document, and none is resolved twice.
	 */
	public function __construct(
		private readonly ActiveTokenSetService $activeSet,
		private readonly TokenSetService $tokenSets,
		private readonly DesignSystemService $designSystems,
		private readonly ShippedTokenSetAuditService $declarations,
		private readonly StockTokensService $stockTokens,
		private readonly CustomOverridesService $overrides,
		private readonly ShellRoleMapper $mapper,
		private readonly LogoLayerService $logos,
		private readonly RuntimeFileLocator $files,
		private readonly IConfig $config,
		private readonly IURLGenerator $urlGenerator,
	) {
	}//end __construct()

	/**
	 * The shell tokens document of the active set.
	 *
	 * When the configured set no longer exists (an uploaded set that was
	 * removed), the document describes the stock `nextcloud` set, which is
	 * what the instance then wears.
	 *
	 * The document: `version`, `id`, `name`, `revision`, `tokens` (role => value) and `logo` (`url`, `inverse_url`).
	 *
	 * @return array{version: int, id: string, name: string, revision: string, tokens: array<string, string>, logo: array<string, string|null>}
	 *
	 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
	 */
	public function document(): array {
		$id = $this->activeSet->getActive();
		if ($this->tokenSets->isValidTokenSet(tokenSetId: $id) === false) {
			$id = ActiveTokenSetService::DEFAULT_SET;
		}

		$meta = $this->designSystems->getTokenSetMeta(tokenSetId: $id);
		$name = ($meta['name'] ?? null);
		if (is_string($name) === false || $name === '') {
			$name = ucwords(str_replace('-', ' ', $id));
		}

		$document = [
			'version' => self::VERSION,
			'id' => $id,
			'name' => $name,
			'tokens' => $this->mapper->map(tokens: $this->declarationsOf(id: $id), overrides: $this->overrides->read(tokenSet: $id)),
			'logo' => $this->logoOf(id: $id),
		];

		// Everything a consumer can read is in the hash, so the revision moves
		// when any of it does and stays put when none of it does.
		$revision = substr(hash('sha256', (string)json_encode($document)), 0, 16);

		return [
			'version' => $document['version'],
			'id' => $document['id'],
			'name' => $document['name'],
			'revision' => $revision,
			'tokens' => $document['tokens'],
			'logo' => $document['logo'],
		];
	}//end document()

	/**
	 * The declarations a set paints with.
	 *
	 * No theming block is passed on purpose: with one, a set that declares no
	 * page background would get its Nextcloud LOGIN background as the page
	 * colour (vng's is a saturated blue). Without it the page is white, which
	 * is what Nextcloud draws.
	 *
	 * The stock set is read from the running instance, as the page itself
	 * does: its shipped file is a snapshot of one Nextcloud version.
	 *
	 * @param string $id The token set id.
	 *
	 * @return array<string, string> Declaration name => resolved value.
	 */
	private function declarationsOf(string $id): array {
		$declared = $this->declarations->resolveDeclarations(appPath: $this->tokenSets->getAppPath(), id: $id, theming: []);
		if ($id === ActiveTokenSetService::DEFAULT_SET) {
			$declared = array_merge($declared, $this->stockTokens->getTokens());
		}

		return $declared;
	}//end declarationsOf()

	/**
	 * The logo of a set, as absolute URLs.
	 *
	 * `url` is the set's own logo, or the logo an administrator uploaded in
	 * Nextcloud's theming settings when the set brings none: the same order
	 * {@see LogoLayerService::layer()} draws the header in. `inverse_url` is
	 * the logo the set draws on a dark surface (`<id>-dark`). Null where there
	 * is none; the stock Nextcloud mark is not offered as anyone's logo.
	 *
	 * @param string $id The token set id.
	 *
	 * @return array{url: string|null, inverse_url: string|null} The two URLs.
	 */
	private function logoOf(string $id): array {
		$logo = $this->absolute(path: $this->fileUrl(name: $this->logos->shippedLogo(name: $id)));
		if ($logo === null) {
			$logo = $this->uploadedLogo();
		}

		return [
			'url' => $logo,
			'inverse_url' => $this->absolute(path: $this->fileUrl(name: $this->logos->shippedLogo(name: $id . '-dark'))),
		];
	}//end logoOf()

	/**
	 * The URL a logo file is served from.
	 *
	 * @param string|null $name The app-relative file name, or null.
	 *
	 * @return string|null The URL, or null without a file.
	 */
	private function fileUrl(?string $name): ?string {
		if ($name === null) {
			return null;
		}

		return $this->files->url(name: $name);
	}//end fileUrl()

	/**
	 * The logo uploaded in Nextcloud's own theming settings, when there is one.
	 *
	 * Built the way the theming app builds it (`ImageManager::getImageUrl()`),
	 * from the same two config keys {@see LogoLayerService::layer()} reads.
	 *
	 * @return string|null The absolute URL, or null when no logo was uploaded.
	 */
	private function uploadedLogo(): ?string {
		foreach (['logoheader', 'logo'] as $key) {
			if ($this->config->getAppValue('theming', $key . 'Mime', '') === '') {
				continue;
			}

			try {
				$path = $this->urlGenerator->linkToRoute('theming.Theming.getImage', ['key' => $key])
					. '?v=' . $this->config->getAppValue('theming', 'cachebuster', '0');
			} catch (Throwable) {
				// The theming app is off: its uploads are not served either.
				return null;
			}

			return $this->absolute(path: $path);
		}

		return null;
	}//end uploadedLogo()

	/**
	 * A URL with its scheme and host.
	 *
	 * @param string|null $path A URL path, or null.
	 *
	 * @return string|null The absolute URL, or null without a path.
	 */
	private function absolute(?string $path): ?string {
		if ($path === null || $path === '') {
			return null;
		}

		return $this->urlGenerator->getAbsoluteURL($path);
	}//end absolute()
}//end class
