<?php

/**
 * Thematiq document house style profile.
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
 * @spec openspec/specs/document-house-style/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Service;

use OCA\Thematiq\AppInfo\Application;
use OCP\IURLGenerator;

/**
 * The values a generated document needs to follow the house style: the
 * organisation name, a logo, an optional cover, five colours, the heading
 * and body fonts and the footer lines. Thematiq does not render documents;
 * filinq (templates) and OpenRegister (PDF exports) read this profile.
 *
 * Fleet apps resolve this class from the server container when thematiq is
 * installed, or call `GET /apps/thematiq/api/document-style`; both return
 * {@see forUser()}. The colours come from the token set that applies to the
 * user, so a group-mapped house style yields its own profile.
 *
 * @spec openspec/specs/document-house-style/spec.md
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - the profile gathers values from every part of the house style.
 */
class DocumentStyleService {

	/**
	 * The contrast a document's text needs on its background.
	 *
	 * @var float
	 */
	public const MIN_TEXT_CONTRAST = 4.5;

	/**
	 * Documents print on white unless the set says otherwise.
	 *
	 * @var string
	 */
	private const PAPER = '#ffffff';

	/**
	 * Constructor.
	 *
	 * @param UserTokenSetResolver $resolver The set that applies to a user.
	 * @param TokenSetService $tokenSets Set names.
	 * @param TokenSetPreviewService $preview A set's resolved tokens.
	 * @param DesignSystemService $designSystem A set's theming metadata (logo).
	 * @param FontService $fonts Uploaded fonts by role.
	 * @param EmailThemingService $emailTheming The footer settings.
	 * @param DocumentAssetService $assets The document logo, cover and footer line.
	 * @param ContrastService $contrast WCAG contrast.
	 * @param IURLGenerator $urls URLs.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) - one collaborator per part of the profile.
	 */
	public function __construct(
		private readonly UserTokenSetResolver $resolver,
		private readonly TokenSetService $tokenSets,
		private readonly TokenSetPreviewService $preview,
		private readonly DesignSystemService $designSystem,
		private readonly FontService $fonts,
		private readonly EmailThemingService $emailTheming,
		private readonly DocumentAssetService $assets,
		private readonly ContrastService $contrast,
		private readonly IURLGenerator $urls,
	) {
	}//end __construct()

	/**
	 * The profile for a user.
	 *
	 * @param string|null $uid The user id; null for the instance default house style.
	 *
	 * @return array<string, mixed> `{tokenSet, organisation, logo, cover, colours, fonts, footer, warnings}`.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	public function forUser(?string $uid): array {
		$setId = $this->resolver->forUser(uid: $uid);
		$tokens = $this->preview->getResolvedTokens(tokenSetId: $setId);
		$footer = $this->emailTheming->getFooterConfig();
		$colours = $this->colours(tokens: $tokens);
		$lines = array_filter(
			[(string)($footer['orgName'] ?? ''), $this->assets->getFooterLine()],
			static fn (string $line): bool => $line !== ''
		);

		return [
			'tokenSet' => ['id' => $setId, 'name' => $this->setName(setId: $setId)],
			'organisation' => (string)($footer['orgName'] ?? ''),
			'logo' => ($this->asset(kind: 'logo') ?? $this->setLogo(setId: $setId)),
			'cover' => $this->asset(kind: 'cover'),
			'colours' => $colours,
			'fonts' => [
				'heading' => $this->font(role: 'heading', fallback: ($tokens['--nldesign-typography-heading-font-family'] ?? null), tokens: $tokens),
				'body' => $this->font(role: 'body', fallback: ($tokens['--nldesign-font-family'] ?? null), tokens: $tokens),
			],
			'footer' => [
				'lines' => array_values($lines),
				'accessibilityUrl' => (string)($footer['accessibilityUrl'] ?? ''),
				'privacyUrl' => (string)($footer['privacyUrl'] ?? ''),
			],
			'warnings' => $this->warnings(colours: $colours),
		];
	}//end forUser()

	/**
	 * The five colours of the profile.
	 *
	 * @param array<string, string> $tokens The resolved `--nldesign-*` tokens.
	 *
	 * @return array{primary: string|null, primaryText: string|null, text: string|null, background: string, accent: string|null} The colours.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function colours(array $tokens): array {
		return [
			'primary' => $this->resolve(name: '--nldesign-color-primary', tokens: $tokens),
			'primaryText' => $this->resolve(name: '--nldesign-color-primary-text', tokens: $tokens),
			'text' => $this->resolve(name: '--nldesign-color-text', tokens: $tokens),
			'background' => ($this->resolve(name: '--nldesign-color-background', tokens: $tokens) ?? self::PAPER),
			'accent' => $this->resolve(name: '--nldesign-color-primary-light', tokens: $tokens),
		];
	}//end colours()

	/**
	 * A token's literal value.
	 *
	 * @param string $name The token name.
	 * @param array<string, string> $tokens The resolved tokens.
	 *
	 * @return string|null The literal value, or null when it does not resolve.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function resolve(string $name, array $tokens): ?string {
		return $this->resolveValue(value: ($tokens[$name] ?? null), tokens: $tokens);
	}//end resolve()

	/**
	 * Follow `var()` references to a literal value.
	 *
	 * @param string|null $value The value, possibly a `var()` reference.
	 * @param array<string, string> $tokens The resolved tokens.
	 *
	 * @return string|null The literal value, or null when it does not resolve.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function resolveValue(?string $value, array $tokens): ?string {
		for ($hop = 0; $hop < 10 && $value !== null; $hop++) {
			if (preg_match('/^var\(\s*(--[\w-]+)\s*(?:,\s*(.+))?\)$/s', trim($value), $match) !== 1) {
				return trim((string)preg_replace('/\s+/', ' ', $value));
			}

			$value = ($tokens[$match[1]] ?? ($match[2] ?? null));
		}

		return null;
	}//end resolveValue()

	/**
	 * A font role: the uploaded font assigned to it, else the family the set declares.
	 *
	 * @param string $role `heading` or `body`.
	 * @param string|null $fallback The declared family token value.
	 * @param array<string, string> $tokens The resolved tokens, for var() references.
	 *
	 * @return array{family: string|null, url: string|null} The family and, for an uploaded font, its file URL.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function font(string $role, ?string $fallback, array $tokens): array {
		foreach ($this->fonts->getManifest() as $id => $entry) {
			if (is_array($entry) === true && ($entry['role'] ?? 'body') === $role) {
				return [
					'family' => (string)($entry['name'] ?? $id),
					'url' => $this->urls->linkToRoute('thematiq.font.serve', ['id' => (string)$id]),
				];
			}
		}

		return ['family' => $this->resolveValue(value: $fallback, tokens: $tokens), 'url' => null];
	}//end font()

	/**
	 * An uploaded document asset.
	 *
	 * @param string $kind `logo` or `cover`.
	 *
	 * @return array{url: string, mime: string}|null The asset, or null when none is uploaded.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function asset(string $kind): ?array {
		$meta = ($this->assets->getAssets()[$kind] ?? null);
		if (is_array($meta) === false) {
			return null;
		}

		return [
			'url' => $this->urls->linkToRoute('thematiq.documentStyle.asset', ['kind' => $kind, 'v' => (int)($meta['uploadedAt'] ?? 0)]),
			'mime' => (string)$meta['mime'],
		];
	}//end asset()

	/**
	 * The logo of a token set.
	 *
	 * @param string $setId The set id.
	 *
	 * @return array{url: string, mime: string}|null The logo, or null when the set has none.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function setLogo(string $setId): ?array {
		$path = ($this->designSystem->getTokenSetMeta(tokenSetId: $setId)['theming']['logo'] ?? null);
		if (is_string($path) === false || $path === '') {
			return null;
		}

		$relative = $path;
		if (str_starts_with($relative, 'img/') === true) {
			$relative = substr($relative, 4);
		}

		$mimes = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
		$extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

		return [
			'url' => $this->urls->imagePath(Application::APP_ID, $relative),
			'mime' => ($mimes[$extension] ?? 'application/octet-stream'),
		];
	}//end setLogo()

	/**
	 * The display name of a set.
	 *
	 * @param string $setId The set id.
	 *
	 * @return string The name, or the id when the set is unknown.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function setName(string $setId): string {
		foreach ($this->tokenSets->getAvailableTokenSets() as $set) {
			if (($set['id'] ?? null) === $setId) {
				return (string)($set['name'] ?? $setId);
			}
		}

		return $setId;
	}//end setName()

	/**
	 * Warnings about the colours.
	 *
	 * @param array<string, string|null> $colours The profile colours.
	 *
	 * @return array<int, array{code: string, ratio: float|null}> One entry per problem.
	 *
	 * @spec openspec/specs/document-house-style/spec.md
	 */
	private function warnings(array $colours): array {
		$ratio = null;
		if ($colours['text'] !== null) {
			$ratio = $this->contrast->measure(foreground: $colours['text'], background: (string)$colours['background']);
		}

		if ($ratio === null || $ratio < self::MIN_TEXT_CONTRAST) {
			return [['code' => 'text-contrast', 'ratio' => $ratio]];
		}

		return [];
	}//end warnings()
}//end class
