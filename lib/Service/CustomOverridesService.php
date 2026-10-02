<?php

/**
 * NL Design Custom Overrides Service.
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
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-8
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-28
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-29
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-30
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-31
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-32
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-33
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-34
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\RuntimeFile\RuntimeFileStore;
use OCP\IConfig;
use RuntimeException;

/**
 * Service for reading and writing the custom-overrides.css file.
 *
 * This service is the sole write path for user-defined token customizations.
 * It validates all token names against the TokenRegistry before writing.
 *
 * The CSS file format is strictly controlled:
 * - One :root {} block with the light values, read back by read()
 * - For every brand-layer colour override, the two dark scopes of the generated dark
 *   stylesheets with its derived dark value, so a user who chose the dark
 *   theme (whose colours Nextcloud declares on body) and a user whose system
 *   is dark see the same colour
 * - One declaration per line
 * - Each declaration carries !important so user overrides win the cascade over
 *   the nldesign design-system stylesheets and Nextcloud core theming
 * - No selectors other than :root and the two dark scopes
 *
 * A SET ON NO DESIGN SYSTEM NEVER READS THE SHARED FILE.
 *
 * `css/custom-overrides.css` holds the edits made on every set that wears a
 * design system. A set on `none` — the stock `nextcloud` set, and every
 * theme saved off it — keeps its edits in a file of its own,
 * `css/custom-overrides-{set}.css`, and only that file is loaded while it is
 * the set on the page. Such a set is Nextcloud plus its own values and
 * nothing else: the stock set loads no token file at all, so emptying its
 * edit file — the reset — gives stock back.
 *
 * Before the split there was one file, loaded whatever the set. A value an
 * admin pinned while on some other theme stayed on the page after switching
 * back to "Nextcloud", so the stock set was not stock, and every theme saved
 * off it wore the other themes' pinned values too.
 *
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-8
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-28
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-29
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-30
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-31
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-32
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-33
 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-34
 */
class CustomOverridesService {

	/**
	 * The CSS file header comment.
	 *
	 * @var string
	 */
	private const CSS_HEADER = OverridesCssBuilder::CSS_HEADER;

	/**
	 * The overrides file of every set that wears a design system (under `css/`, no extension).
	 *
	 * @var string
	 */
	public const FILE = 'custom-overrides';

	/**
	 * Where the overrides files are stored: app data, never the app directory.
	 *
	 * @var RuntimeFileStore
	 */
	private RuntimeFileStore $store;

	/**
	 * The CSS parser service (shared :root-block parsing, avoids duplication).
	 *
	 * @var CssParserService
	 */
	private CssParserService $cssParser;

	/**
	 * Builds the file content, deriving each colour override's dark value exactly
	 * as the generated dark stylesheets do.
	 *
	 * @var OverridesCssBuilder
	 */
	private OverridesCssBuilder $css;

	/**
	 * The administrator's own tokens, rendered after the editor's.
	 *
	 * @var OwnTokenService|null
	 */
	private ?OwnTokenService $ownTokens;

	/**
	 * The app config, for the active set when a caller names none.
	 *
	 * @var IConfig|null
	 */
	private ?IConfig $config;

	/**
	 * Which design system a set wears, for choosing its file.
	 *
	 * @var DesignSystemService|null
	 */
	private ?DesignSystemService $designSystems;

	/**
	 * The value grammar per token type.
	 *
	 * @var TokenValueValidator
	 */
	private TokenValueValidator $values;

	/**
	 * The motion tokens the editor sets, and the thematiq name each one also writes, so both
	 * Nextcloud's components and anything reading the thematiq name follow the same speed.
	 *
	 * @var array<string, string>
	 */
	public const MOTION_TWINS = OverridesCssBuilder::MOTION_TWINS;

	/**
	 * Constructor.
	 *
	 * @param RuntimeFileStore $store Where the overrides files are stored.
	 * @param CssParserService $cssParser CSS parser for :root block extraction.
	 * @param DarkPaletteService $darkPalette Derives each colour override's dark value.
	 * @param IConfig|null $config The app config. With the next one, what picks a set's own file;
	 *                             without them every set reads the shared file.
	 * @param DesignSystemService|null $designSystems The token set metadata.
	 * @param TokenValueValidator|null $values The value grammar per token type.
	 * @param OwnTokenService|null $ownTokens The administrator's own tokens; without it the file holds none.
	 */
	public function __construct(
		RuntimeFileStore $store,
		CssParserService $cssParser,
		DarkPaletteService $darkPalette,
		?IConfig $config = null,
		?DesignSystemService $designSystems = null,
		?TokenValueValidator $values = null,
		?OwnTokenService $ownTokens = null,
	) {
		$this->store = $store;
		$this->cssParser = $cssParser;
		$this->config = $config;
		$this->designSystems = $designSystems;
		$this->values = ($values ?? new TokenValueValidator());
		$this->css = new OverridesCssBuilder(darkPalette: $darkPalette, values: $this->values);
		$this->ownTokens = $ownTokens;
	}//end __construct()

	/**
	 * The overrides file a token set reads, under `css/` and without extension.
	 *
	 * @param string $tokenSet       The token set id.
	 * @param string $designSystemId The design system the set wears.
	 *
	 * @return string `custom-overrides-{set}` for a set on `none`, {@see self::FILE} for every other.
	 *
	 * @spec openspec/specs/css-architecture/spec.md
	 */
	public static function fileFor(string $tokenSet, string $designSystemId): string {
		// The stock set is on `none` by definition; named here too so its
		// file does not depend on its metadata being readable.
		if ($designSystemId !== 'none' && $tokenSet !== CssInjectionService::STOCK_TOKEN_SET) {
			return self::FILE;
		}

		return self::FILE . '-' . preg_replace('/[^a-z0-9-]/', '', strtolower($tokenSet));
	}//end fileFor()

	/**
	 * The runtime file name of a token set's overrides file.
	 *
	 * @param string|null $tokenSet The token set id, or null for the instance's active set.
	 *
	 * @return string The name in the store, such as `css/custom-overrides.css`.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - fileFor() is static so CssInjectionService can ask without an instance
	 */
	private function getFilePath(?string $tokenSet): string {
		// Built without the set lookup (a caller that constructs the service
		// by hand): the shared file, which is every set's on a design system.
		if ($this->config === null || $this->designSystems === null) {
			return 'css/' . self::FILE . '.css';
		}

		if ($tokenSet === null) {
			$tokenSet = $this->config->getAppValue(
				Application::APP_ID,
				'token_set',
				CssInjectionService::STOCK_TOKEN_SET
			);
		}

		$meta = $this->designSystems->getTokenSetMeta(tokenSetId: $tokenSet);
		$file = self::fileFor(tokenSet: $tokenSet, designSystemId: (string)($meta['design_system'] ?? 'nldesign'));

		return 'css/' . $file . '.css';
	}//end getFilePath()

	/**
	 * Ensure the overrides file of a token set exists.
	 *
	 * Creates an empty :root {} file if the file is absent.
	 * Safe to call on every page load (no-op if file already exists).
	 *
	 * @param string|null $tokenSet The token set id, or null for the instance's active set.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-28
	 */
	public function ensureExists(?string $tokenSet = null): void {
		$name = $this->getFilePath(tokenSet: $tokenSet);
		if ($this->store->exists(name: $name) === false) {
			$this->writeFile(tokens: [], name: $name);
		}

	}//end ensureExists()

	/**
	 * Read the current custom overrides from the CSS file.
	 *
	 * Returns only the tokens explicitly set in the file.
	 * Does not return defaults or resolved values from the full CSS stack.
	 *
	 * @param string|null $tokenSet The token set id, or null for the instance's active set.
	 *
	 * @return array<string, string> Map of token name => value for all overrides in the file.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-29
	 */
	public function read(?string $tokenSet = null): array {
		$content = $this->store->read(name: $this->getFilePath(tokenSet: $tokenSet));
		if ($content === null) {
			return [];
		}

		// Only the editor's names: the motion twins and own tokens share the block.
		return $this->filterEditable(tokens: $this->parseDeclarations(css: $content));
	}//end read()

	/**
	 * The dark values an administrator set, as opposed to the ones derived from the light value.
	 *
	 * @param string|null $tokenSet The set whose file to read, or null for the active one.
	 *
	 * @return array<string, string> Token => dark value, only where it differs from the derived value.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-each-colour-token-has-an-optional-dark-value
	 */
	public function readDark(?string $tokenSet = null): array {
		return $this->ownDarkValues(css: $this->getRawContent(tokenSet: $tokenSet));
	}//end readDark()

	/**
	 * The administrator's own dark values in one overrides file's content.
	 *
	 * @param string $css The content of an overrides file.
	 *
	 * @return array<string, string> Token => dark value, only where it differs from the derived value.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-each-colour-token-has-an-optional-dark-value
	 */
	private function ownDarkValues(string $css): array {
		$start = strpos($css, 'body[data-theme-dark]');
		if ($start === false) {
			return [];
		}

		$open = (int)strpos($css, '{', $start);
		$close = (int)strpos($css, '}', $open);
		// Read through parseDeclarations(), so a settable variable stored as its
		// `--nldesign-nc-*` token comes back under the editor's name.
		$block = $this->parseDeclarations(css: ':root {' . substr($css, ($open + 1), ($close - $open - 1)) . '}');
		// Only the editor's names: own tokens keep their dark values in their own store.
		$light = $this->filterEditable(tokens: $this->parseDeclarations(css: $css));
		$derived = $this->css->darkValues(tokens: $light);
		$own = [];
		foreach ($block as $name => $value) {
			if (isset($light[$name]) === true && ($derived[$name] ?? null) !== $value) {
				$own[$name] = $value;
			}
		}

		return $own;
	}//end ownDarkValues()

	/**
	 * Write every overrides file again in today's shape, keeping its values.
	 *
	 * A file written before typed values gains the thematiq twin of each motion
	 * override and a dark value for each colour override (the administrator's own,
	 * else the derived one). Values are not type-checked here: a value that saved
	 * before keeps saving, so an upgrade never drops what an administrator set.
	 * Running it twice changes nothing the second time.
	 *
	 * @return array<string> The files that changed, by basename.
	 *
	 * @throws RuntimeException When a file cannot be written.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-motion-tokens-are-typed-and-reach-every-transition
	 */
	public function rewriteAll(): array {
		$changed = [];
		$names = array_filter(
			$this->store->listDirectory(directory: 'css'),
			static fn (string $name): bool => preg_match('#^css/' . self::FILE . '(-[a-z0-9-]+)?\.css$#', $name) === 1
		);
		foreach ($names as $name) {
			$css = (string)$this->store->read(name: $name);
			// The editable filter also drops the motion twins: the registry lists only the editor's names.
			$tokens = $this->filterEditable(tokens: $this->parseDeclarations(css: $css));
			$own = $this->ownDarkValues(css: $css);
			if ($this->buildCss(tokens: $tokens, darkTokens: $own) === $css) {
				continue;
			}

			$this->writeFile(tokens: $tokens, name: $name, darkTokens: $own);
			$changed[] = basename($name);
		}

		return $changed;
	}//end rewriteAll()

	/**
	 * The dark value each colour override gets when the administrator sets none.
	 *
	 * @param string|null $tokenSet The set whose file to read, or null for the active one.
	 *
	 * @return array<string, string> Token => derived dark value.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-each-colour-token-has-an-optional-dark-value
	 */
	public function derivedDark(?string $tokenSet = null): array {
		return $this->css->darkValues(tokens: $this->read(tokenSet: $tokenSet));
	}//end derivedDark()

	/**
	 * Write a new set of token overrides to the overrides file of a token set.
	 *
	 * Only tokens present in TokenRegistry are accepted — others are silently ignored.
	 * The store replaces the whole file in one write, so a reader never sees half of it.
	 *
	 * @param array<string, string> $tokens   Map of token name => value to persist.
	 * @param string|null           $tokenSet The token set id, or null for the instance's active set.
	 * @param array<string, string> $darkTokens The administrator's own dark values, by token.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the file cannot be written.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-30
	 */
	public function write(array $tokens, ?string $tokenSet = null, array $darkTokens = []): void {
		$validated = $this->filterEditable(tokens: $tokens);

		$this->writeFile(tokens: $validated, name: $this->getFilePath(tokenSet: $tokenSet), darkTokens: $darkTokens);
	}//end write()

	/**
	 * The tokens a save would drop or refuse, with the reason, which names the type.
	 *
	 * @param array<string, mixed> $tokens     Token name => light value.
	 * @param array<string, mixed> $darkTokens Token name => the administrator's own dark value.
	 *
	 * @return array<string, string> Token name => reason; empty when everything passes.
	 *
	 * @spec openspec/specs/token-editor-ui/spec.md#requirement-the-server-checks-each-value-against-its-token-type
	 */
	public function findRejected(array $tokens, array $darkTokens = []): array {
		return $this->values->findRejected(tokens: $tokens, darkTokens: $darkTokens);
	}//end findRejected()

	/**
	 * Filter a token map to only those present in the registry.
	 *
	 * @param array<string, string> $tokens Input token map.
	 *
	 * @return array<string, string> Filtered tokens.
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-8
	 */
	private function filterEditable(array $tokens): array {
		$result = [];
		foreach ($tokens as $name => $value) {
			if (TokenRegistry::isEditable(tokenName: $name) === true) {
				$result[$name] = $value;
			}
		}

		return $result;
	}//end filterEditable()

	/**
	 * Write the CSS file to the store.
	 *
	 * @param array<string, string> $tokens     Validated token map to write.
	 * @param string                $name       The runtime file name to write.
	 * @param array<string, string> $darkTokens The administrator's own dark values, by token.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the store cannot write the file.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-31
	 */
	private function writeFile(array $tokens, string $name, array $darkTokens = []): void {
		$this->store->write(name: $name, content: $this->buildCss(tokens: $tokens, darkTokens: $darkTokens));

	}//end writeFile()

	/**
	 * Build the CSS file content from a token map.
	 *
	 * @param array<string, string> $tokens Token name => value pairs.
	 * @param array<string, string> $darkTokens The administrator's own dark values, by token.
	 *
	 * @return string The CSS file content.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-32
	 */
	private function buildCss(array $tokens, array $darkTokens = []): string {
		return $this->css->build(tokens: $tokens, darkTokens: $darkTokens, own: $this->ownTokens?->css());
	}//end buildCss()

	/**
	 * Parse CSS custom property declarations from a :root {} block.
	 *
	 * Delegates to CssParserService to avoid duplicating the parse logic.
	 *
	 * @param string $css The raw CSS string.
	 *
	 * @return array<string, string> Map of token name => value.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-29
	 *
	 * @SuppressWarnings(PHPMD.StaticAccess) - TokenRegistry uses static methods by design
	 */
	private function parseDeclarations(string $css): array {
		$byToken = [];
		foreach (TokenRegistry::getSettableTokens() as $name => $meta) {
			$byToken[$meta['token']] = $name;
		}

		// Read a stored token back under the Nextcloud name the editor uses.
		$tokens = [];
		foreach ($this->cssParser->parseRootBlock(css: $css) as $name => $value) {
			$tokens[($byToken[$name] ?? $name)] = $value;
		}

		return $tokens;
	}//end parseDeclarations()

	/**
	 * Return the raw CSS file content for download.
	 *
	 * @param string|null $tokenSet The token set id, or null for the instance's active set.
	 *
	 * @return string The raw file content, or an empty :root {} if the file does not exist.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-nldesign/tasks.md#task-34
	 */
	public function getRawContent(?string $tokenSet = null): string {
		$content = $this->store->read(name: $this->getFilePath(tokenSet: $tokenSet));
		if ($content === null) {
			return self::CSS_HEADER . PHP_EOL . ':root {}' . PHP_EOL;
		}

		return $content;
	}//end getRawContent()
}//end class
