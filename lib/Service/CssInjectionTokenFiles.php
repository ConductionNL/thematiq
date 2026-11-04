<?php

/**
 * The files a token set layer is read from.
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
 * @spec openspec/specs/css-architecture/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;

/**
 * The stock tokens, the files thematiq wrote at runtime, the logo layer and the internal scopes.
 *
 * @spec openspec/specs/css-architecture/spec.md
 */
final class CssInjectionTokenFiles {

	/**
	 * Constructor.
	 *
	 * @param StockTokensService $stockTokens Resolves the `nextcloud` set from the running instance.
	 * @param RuntimeFileLocator $runtimeFiles Finds files thematiq wrote at runtime, which live in app data.
	 * @param LogoLayerService $logoLayer Resolves the active set's logo layer.
	 * @param InternalScopesService|null $internalScopes Builds the internal scopes; null builds one reading through the runtime files.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function __construct(
		public readonly StockTokensService $stockTokens,
		public readonly RuntimeFileLocator $runtimeFiles,
		public readonly LogoLayerService $logoLayer,
		public readonly ?InternalScopesService $internalScopes = null,
	) {
	}//end __construct()
}//end class
