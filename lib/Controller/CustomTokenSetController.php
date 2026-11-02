<?php

/**
 * NL Design Custom Token Set Controller.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Controller
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-3.1
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-3.2
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-3.3
 * @spec openspec/specs/custom-token-sets/spec.md
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 */

declare(strict_types=1);

namespace OCA\Thematiq\Controller;

use OCA\Thematiq\AppInfo\Application;
use OCA\Thematiq\Service\BrandingCaptureService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\DesignSystemService;
use OCA\Thematiq\Service\MultiBrandImportService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\ThemingService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCA\Thematiq\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use RuntimeException;

/**
 * Admin-only controller for the custom token set upload lifecycle.
 *
 * Every method is restricted to delegated theming admins via
 * AuthorizedAdminSetting and CSRF-protected (no NoCSRFRequired). The upload
 * output is CSS served to every user, so the validation pipeline is strict and
 * the served file is always re-serialised from parsed declarations.
 *
 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-3.1
 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) - one endpoint family (import, list, export, delete) for one resource; the branches
 *   are the order the validation has to run in, and splitting them across classes would spread that order out rather than simplify it.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - importing a theme genuinely needs all of them: convert, validate, parse, store, audit,
 *   and — when the deleted set was the active one — undo what it pushed into core theming. Each is called here; none is passed through.
 */
class CustomTokenSetController extends Controller {
	use ErrorStatusTrait;

	/**
	 * The custom token set storage/lifecycle service.
	 *
	 * @var CustomTokenSetService
	 */
	private CustomTokenSetService $service;

	/**
	 * The CSS upload validator / re-serialiser.
	 *
	 * @var CustomTokenSetValidator
	 */
	private CustomTokenSetValidator $validator;

	/**
	 * The CSS parser service.
	 *
	 * @var CssParserService
	 */
	private CssParserService $cssParser;

	/**
	 * The localization service.
	 *
	 * @var IL10N
	 */
	private IL10N $l;

	/**
	 * The theming audit trail service.
	 *
	 * @var ThemingAuditService
	 */
	private ThemingAuditService $auditService;

	/**
	 * The application configuration service (active token set lookup, for
	 * detecting whether a delete resets the active set).
	 *
	 * @var IConfig
	 */
	private IConfig $config;

	/**
	 * The theme converter — runs BEFORE the validator on every upload and
	 * paste, so an admin can hand this app the artefact a design system
	 * actually publishes instead of pre-baked `--nldesign-*` CSS.
	 *
	 * @var TokenSetConverterService
	 */
	private TokenSetConverterService $converter;

	/**
	 * Nextcloud core theming, so deleting the ACTIVE set can undo what that set
	 * pushed into core (primary colour, background, logo).
	 *
	 * @var ThemingService
	 */
	private ThemingService $themingService;

	/**
	 * The shipped design-system manifest, used to allow-list a claimed id.
	 *
	 * @var DesignSystemService
	 */
	private DesignSystemService $designSystems;

	/**
	 * Copies Nextcloud's own branding into a theme saved from the editor.
	 * Optional so a caller that builds this controller by hand need not know
	 * about it; the container always injects it.
	 *
	 * @var BrandingCaptureService|null
	 */
	private ?BrandingCaptureService $brandingCapture;

	/**
	 * Constructor.
	 *
	 * @param string $appName The app name.
	 * @param IRequest $request The request object.
	 * @param CustomTokenSetService $service The storage/lifecycle service.
	 * @param CustomTokenSetValidator $validator The CSS validator.
	 * @param CssParserService $cssParser The CSS parser service.
	 * @param IL10N $l The localization service.
	 * @param ThemingAuditService $auditService The theming audit trail service.
	 * @param IConfig $config The config service.
	 * @param TokenSetConverterService $converter The theme converter, which runs before the validator.
	 * @param ThemingService $themingService Core theming, for undoing a deleted set's sync.
	 * @param DesignSystemService $designSystems The design-system manifest, for allow-listing a claimed id.
	 * @param BrandingCaptureService|null $brandingCapture Copies Nextcloud's branding into a theme saved from the editor.
	 * @param MultiBrandImportService|null $multiBrand Lists and imports the brands of a multi-brand source.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList) - Nextcloud's container injects through the constructor and nothing else; the
	 *   alternative is a service locator, which hides exactly these dependencies instead of removing any of them.
	 */
	public function __construct(
		string $appName,
		IRequest $request,
		CustomTokenSetService $service,
		CustomTokenSetValidator $validator,
		CssParserService $cssParser,
		IL10N $l,
		ThemingAuditService $auditService,
		IConfig $config,
		TokenSetConverterService $converter,
		ThemingService $themingService,
		DesignSystemService $designSystems,
		?BrandingCaptureService $brandingCapture = null,
		private ?MultiBrandImportService $multiBrand = null,
	) {
		parent::__construct(appName: $appName, request: $request);
		$this->service = $service;
		$this->validator = $validator;
		$this->cssParser = $cssParser;
		$this->l = $l;
		$this->auditService = $auditService;
		$this->config = $config;
		$this->converter = $converter;
		$this->themingService = $themingService;
		$this->designSystems = $designSystems;
		$this->brandingCapture = $brandingCapture;
	}//end __construct()

	/**
	 * Import a design-system theme (or a finished token set) as a custom set.
	 *
	 * Accepts a `name` plus EITHER a multipart `file` or a `content` paste —
	 * identical from the read onward, because a theme usually arrives as text
	 * in a clipboard, not as a file on disk. The content is CONVERTED first
	 * (`TokenSetConverterService`, which detects which of the four accepted
	 * shapes it is from the content itself, never from the file name), and the
	 * converter's emitted CSS is then put through the existing validator and
	 * stored as `css/tokens/custom-{slug}.css`.
	 *
	 * Conversion runs BEFORE validation, never instead of it: the validator is
	 * still the last gate on the bytes that get written.
	 *
	 * THE CONVERTER IS WIRED IN UNCONDITIONALLY, ON PURPOSE, AND IS NOT
	 * FINISHED. There is no feature flag and no bypass, because the path it
	 * replaces was strictly worse: it accepted only pre-baked `--nldesign-*`
	 * CSS, so every other shape a design system publishes — a W3C Design Tokens
	 * document, a Style Dictionary `tokens.json`, a built theme stylesheet — was
	 * simply refused. The converter accepts all four and is already better than
	 * what was here before, which is why it ships now rather than behind a flag
	 * nobody would turn on.
	 *
	 * What is NOT yet done, and is tracked in
	 * `openspec/changes/nlds-theme-converter`: the parity tests between this
	 * runtime and its JS mirror, the paste box, the report rendering in the
	 * admin panel, and regenerating the shipped sets. Until those land, expect
	 * conversions to need review — the emitted file is validated, so nothing
	 * unsafe is written, but "it stored something" is not yet "it stored the
	 * right thing".
	 *
	 * @return JSONResponse `{ id, imported, skipped, warnings, report, counts, inputKind }` or an error.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function upload(): JSONResponse {
		$name = trim((string)($this->request->getParam('name', '')));
		if ($name === '') {
			return new JSONResponse(['error' => $this->l->t('A token set name is required.')], 400);
		}

		$slug = $this->service->slugify(name: $name);
		if ($slug === '') {
			return new JSONResponse(['error' => $this->l->t('A token set name must contain at least one letter or digit.')], 422);
		}

		$read = $this->readInput();
		if ($read instanceof JSONResponse) {
			return $read;
		}

		// One source, several brands: listed first, stored on the admin's choice.
		$brands = ($this->multiBrand?->brands(content: $read['content']) ?? []);
		if ($brands !== []) {
			return $this->uploadBrands(name: $name, read: $read, brands: $brands);
		}

		// ALREADY A TOKEN SET — store it without converting it.
		//
		// The token editor's "save as a new theme" serialises the active set and
		// the admin's overrides straight into the `css/tokens/*.css` shape, so
		// there is nothing to convert. Running it through the converter anyway
		// was actively wrong: the converter's job is to make an incomplete NLDS
		// document whole, and it does that by filling every unmapped target from
		// the mapping table's fallbacks. An admin who changed one colour got a
		// theme carrying 121 component tokens of Rijkshuisstijl defaults they
		// never chose.
		//
		// A file an admin picks off disk is an unknown document and goes through
		// the converter — unless Thematiq exported it. "Export as token set"
		// marks its file with the design system it was taken from, and that
		// file is a token set already: converting it filled every token the set
		// left out with nldesign fallbacks, so an exported theme came back as a
		// different one.
		$exported = $this->exportedDesignSystem(content: $read['content']);
		if ($this->request->getParam('raw', false) === true || $exported !== null) {
			return $this->storeRaw(name: $name, slug: $slug, content: $read['content'], designSystem: $exported);
		}

		return $this->storeConverted(name: $name, slug: $slug, read: $read);
	}//end upload()

	/**
	 * A multi-brand upload: without `brands` the brands are listed and nothing is stored;
	 * with `brands` the chosen ones are stored together, or none.
	 *
	 * @param string $name The source's display name.
	 * @param array{content: string, sourceName: string|null} $read The payload from readInput().
	 * @param array<int, array<string, mixed>> $brands The brands the source holds.
	 *
	 * @return JSONResponse `{multiBrand, stored, brands}` or `{multiBrand, stored, sourceId, sets}`, or an error naming the brand.
	 *
	 * @spec openspec/specs/multi-brand-token-sources/spec.md#requirement-a-brand-is-a-custom-token-set-linked-to-its-source
	 */
	private function uploadBrands(string $name, array $read, array $brands): JSONResponse {
		$chosen = $this->request->getParam('brands', null);
		if ($chosen === null || $this->multiBrand === null) {
			return new JSONResponse(['multiBrand' => true, 'stored' => false, 'brands' => $brands]);
		}

		try {
			$result = $this->multiBrand->import(sourceName: $name, content: $read['content'], keys: (array)$chosen, fileName: $read['sourceName']);
		} catch (RuntimeException $e) {
			$code = match ($e->getCode()) {
				404 => 404,
				409 => 409,
				default => 422,
			};

			return new JSONResponse(['error' => $e->getMessage()], $code);
		}

		return new JSONResponse(['multiBrand' => true, 'stored' => true] + $result);
	}//end uploadBrands()

	/**
	 * The design system a file exported by Thematiq says it was taken from.
	 *
	 * `js/playground.js` `exportCss()` writes the marker as a comment of its
	 * own, so the exported file stays a plain token set that any other reader
	 * can take as it is.
	 *
	 * @param string $content The uploaded document.
	 *
	 * @return string|null The design system id, or null for a file without the marker.
	 *
	 * @spec openspec/specs/token-import-export/spec.md#requirement-token-set-round-trip
	 */
	private function exportedDesignSystem(string $content): ?string {
		if (preg_match('/\/\*\s*thematiq-token-set:\s*design-system=([a-z0-9-]+)\s*\*\//', $content, $match) !== 1) {
			return null;
		}

		return $match[1];
	}//end exportedDesignSystem()

	/**
	 * Store a token set that arrived already in the `css/tokens/*.css` shape.
	 *
	 * @param string $name The set's display name.
	 * @param string $slug The slug derived from the name.
	 * @param string $content The token set CSS, as sent; only its validated declarations are stored.
	 * @param string|null $designSystem The design system the file itself names, which outranks the request's claim.
	 *
	 * @return JSONResponse The persisted set, or the validator's error.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function storeRaw(string $name, string $slug, string $content, ?string $designSystem = null): JSONResponse {
		$parsed = $this->mapFromCss(content: $content, slug: $slug);
		if ($parsed instanceof JSONResponse) {
			return $parsed;
		}

		// No `css` key: persist() then writes the accepted declarations
		// re-serialised, as a bundle import does, never the bytes as sent.
		// The selector guard and the parser strip comments without regard to
		// strings, while a browser does not treat a `/*` inside `url('…')` as
		// one, so a crafted file could hide a whole rule between two :root
		// declarations and still validate.

		// Which design system the editor was looking at when it serialised
		// this. Allow-listed against the shipped manifest rather than taken
		// on trust: it decides which stylesheet layers every page load emits.
		$claimed = ($designSystem ?? trim((string)$this->request->getParam('designSystem', '')));
		if ($claimed !== '' && isset($this->designSystems->getDesignSystems()[$claimed]) === true) {
			$parsed['designSystem'] = $claimed;
		}

		$response = $this->persist(name: $name, parsed: $parsed);

		// A theme saved from the editor keeps the Nextcloud branding that
		// was on when it was saved — colours, background, logos, favicon —
		// so applying it later brings that back too. Only once the set is
		// safely stored: a refused save must not leave copied images behind.
		if ($this->request->getParam('captureTheming', false) === true
			&& $this->brandingCapture !== null
			&& $response->getStatus() === 200
		) {
			// The persist() response is always an array; getData() is typed
			// array|object, so say which before indexing it.
			$data = $response->getData();
			if (is_array($data) === true) {
				$data['theming'] = $this->brandingCapture->capture(setId: (string)$data['id']);
				$response->setData($data);
			}
		}

		return $response;
	}//end storeRaw()

	/**
	 * Convert an uploaded design-system document and store the result.
	 *
	 * @param string $name The set's display name.
	 * @param string $slug The slug derived from the name.
	 * @param array{content: string, sourceName: string|null} $read The payload from readInput().
	 *
	 * @return JSONResponse `{ id, imported, skipped, warnings, report, counts, inputKind }` or an error.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function storeConverted(string $name, string $slug, array $read): JSONResponse {
		$converted = $this->convertUpload(upload: $read + ['name' => $name, 'slug' => $slug]);
		if ($converted instanceof JSONResponse) {
			return $converted;
		}

		$parsed = $this->parsedUpload(converted: $converted, slug: $slug);
		if ($parsed instanceof JSONResponse) {
			return $parsed;
		}

		return $this->persist(name: $name, parsed: $parsed);
	}//end storeConverted()

	/**
	 * Validate the emitted file and add what the conversion and the dark values brought.
	 *
	 * @param array<string, mixed> $converted The converter result.
	 * @param string $slug The slug derived from the name.
	 *
	 * @return array<string, mixed>|JSONResponse The validated upload, or the refusal.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function parsedUpload(array $converted, string $slug): array|JSONResponse {
		// The validator sees the EMITTED file, which is the thing being stored.
		$parsed = $this->mapFromCss(content: $converted['css'], slug: $slug);
		if ($parsed instanceof JSONResponse) {
			return $parsed;
		}

		return $this->withDarkPins(
			parsed: $this->withDiagnostics(parsed: $this->withConversion(parsed: $parsed, converted: $converted), converted: $converted),
			dark: ['declarations' => ($converted['darkDeclarations'] ?? []), 'slug' => $slug]
		);
	}//end parsedUpload()

	/**
	 * Run the converter on an upload, refusing a document that yields nothing.
	 *
	 * @param array{content: string, sourceName: string|null, name: string, slug: string} $upload The payload, name and slug.
	 *
	 * @return array<string, mixed>|JSONResponse The converter result, or the error response.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function convertUpload(array $upload): array|JSONResponse {
		try {
			$converted = $this->callConverter(upload: $upload);
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => $e->getMessage()], $this->errorStatus(exception: $e, fallback: 422));
		}

		// What the ADMIN'S DOCUMENT yielded, reported by the converter from the
		// end of its input parse — not the size of the emitted file. The
		// converter fills every unmapped target from the mapping table's
		// fallbacks, so the emitted file is always the complete semantic layer
		// and says nothing about what was uploaded.
		if ((int)($converted['imported'] ?? 0) === 0) {
			return $this->nothingImported(converted: $converted);
		}

		return $converted;
	}//end convertUpload()

	/**
	 * Run the converter on an upload.
	 *
	 * @param array{content: string, sourceName: string|null, name: string, slug: string} $upload The payload, name and slug.
	 *
	 * @return array<string, mixed> The converter result.
	 *
	 * @throws RuntimeException When the document cannot be converted.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function callConverter(array $upload): array {
		return $this->converter->convert(
			content: $upload['content'],
			slug: $upload['slug'],
			displayName: $upload['name'],
			sourceName: $upload['sourceName'],
			// An extracted logo lands under the SET's id, not the bare
			// slug, so an uploaded theme named "Amsterdam" can never
			// overwrite the shipped img/logos/amsterdam.svg.
			assetName: CustomTokenSetService::ID_PREFIX . $upload['slug']
		);
	}//end callConverter()

	/**
	 * The refusal of a document that yields no token: a fixable mistake, so it carries the findings.
	 *
	 * @param array<string, mixed> $converted The converter result.
	 *
	 * @return JSONResponse
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function nothingImported(array $converted): JSONResponse {
		return new JSONResponse(
			[
				'error' => $this->l->t(
					'Nothing in this document maps onto a Nextcloud token. The skipped list'
					. ' shows what was found and why each token could not be used.'
				),
				'imported' => 0,
				'skipped' => $this->skippedFrom(report: $converted['report']),
				'errors' => $converted['errors'],
			],
			422
		);
	}//end nothingImported()

	/**
	 * Add what the conversion produced to the validated split.
	 *
	 * What the admin's document yielded, and what of it could not be used, describe the UPLOAD,
	 * so they replace the validator's split, which describes the emitted file.
	 *
	 * @param array<string, mixed> $parsed The validated split of the emitted file.
	 * @param array<string, mixed> $converted The converter result.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function withConversion(array $parsed, array $converted): array {
		$parsed['imported'] = (int)($converted['imported'] ?? 0);
		$parsed['skipped'] = $this->skippedFrom(report: $converted['report']);
		$parsed['css'] = $converted['css'];
		$parsed['theming'] = ($converted['manifestEntry']['theming'] ?? []);
		$parsed['logoAsset'] = ($converted['logoAsset'] ?? null);
		$parsed['report'] = $converted['report'];
		$parsed['counts'] = $converted['counts'];
		$parsed['inputKind'] = $converted['inputKind'];
		$parsed['reasons'] = $this->converter->getReasons();

		return $parsed;
	}//end withConversion()

	/**
	 * Add the declared version and the DTCG-only diagnostics.
	 *
	 * `$deprecated` notices about the SOURCE document and hard mapping errors keep the shape of the
	 * pre-converter upload path, separate from the conversion report.
	 *
	 * @param array<string, mixed> $parsed The upload so far.
	 * @param array<string, mixed> $converted The converter result.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function withDiagnostics(array $parsed, array $converted): array {
		if (isset($converted['manifestEntry']['upstreamVersion']) === true) {
			$parsed['version'] = $converted['manifestEntry']['upstreamVersion'];
		}

		if (empty($converted['importWarnings']) === false) {
			$parsed['importWarnings'] = $converted['importWarnings'];
		}

		if (empty($converted['errors']) === false) {
			$parsed['errors'] = $converted['errors'];
		}

		return $parsed;
	}//end withDiagnostics()

	/**
	 * Judge the dark values the file brought like the light ones, and keep the accepted ones.
	 *
	 * One that the validator refuses refuses the upload, as a light value does.
	 *
	 * @param array<string, mixed> $parsed The upload so far.
	 * @param array{declarations: array<string, string>, slug: string} $dark The file's dark declarations and the slug.
	 *
	 * @return array<string, mixed>|JSONResponse The upload with its dark pins, or the refusal.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function withDarkPins(array $parsed, array $dark): array|JSONResponse {
		if (empty($dark['declarations']) === true) {
			return $parsed;
		}

		$split = $this->validator->validateDeclarations(declarations: $dark['declarations'], slug: $dark['slug']);
		if ($split === null) {
			$error = $this->validator->getLastError();

			return new JSONResponse(['error' => $this->l->t('A dark value in the file is not allowed: %s', [(string)($error['property'] ?? '')])], 422);
		}

		$parsed['darkPins'] = $split['accepted'];

		return $parsed;
	}//end withDarkPins()

	/**
	 * Read the import payload from either the file picker or the paste box.
	 *
	 * Both surfaces enforce the same 512 KB limit, and an empty file picker and
	 * an empty textarea are deliberately the same error: from here on the two
	 * paths are indistinguishable, so they must fail identically too.
	 *
	 * @return array{content: string, sourceName: string|null}|JSONResponse The payload, or the error response.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function readInput() {
		$file = $this->request->getUploadedFile(key: 'file');
		if (empty($file) === false && isset($file['tmp_name']) === true) {
			return $this->readUpload(file: $file);
		}

		return $this->pastedInput();
	}//end readInput()

	/**
	 * The import payload from the paste box.
	 *
	 * @return array{content: string, sourceName: string|null}|JSONResponse The payload, or the error response.
	 *
	 * @spec openspec/changes/nlds-theme-converter/specs/custom-token-sets/spec.md
	 */
	private function pastedInput(): array|JSONResponse {
		$pasted = (string)($this->request->getParam('content', ''));
		if (trim($pasted) === '') {
			return new JSONResponse(['error' => $this->l->t('Choose a file or paste the contents of a theme file.')], 400);
		}

		if (strlen($pasted) > CustomTokenSetValidator::MAX_SIZE) {
			return new JSONResponse(['error' => $this->l->t('Pasted content exceeds the 512 KB size limit.')], 413);
		}

		$sourceName = trim((string)($this->request->getParam('sourceName', '')));
		if ($sourceName === '') {
			$sourceName = null;
		}

		return [
			'content' => $pasted,
			'sourceName' => $sourceName,
		];
	}//end pastedInput()

	/**
	 * The import payload from the file picker: the envelope validated, the content read.
	 *
	 * @param array<string, mixed>|null $file The uploaded file array from the request.
	 *
	 * @return array{content: string, sourceName: string|null}|JSONResponse The payload, or the error response.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function readUpload(?array $file) {
		if (empty($file) === true || isset($file['tmp_name']) === false) {
			return new JSONResponse(['error' => $this->l->t('No file uploaded.')], 400);
		}

		if (($file['size'] ?? 0) > CustomTokenSetValidator::MAX_SIZE) {
			return new JSONResponse(['error' => $this->l->t('File exceeds the 512 KB size limit.')], 413);
		}

		$content = file_get_contents($file['tmp_name']);
		if ($content === false) {
			return new JSONResponse(['error' => $this->l->t('Could not read the uploaded file.')], 400);
		}

		$fileName = trim((string)($file['name'] ?? ''));
		if ($fileName === '') {
			$fileName = null;
		}

		return [
			'content' => $content,
			'sourceName' => $fileName,
		];
	}//end readUpload()

	/**
	 * The tokens the conversion could not use, in the `{path, reason}` shape the
	 * admin panel groups by reason.
	 *
	 * Read from the same report the count comes from, so the number and the
	 * breakdown can never disagree with one another.
	 *
	 * @param array<int, array<string, mixed>> $report The conversion report.
	 *
	 * @return array<int, array{path: string, reason: string|null}> The skipped tokens.
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function skippedFrom(array $report): array {
		$skipped = [];

		foreach ($report as $entry) {
			if ((string)($entry['action'] ?? '') !== 'skipped') {
				continue;
			}

			$skipped[] = [
				'path' => (string)($entry['source'] ?? ''),
				'reason' => ($entry['reason'] ?? null),
			];
		}

		return $skipped;
	}//end skippedFrom()

	/**
	 * Parse and map a CSS upload into the accepted/skipped split.
	 *
	 * @param string $content The raw CSS upload.
	 * @param string $slug The derived slug (for `--{slug}-*` extras).
	 *
	 * @return array{accepted: array<string, string>, skipped: string[]}|JSONResponse
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-3.3
	 */
	private function mapFromCss(string $content, string $slug) {
		if ($this->validator->hasDisallowedSelector(css: $content) === true) {
			return new JSONResponse(
				['error' => $this->l->t('The CSS contains a selector or at-rule other than :root, which is not allowed in a token set.')],
				422
			);
		}

		$declarations = $this->cssParser->parseRootBlock(css: $content);
		$split = $this->validator->validateDeclarations(declarations: $declarations, slug: $slug);
		if ($split === null) {
			$error = $this->validator->getLastError();

			return new JSONResponse(
				['error' => ($error['message'] ?? $this->l->t('The uploaded CSS could not be validated.'))],
				($error['status'] ?? 422)
			);
		}

		return $split;
	}//end mapFromCss()

	/**
	 * Store the accepted declarations and build the upload response.
	 *
	 * The response's `warnings` key is always the WCAG contrast warnings
	 * (pre-existing behaviour, unchanged). A DTCG (JSON) upload additionally
	 * carries `errors` (structured, non-recoverable per-token diagnostics),
	 * `importWarnings` (structured `$deprecated` notices — deliberately not
	 * named `warnings` to avoid colliding with the contrast warnings above)
	 * and `version` (the declared package version, when present). A CSS
	 * upload carries none of those three — `$parsed` simply omits them.
	 *
	 * @param string $name The display name.
	 * @param array<string, mixed> $parsed The validated split (`accepted`, `skipped`), plus — for a
	 *                                     DTCG upload — `errors`, `importWarnings` and `version` (see the description above).
	 *
	 * @return JSONResponse The upload result or a collision/storage error.
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-3.3
	 * @spec openspec/specs/custom-token-sets/spec.md
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function persist(string $name, array $parsed): JSONResponse {
		try {
			$result = $this->service->store(...$this->storeArguments(name: $name, parsed: $parsed));
			if (empty($parsed['darkPins']) === false) {
				$this->service->keepDarkValues(id: $result['id'], declarations: $parsed['darkPins']);
			}
		} catch (RuntimeException $e) {
			return new JSONResponse(['error' => $e->getMessage()], $this->errorStatus(exception: $e, fallback: 500));
		}

		$this->auditService->log(action: 'custom_set_uploaded', context: $this->auditContext(parsed: $parsed, stored: $result + ['name' => $name]));

		return new JSONResponse($this->uploadResponse(parsed: $parsed, result: $result));
	}//end persist()

	/**
	 * The named arguments {@see CustomTokenSetService::store()} gets for an upload.
	 *
	 * @param string $name The display name.
	 * @param array<string, mixed> $parsed The validated upload.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function storeArguments(string $name, array $parsed): array {
		return [
			'displayName' => $name,
			'description' => trim((string)($this->request->getParam('description', ''))),
			'declarations' => $parsed['accepted'],
			'version' => ($parsed['version'] ?? null),
			'importWarnings' => ($parsed['importWarnings'] ?? []),
			// The converter's own file, so its four sections and provenance
			// block are what lands on disk rather than a flat re-serialise.
			'css' => ($parsed['css'] ?? null),
			'theming' => ($parsed['theming'] ?? []),
			'logoAsset' => ($parsed['logoAsset'] ?? null),
			'designSystem' => ($parsed['designSystem'] ?? null),
		];
	}//end storeArguments()

	/**
	 * What the audit log records about an upload.
	 *
	 * @param array<string, mixed> $parsed The validated upload.
	 * @param array{id: string, name: string} $stored The stored set's id and display name.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function auditContext(array $parsed, array $stored): array {
		return [
			'id' => $stored['id'],
			'name' => $stored['name'],
			'declarationCount' => (int)($parsed['imported'] ?? 0),
			'contentHash' => $this->servedHash(id: $stored['id']),
		];
	}//end auditContext()

	/**
	 * A short hash of the CSS the stored set serves, for the audit log.
	 *
	 * @param string $id The stored set id.
	 *
	 * @return string|null `sha256:` and 12 hex digits, or null when the set serves nothing.
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function servedHash(string $id): ?string {
		$servedCss = $this->service->getRawContent(id: $id);
		if ($servedCss === null) {
			return null;
		}

		return 'sha256:' . substr(hash(algo: 'sha256', data: $servedCss), 0, 12);
	}//end servedHash()

	/**
	 * The upload result: id, yield, contrast warnings and dark values, plus what the upload carried.
	 *
	 * The conversion report says what the theme asked for that Nextcloud will not do, and why;
	 * `reasons` carries the human sentence per code so the panel keeps no copy of the mapping table.
	 *
	 * @param array<string, mixed> $parsed The validated upload.
	 * @param array{id: string, warnings: array<int, array<string, mixed>>} $result The stored set.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function uploadResponse(array $parsed, array $result): array {
		$response = [
			'id' => $result['id'],
			'imported' => (int)($parsed['imported'] ?? 0),
			'skipped' => ($parsed['skipped'] ?? []),
			'warnings' => $result['warnings'],
			'darkValues' => count(($parsed['darkPins'] ?? [])),
		];

		return $response + $this->carriedFields(parsed: $parsed) + ['selectable' => true];
	}//end uploadResponse()

	/**
	 * What an upload carried beyond its yield: DTCG errors and import warnings, the declared version
	 * (kept even when null) and the conversion report.
	 *
	 * @param array<string, mixed> $parsed The validated upload.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/custom-token-sets/spec.md
	 */
	private function carriedFields(array $parsed): array {
		$fields = [];
		foreach (['errors', 'importWarnings', 'version', 'report', 'counts', 'inputKind', 'reasons'] as $key) {
			if (isset($parsed[$key]) === true || ($key === 'version' && array_key_exists('version', $parsed) === true)) {
				$fields[$key] = $parsed[$key];
			}
		}

		return $fields;
	}//end carriedFields()

	/**
	 * List stored custom token sets with their metadata and contrast warnings.
	 *
	 * @return JSONResponse The list of custom sets.
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-3.1
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function list(): JSONResponse {
		return new JSONResponse(['sets' => $this->service->list()]);
	}//end list()

	/**
	 * Export (download) the served CSS of a custom token set.
	 *
	 * @param string $id The custom set id.
	 *
	 * @return DataDownloadResponse|JSONResponse The CSS download or a 404.
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-3.1
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function export(string $id) {
		$content = $this->service->getRawContent(id: $id);
		if ($content === null) {
			return new JSONResponse(['error' => $this->l->t('Token set not found.')], 404);
		}

		return new DataDownloadResponse(
			data: $content,
			filename: $id . '.css',
			contentType: 'text/css'
		);
	}//end export()

	/**
	 * Delete a custom token set (file + manifest), resetting the active set if needed.
	 *
	 * @param string $id The custom set id.
	 *
	 * @return JSONResponse The deletion result.
	 *
	 * @spec openspec/changes/archive/2026-06-14-custom-token-set-upload/tasks.md#task-3.1
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	#[AuthorizedAdminSetting(Admin::class)]
	public function delete(string $id): JSONResponse {
		if ($this->service->isCustomId(id: $id) === false) {
			return new JSONResponse(['error' => $this->l->t('Only custom token sets can be deleted.')], 400);
		}

		$activeBefore = $this->config->getAppValue(Application::APP_ID, 'token_set', 'nextcloud');
		$contentHash = $this->servedHash(id: $id);

		if ($this->service->delete(id: $id) === false) {
			return new JSONResponse(['error' => $this->l->t('Token set not found.')], 404);
		}

		// The source record of a multi-brand import goes with its last brand, and
		// the branding it captured, and the images copied for it, go with it.
		$this->multiBrand?->forgetSet(id: $id);
		$this->brandingCapture?->forget(setId: $id);

		$activeReset = ($activeBefore === $id);
		if ($activeReset === true) {
			$this->resetActiveTheming();
		}

		$this->auditService->log(
			action: 'custom_set_deleted',
			context: ['id' => $id, 'activeReset' => $activeReset, 'themingReset' => $activeReset, 'contentHash' => $contentHash]
		);

		return new JSONResponse(['status' => 'ok']);
	}//end delete()

	/**
	 * Put Nextcloud's own colours and logo back after the active set was deleted.
	 *
	 * Deleting the ACTIVE set puts the instance back on stock, and stock
	 * means Nextcloud's own colours and logo, not the deleted set's,
	 * stranded in core theming with no set left to explain them. The service
	 * resets the `token_set` app value; the sync it pushed into core has to
	 * be undone here, exactly as selecting the stock set does. Nothing is
	 * synced any more, so nothing is remembered as synced.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/theming-audit/spec.md#requirement-complete-call-site-coverage
	 */
	private function resetActiveTheming(): void {
		$this->themingService->resetToDefaults();
		foreach (['logo', 'logoheader', 'favicon', 'background'] as $imageKey) {
			$this->config->deleteAppValue(
				Application::APP_ID,
				SettingsController::SYNCED_IMAGE_PREFIX . $imageKey
			);
		}
	}//end resetActiveTheming()
}//end class
