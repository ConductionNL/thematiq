<?php

/**
 * Thematiq email logo address.
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
 * @spec openspec/specs/email-theming/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use OCP\IURLGenerator;

/**
 * The absolute address a mail client loads a token set's logo from.
 *
 * @spec openspec/specs/email-theming/spec.md
 */
final class EmailLogoAddress {

	/**
	 * Constructor.
	 *
	 * @param IURLGenerator $urlGenerator Builds absolute URLs.
	 * @param RuntimeFileLocator|null $files Finds an uploaded set's logo, which lives in app data.
	 */
	public function __construct(
		private readonly IURLGenerator $urlGenerator,
		private readonly RuntimeFileLocator|null $files,
	) {
	}//end __construct()

	/**
	 * Resolve a token set's logo path to an absolute URL.
	 *
	 * @param string|null $logoPath The logo path from token-sets.json (e.g. 'img/logos/amsterdam.svg').
	 *
	 * @return string|null The absolute URL, or null when unresolvable.
	 *
	 * @spec openspec/specs/email-theming/spec.md
	 */
	public function urlOf(string|null $logoPath): string|null {
		if ($logoPath === null || $logoPath === '') {
			return null;
		}

		// An uploaded or captured logo lives in app data and is served by the
		// runtime file route; a mail client needs that URL absolute.
		if ($this->files !== null && $this->files->isShipped(name: $logoPath) === false) {
			return $this->runtimeUrl(files: $this->files, logoPath: $logoPath);
		}

		return $this->shippedUrl(logoPath: $logoPath);
	}//end urlOf()

	/**
	 * The absolute address of an uploaded or captured logo.
	 *
	 * @param RuntimeFileLocator $files The runtime file locator.
	 * @param string $logoPath The stored logo path.
	 *
	 * @return string|null The absolute URL, or null when the file is not there.
	 */
	private function runtimeUrl(RuntimeFileLocator $files, string $logoPath): string|null {
		$url = $files->url(name: $logoPath);
		if ($url === null) {
			return null;
		}

		return $this->urlGenerator->getAbsoluteURL($url);
	}//end runtimeUrl()

	/**
	 * The absolute address of a logo the app ships.
	 *
	 * @param string $logoPath The stored logo path (e.g. 'img/logos/amsterdam.svg').
	 *
	 * @return string|null The absolute URL, or null when it cannot be built.
	 */
	private function shippedUrl(string $logoPath): string|null {
		// IURLGenerator::imagePath() resolves relative to the app's img/
		// directory itself, so a stored path already prefixed with 'img/'
		// must have that prefix stripped before being passed in.
		$relative = $logoPath;
		if (str_starts_with($relative, 'img/') === true) {
			$relative = substr($relative, 4);
		}

		try {
			return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, $relative));
		} catch (\Throwable $e) {
			return null;
		}
	}//end shippedUrl()
}//end class
