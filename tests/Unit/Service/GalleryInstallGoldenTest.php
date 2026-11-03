<?php

/**
 * Golden test for GalleryInstallService::install().
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://github.com/ConductionNL/thematiq
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/theme-gallery/spec.md#requirement-installing-is-checked-and-goes-through-the-upload-path
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\CustomTokenSetService;
use OCA\Thematiq\Service\CustomTokenSetValidator;
use OCA\Thematiq\Service\Exception\GalleryException;
use OCA\Thematiq\Service\GalleryDownloadSources;
use OCA\Thematiq\Service\GalleryInstallRecords;
use OCA\Thematiq\Service\GalleryInstallService;
use OCA\Thematiq\Service\ThemeGalleryService;
use OCA\Thematiq\Service\ThemingAuditService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCA\Thematiq\Service\TokenSetUploadPipeline;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionMethod;
use RuntimeException;

/**
 * Installs seeded gallery entries against recording doubles: unknown entries, downloads that
 * throw, fail or are too large, checksums that do not match, first installs and updates (active
 * or not, with or without a stored name), converter results that throw, import nothing, carry a
 * foreign selector or declarations the validator refuses, a store that refuses, and manifests
 * with and without the stored entry. Records the result or the exception and every call the
 * service made to its collaborators (only the arguments it passed, by name), case by case.
 * Set THEMATIQ_GOLDEN_RECORD=1 to rewrite it.
 *
 * @covers \OCA\Thematiq\Service\GalleryInstallService
 * @uses   \OCA\Thematiq\Service\GalleryDownloadSources
 * @uses   \OCA\Thematiq\Service\GalleryInstallRecords
 * @uses   \OCA\Thematiq\Service\TokenSetUploadPipeline
 * @uses   \OCA\Thematiq\Service\CustomTokenSetValidator
 * @uses   \OCA\Thematiq\Service\Exception\GalleryException
 */
class GalleryInstallGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/../fixtures/golden/gallery-install.golden.json';

	private const DECLARATIONS = [
		['--nldesign-color-primary' => '#154273', '--nldesign-color-primary-text' => '#ffffff'],
		['--nldesign-color-primary' => '#154273'],
		['--nldesign-color-primary' => 'expression(alert(1))'],
		['--not-a-token' => '1px'],
		[],
	];

	/**
	 * The calls of the current case.
	 *
	 * @var array<int, array<int|string, mixed>>
	 */
	private array $calls = [];

	/**
	 * Every seeded install has the recorded outcome and calls.
	 *
	 * @return void
	 */
	public function testInstallMatchesTheGoldenRecording(): void {
		$actual = [];
		for ($seed = 1; $seed <= 200; $seed++) {
			$actual['seed:' . $seed] = $this->runCase(random: new Randomizer(new Mt19937($seed)));
		}

		if (getenv('THEMATIQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		self::assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $json) {
			self::assertSame(expected: $json, actual: $actual[$name], message: $name);
		}
	}//end testInstallMatchesTheGoldenRecording()

	/**
	 * One seeded install, as JSON text.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return string
	 */
	private function runCase(Randomizer $random): string {
		$this->calls = [];
		$body = ":root {\n\t--nldesign-color-primary: #154273;\n}\n";
		if ($random->getInt(0, 19) === 0) {
			$body = str_repeat('x', (CustomTokenSetValidator::MAX_SIZE + 1));
		}

		$entry = [
			'id' => 'gallery-' . $random->getInt(1, 3),
			'name' => ['Gemeente Zuid', 'Stad Noord', ''][$random->getInt(0, 2)],
			'description' => ['Een huisstijl', ''][$random->getInt(0, 1)],
			'fileUrl' => ['https://example.org/sets/zuid.css?x=1', 'https://example.org/noord.json', 'https://example.org/'][$random->getInt(0, 2)],
			'sha256' => hash('sha256', $body),
			'sourceUrl' => 'https://example.org/source',
			'licence' => 'EUPL-1.2',
		];
		if ($random->getInt(0, 7) === 0) {
			$entry['sha256'] = hash('sha256', 'something else');
		}

		$galleryId = $entry['id'];
		if ($random->getInt(0, 9) === 0) {
			$galleryId = 'gallery-unknown';
		}

		$existing = [null, ['id' => 'custom-earlier', 'name' => 'Eerder'], ['id' => 'custom-earlier']][$random->getInt(0, 2)];
		$activeSet = ['custom-earlier', 'other', ''][$random->getInt(0, 2)];

		$service = new GalleryInstallService(
			downloads: new GalleryDownloadSources(
				gallery: $this->gallery(entry: $entry, existing: $existing),
				clientService: $this->clientService(random: $random, body: $body)
			),
			pipeline: new TokenSetUploadPipeline(
				converter: $this->converter(random: $random),
				validator: new CustomTokenSetValidator(),
				cssParser: $this->cssParser(random: $random)
			),
			records: new GalleryInstallRecords(
				customSets: $this->customSets(random: $random),
				audit: $this->recorder(class: ThemingAuditService::class, methods: ['log' => null]),
				config: $this->recorder(class: IConfig::class, methods: ['getAppValue' => $activeSet, 'setAppValue' => null]),
				time: $this->recorder(class: ITimeFactory::class, methods: ['getTime' => 1_800_000_000])
			),
			l10n: $this->l10n()
		);

		try {
			$outcome = ['result' => $service->install(galleryId: $galleryId)];
		} catch (GalleryException $e) {
			$outcome = ['exception' => $e->getCode(), 'message' => $e->getMessage(), 'previous' => ($e->getPrevious()?->getMessage())];
		}

		$outcome['calls'] = $this->calls;

		return (string)json_encode($outcome, (JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
	}//end runCase()

	/**
	 * A gallery that knows one entry and its installed set.
	 *
	 * @param array<string, string> $entry The entry.
	 * @param array<string, string>|null $existing The installed set, if any.
	 *
	 * @return ThemeGalleryService
	 */
	private function gallery(array $entry, ?array $existing): ThemeGalleryService {
		$gallery = $this->recorder(class: ThemeGalleryService::class, methods: ['installedFor' => $existing]);
		$gallery->method('findEntry')->willReturnCallback(
			function (string $galleryId) use ($entry): ?array {
				$this->calls[] = ['ThemeGalleryService::findEntry', $galleryId];
				if ($galleryId === $entry['id']) {
					return $entry;
				}

				return null;
			}
		);

		return $gallery;
	}//end gallery()

	/**
	 * An HTTP client service whose download throws, fails, or returns the body.
	 *
	 * @param Randomizer $random The case's randomizer.
	 * @param string $body The body a successful download returns.
	 *
	 * @return IClientService
	 */
	private function clientService(Randomizer $random, string $body): IClientService {
		$mode = $random->getInt(0, 14);
		$client = $this->createMock(originalClassName: IClient::class);
		$client->method('get')->willReturnCallback(
			function (string $url, array $options) use ($mode, $body): IResponse {
				$this->calls[] = ['IClient::get', $url, $options];
				if ($mode === 0) {
					throw new RuntimeException('network down');
				}

				$response = $this->createMock(originalClassName: IResponse::class);
				$response->method('getStatusCode')->willReturn(($mode === 1) ? 500 : 200);
				$response->method('getBody')->willReturn($body);
				return $response;
			}
		);

		$service = $this->createMock(originalClassName: IClientService::class);
		$service->method('newClient')->willReturn($client);
		return $service;
	}//end clientService()

	/**
	 * A converter that throws, imports nothing, emits a foreign selector or a normal set.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return TokenSetConverterService
	 */
	private function converter(Randomizer $random): TokenSetConverterService {
		$mode = $random->getInt(0, 9);
		$result = [
			'imported' => [0, 3, 7][$random->getInt(1, 2)],
			'css' => ":root {\n\t--nldesign-color-primary: #154273;\n}\n",
		];
		if ($mode === 1) {
			$result['imported'] = 0;
		}

		if ($mode === 2) {
			$result['css'] = "body { color: red; }\n";
		}

		if ($random->getInt(0, 1) === 0) {
			$result['manifestEntry'] = ['upstreamVersion' => '1.4.0', 'theming' => ['primary_color' => '#154273']];
		}

		if ($random->getInt(0, 1) === 0) {
			$result['importWarnings'] = [['path' => 'a.b', 'message' => 'deprecated']];
		}

		if ($random->getInt(0, 2) === 0) {
			$result['logoAsset'] = ['path' => 'logo.svg', 'contents' => '<svg/>'];
		}

		$converter = $this->createMock(originalClassName: TokenSetConverterService::class);
		$converter->method('convert')->willReturnCallback(
			function (...$arguments) use ($mode, $result): array {
				$this->calls[] = ['TokenSetConverterService::convert'] + $this->named(method: [TokenSetConverterService::class, 'convert'], arguments: $arguments);
				if ($mode === 0) {
					throw new RuntimeException('not a theme');
				}

				return $result;
			}
		);

		return $converter;
	}//end converter()

	/**
	 * A parser double that answers the root block with seeded declarations.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return CssParserService
	 */
	private function cssParser(Randomizer $random): CssParserService {
		$declarations = self::DECLARATIONS[$random->getInt(0, 6) % count(self::DECLARATIONS)];
		return $this->recorder(class: CssParserService::class, methods: ['parseRootBlock' => $declarations]);
	}//end cssParser()

	/**
	 * A custom token set service double that slugs, deletes, stores (or refuses) and keeps a manifest.
	 *
	 * @param Randomizer $random The case's randomizer.
	 *
	 * @return CustomTokenSetService
	 */
	private function customSets(Randomizer $random): CustomTokenSetService {
		$manifest = [[], ['custom-new' => ['name' => 'Nieuw', 'theming' => []]], ['custom-new' => 'not an entry']][$random->getInt(0, 2)];
		$raw = [null, ":root {}\n"][$random->getInt(0, 1)];
		$refuses = ($random->getInt(0, 7) === 0);

		$sets = $this->recorder(
			class: CustomTokenSetService::class,
			methods: ['slugify' => 'gemeente-zuid', 'delete' => true, 'getManifest' => $manifest, 'getRawContent' => $raw, 'replace' => null]
		);
		$sets->method('store')->willReturnCallback(
			function (...$arguments) use ($refuses): array {
				$this->calls[] = ['CustomTokenSetService::store'] + $this->named(method: [CustomTokenSetService::class, 'store'], arguments: $arguments);
				if ($refuses === true) {
					throw new RuntimeException('A custom token set named "x" already exists.', 409);
				}

				return ['id' => 'custom-new', 'warnings' => [['pair' => 'a vs b', 'ratio' => 2.5]]];
			}
		);

		return $sets;
	}//end customSets()

	/**
	 * A translator that fills `{reason}` and records nothing.
	 *
	 * @return IL10N
	 */
	private function l10n(): IL10N {
		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, $parameters = []): string => strtr($text, ['{reason}' => (string)(((array)$parameters)['reason'] ?? '')])
		);

		return $l10n;
	}//end l10n()

	/**
	 * A double whose listed methods record their arguments by name and return the given value.
	 *
	 * @param class-string $class The class or interface.
	 * @param array<string, mixed> $methods Method => return value.
	 *
	 * @return MockObject
	 */
	private function recorder(string $class, array $methods): MockObject {
		$double = $this->createMock(originalClassName: $class);
		foreach ($methods as $method => $value) {
			$double->method($method)->willReturnCallback(
				function (...$arguments) use ($class, $method, $value) {
					$short = substr($class, (strrpos($class, '\\') + 1));
					$this->calls[] = [$short . '::' . $method] + $this->named(method: [$class, $method], arguments: $arguments);
					return $value;
				}
			);
		}

		return $double;
	}//end recorder()

	/**
	 * The arguments a call received, keyed by parameter name.
	 *
	 * Named arguments arrive keyed by name already; positional ones are named from the signature.
	 *
	 * @param array{0: class-string, 1: string} $method The called method.
	 * @param array<int|string, mixed> $arguments The received arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function named(array $method, array $arguments): array {
		$parameters = (new ReflectionMethod($method[0], $method[1]))->getParameters();
		$named = [];
		foreach ($arguments as $key => $value) {
			if (is_int($key) === true) {
				$key = $parameters[$key]->getName();
			}

			$named[$key] = $value;
		}

		return $named;
	}//end named()
}//end class
