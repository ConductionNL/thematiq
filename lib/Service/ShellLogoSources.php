<?php

/**
 * The collaborators that find a token set's logo and its URL.
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

/**
 * The set's logo files, the runtime file URLs, the uploaded theming logo and absolute URLs.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
 */
final class ShellLogoSources {

	/**
	 * Constructor.
	 *
	 * @param LogoLayerService $logos Finds the set's logo files.
	 * @param RuntimeFileLocator $files Turns a logo file into its URL.
	 * @param IConfig $config Reads whether Nextcloud theming holds an uploaded logo.
	 * @param IURLGenerator $urlGenerator Makes URLs absolute.
	 *
	 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-another-product-reads-the-active-shell-tokens
	 */
	public function __construct(
		public readonly LogoLayerService $logos,
		public readonly RuntimeFileLocator $files,
		public readonly IConfig $config,
		public readonly IURLGenerator $urlGenerator,
	) {
	}//end __construct()
}//end class
