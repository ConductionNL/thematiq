<?php

/**
 * The single entries of a token set's stylesheet layers.
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

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileLocator;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Builds one layer entry each: a stylesheet file (shipped, or a runtime file
 * linked by route), the stock set's tokens resolved from the instance, and
 * the internal scopes. {@see TokenSetLayers} puts them in cascade order.
 *
 * @spec openspec/specs/css-architecture/spec.md
 */
class TokenSetLayerEntries {

	/**
	 * The `id` the resolved stock-tokens `<style>` carries on the page.
	 *
	 * An inline block has no href to be found by, so the client replaces it
	 * by id when the set changes.
	 *
	 * @var string
	 */
	public const string STOCK_TOKENS_STYLE_ID = 'nldesign-stock-tokens';

	/**
	 * The id of the inline `<style>` carrying the internal scopes.
	 *
	 * @var string
	 */
	public const string INTERNAL_SCOPES_STYLE_ID = 'thematiq-internal-scopes';

	/**
	 * Constructor.
	 *
	 * @param RuntimeFileLocator $runtimeFiles Finds files thematiq wrote at runtime, which live in app data.
	 * @param StockTokensService $stockTokens Resolves the `nextcloud` set from the running instance.
	 * @param InternalScopesService $internalScopes Builds the rules that carry a set's internal tokens onto their components.
	 * @param LoggerInterface $logger Records a skipped internal scopes layer.
	 */
	public function __construct(
		private readonly RuntimeFileLocator $runtimeFiles,
		private readonly StockTokensService $stockTokens,
		private readonly InternalScopesService $internalScopes,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * A stylesheet layer: a shipped static file, or a runtime file linked by route.
	 *
	 * @param string $layer The layer name.
	 * @param string $file The stylesheet path relative to `css/`, without extension.
	 *
	 * @return array{layer: string, kind: string, file?: string, name?: string} The layer entry.
	 *
	 * @spec openspec/specs/runtime-file-storage/spec.md
	 */
	public function file(string $layer, string $file): array {
		$name = 'css/' . $file . '.css';
		if ($this->runtimeFiles->inStore(name: $name) === true) {
			return ['layer' => $layer, 'kind' => 'runtime', 'name' => $name];
		}

		return ['layer' => $layer, 'kind' => 'file', 'file' => $file];
	}//end file()

	/**
	 * The `nextcloud` set's token layer, resolved from the running instance.
	 *
	 * The `nextcloud` set is the one set whose values belong to something
	 * else: it exists to reproduce the appearance of the running instance, and
	 * that appearance changes with every Nextcloud release. A shipped file can
	 * only ever hold a snapshot of one version, so it is resolved from the
	 * instance instead. See StockTokensService for the measurements and for
	 * why a stylesheet cannot do this with var().
	 *
	 * @return array{layer: string, kind: string, css: string, id: string}|null The inline
	 *                                                                          layer, or null when the instance could not be read.
	 *
	 * @spec openspec/changes/component-playground/specs/nextcloud-variable-mapping/spec.md
	 */
	public function stockTokens(): ?array {
		$css = $this->stockTokens->getCss();
		if ($css === null) {
			return null;
		}

		return ['layer' => 'tokens', 'kind' => 'inline', 'css' => $css, 'id' => self::STOCK_TOKENS_STYLE_ID];
	}//end stockTokens()

	/**
	 * The internal scopes as an inline layer, after the component scopes, or
	 * nothing when the set and the overrides give no internal token a value.
	 *
	 * Its own failure degrades only itself: the set's other layers still emit.
	 *
	 * @param string $tokenSet The token set.
	 * @param array{designSystemId: string, withDark: bool} $cascade The design system the set wears, and whether the set's
	 *                                                               dark variant is part of the cascade.
	 *
	 * @return array<int, array{layer: string, kind: string, css: string, id: string}> Zero or one entry.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public function internalScopes(string $tokenSet, array $cascade): array {
		try {
			$css = $this->internalScopes->forSet(tokenSet: $tokenSet, designSystemId: $cascade['designSystemId'], withDark: $cascade['withDark']);
		} catch (Throwable $e) {
			$this->logger->warning(
				'thematiq: the internal scopes layer was skipped; the rest of the cascade still ran.',
				['app' => Application::APP_ID, 'exception' => $e]
			);
			return [];
		}

		if ($css === '') {
			return [];
		}

		return [['layer' => 'internal-scopes', 'kind' => 'inline', 'css' => $css, 'id' => self::INTERNAL_SCOPES_STYLE_ID]];
	}//end internalScopes()
}//end class
