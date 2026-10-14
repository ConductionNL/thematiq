<?php

/**
 * Tests for the OpenProject plugin's set file, both ways.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Tests
 * @package   OCA\Thematiq
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/openproject-set-file/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DesignTokensMapper;
use OCA\Thematiq\Service\FontService;
use OCA\Thematiq\Service\OpenProjectSetFile;
use OCA\Thematiq\Service\OpenProjectSetFileWriter;
use OCA\Thematiq\Service\ShellCatalogueService;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetConverterService;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Download a set file, upload it, compare the roles; and upload the plugin's own files.
 *
 * @spec openspec/changes/openproject-set-file/specs/openproject-set-file/spec.md#requirement-the-plugins-set-file-becomes-a-custom-token-set
 */
final class OpenProjectSetFileTest extends TestCase {

	/**
	 * The app directory.
	 *
	 * @return string
	 */
	private static function appPath(): string {
		return \dirname(__DIR__, 3);
	}//end appPath()

	/**
	 * The set file writer over the repository.
	 *
	 * @return OpenProjectSetFileWriter
	 */
	private function writer(): OpenProjectSetFileWriter {
		$contrast = new ContrastService();

		return new OpenProjectSetFileWriter(new ShellCatalogueService(new ShippedTokenSetAuditService($contrast, new CssParserService()), new ShellRoleMapper(colours: $contrast)));
	}//end writer()

	/**
	 * Upload a document the way the admin upload does.
	 *
	 * @param string $json The file.
	 *
	 * @return array{declarations: array<string, string>, skipped: array<int, string>, dark: array<string, string>}
	 */
	private function upload(string $json): array {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(self::appPath());
		$parser = new CssParserService();
		$converter = new TokenSetConverterService($appManager, $parser, new ContrastService(), new DesignTokensMapper(), $this->createMock(FontService::class), $this->createMock(LoggerInterface::class));
		$result = $converter->convert(content: $json, slug: 'kopie', displayName: 'Kopie');

		$skipped = [];
		foreach ($result['report'] as $entry) {
			if (($entry['action'] ?? '') === 'skipped') {
				$skipped[] = $entry['source'] . ': ' . $entry['reason'];
			}
		}

		return ['declarations' => $parser->parseRootBlock(css: (string)$result['css']), 'skipped' => $skipped, 'dark' => ($result['darkDeclarations'] ?? [])];
	}//end upload()

	/**
	 * The roles of uploaded declarations.
	 *
	 * @param array<string, string> $declarations The declarations.
	 *
	 * @return array<string, string>
	 */
	private function roles(array $declarations): array {
		return (new ShellRoleMapper(colours: new ContrastService()))->map(tokens: $declarations);
	}//end roles()

	/**
	 * Every shipped set.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function shippedSets(): array {
		$out = [];
		foreach ((array)json_decode((string)file_get_contents(self::appPath() . '/token-sets.json'), true) as $set) {
			$id = (string)($set['id'] ?? '');
			if ($id !== '' && is_file(self::appPath() . '/css/tokens/' . $id . '.css') === true) {
				$out[$id] = [$id];
			}
		}

		return $out;
	}//end shippedSets()

	/**
	 * Scenario: every shipped set, downloaded as a set file and uploaded again, has the same roles.
	 *
	 * @param string $id The set id.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/openproject-set-file/specs/openproject-set-file/spec.md#requirement-any-token-set-can-be-downloaded-as-the-plugins-set-file
	 */
	#[DataProvider('shippedSets')]
	public function testASetFileComesBackWithTheSameRoles(string $id): void {
		$file = $this->writer()->setFile(appPath: self::appPath(), id: $id, name: 'Naam', store: null);

		$this->assertSame(['id', 'name', 'tokens', 'logo'], array_slice(array_keys($file), 0, 4));
		$this->assertSame([], array_diff(array_keys($file), ['id', 'name', 'tokens', 'logo', 'dark', 'vocabulary']));
		$this->assertSame(['file', 'inverse_file', 'licence', 'redistributable', 'basis'], array_keys($file['logo']));

		$back = $this->upload(json: (string)json_encode($file));
		$roles = $this->roles(declarations: $back['declarations']);
		foreach ($file['tokens'] as $role => $value) {
			$this->assertSame(strtolower((string)$value), strtolower((string)$roles[$role]), $id . ' ' . $role);
		}

		foreach (($file['vocabulary'] ?? []) as $key => $value) {
			$this->assertSame(trim((string)$value), ($back['declarations']['--nldesign-' . $key] ?? null), $id . ' vocabulary ' . $key);
		}
	}//end testASetFileComesBackWithTheSameRoles()

	/**
	 * The plugin's own files, as its code wrote them.
	 *
	 * Built on 14 October 2026 with `OpenProject::Thematiq::Transfer.export` from
	 * `ConductionNL/openproject-thematiq` development at `e550f1b8`, in a throwaway Ruby container.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function pluginFiles(): array {
		return ['cunningham' => ['cunningham'], 'zuiddrecht' => ['zuiddrecht']];
	}//end pluginFiles()

	/**
	 * Scenario: a set file the plugin downloads uploads into thematiq with every role at its value.
	 *
	 * @param string $id The plugin's design system.
	 *
	 * @return void
	 */
	#[DataProvider('pluginFiles')]
	public function testAPluginSetFileKeepsEveryRole(string $id): void {
		$json = (string)file_get_contents(self::appPath() . '/tests/fixtures/openproject/' . $id . '.json');
		$file = json_decode($json, true);
		$back = $this->upload(json: $json);
		$roles = $this->roles(declarations: $back['declarations']);

		foreach ($file['tokens'] as $role => $value) {
			$this->assertSame(strtolower((string)$value), strtolower((string)$roles[$role]), $role);
		}

		$this->assertSame([], $back['skipped']);

		// The dark block: every dark role under its name, and the dark vocabulary with the prefix.
		foreach ($file['dark']['tokens'] as $role => $value) {
			$this->assertSame($value, $back['dark'][OpenProjectSetFile::ROLE_TOKENS[$role]], 'dark ' . $role);
		}

		foreach (($file['dark']['vocabulary'] ?? []) as $key => $value) {
			$this->assertSame($value, $back['dark']['--nldesign-' . $key], 'dark vocabulary ' . $key);
		}
	}//end testAPluginSetFileKeepsEveryRole()

	/**
	 * What a set file holds and cannot be used is named, and other documents are not set files.
	 *
	 * @return void
	 */
	public function testWhatIsLeftOutIsNamed(): void {
		$reader = new OpenProjectSetFile();
		$skipped = [];
		$document = $reader->toDocument(
			document: [
				'id' => 'x',
				'name' => 'X',
				'tokens' => ['primary' => '#123456', 'sparkle' => '#ffffff', 'ink' => 12, 'muted' => '{a}', 'menu' => '#eeeeee'],
				'vocabulary' => ['color-focus' => '#0000ff', 'Bad_Key' => '1px', 'color-primary' => '#999999', 'brand-stripe-height' => ' '],
			],
			skipped: $skipped
		);

		$this->assertSame(
			['--nldesign-color-primary' => '#123456', '--thematiq-shell-menu' => '#eeeeee', '--nldesign-color-focus' => '#0000ff'],
			(array)$document['$extensions']['nl.conduction.thematiq']['cssOnly']
		);
		$this->assertSame('X', $document['$description']);
		$this->assertSame(
			[
				['path' => 'tokens.sparkle', 'reason' => 'unmapped'],
				['path' => 'tokens.ink', 'reason' => 'value-not-a-css-value'],
				['path' => 'tokens.muted', 'reason' => 'value-not-a-css-value'],
				['path' => 'vocabulary.Bad_Key', 'reason' => 'unmapped'],
				['path' => 'vocabulary.brand-stripe-height', 'reason' => 'value-not-a-css-value'],
			],
			$skipped
		);

		$this->assertTrue($reader->isSetFile(document: ['id' => 'x', 'tokens' => []]));
		$this->assertFalse($reader->isSetFile(document: ['id' => 'x', 'tokens' => [], 'extra' => 1]), 'an unknown key');
		$this->assertFalse($reader->isSetFile(document: ['tokens' => []]), 'no id');
		$this->assertFalse($reader->isSetFile(document: ['id' => 'x', 'tokens' => '#fff']), 'tokens not an object');
	}//end testWhatIsLeftOutIsNamed()

	/**
	 * Any content that is not a set file reaches the converter as it was.
	 *
	 * @return void
	 */
	public function testOtherContentIsNotTouched(): void {
		$skipped = [];
		$reader = new OpenProjectSetFile();

		foreach ([':root { --nldesign-color-primary: #123456; }', '{"color": {"primary": {"$value": "#123456"}}}', '{not json', ''] as $content) {
			$this->assertSame($content, $reader->normalise(content: $content, skipped: $skipped));
		}

		$this->assertSame([], $skipped);
	}//end testOtherContentIsNotTouched()

	/**
	 * An uploaded set's file names no logo, and reads its own files from the store.
	 *
	 * @return void
	 */
	public function testAnUploadedSetNamesNoLogo(): void {
		$file = $this->writer()->setFile(appPath: self::appPath(), id: 'custom-does-not-ship', name: 'Eigen', store: null);

		$this->assertSame(OpenProjectSetFileWriter::NO_LOGO, $file['logo']);
		$this->assertSame('Eigen', $file['name']);
		$this->assertArrayNotHasKey('dark', $file);
	}//end testAnUploadedSetNamesNoLogo()
}//end class
