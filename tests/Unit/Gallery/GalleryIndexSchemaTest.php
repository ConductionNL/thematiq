<?php

/**
 * The shipped gallery index validates against its JSON Schema, and the schema and the
 * runtime entry check agree on what a valid entry is.
 *
 * @category Test
 * @package  OCA\Thematiq\Tests\Unit\Gallery
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/theme-gallery/spec.md
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Gallery;

use OCA\Thematiq\Service\ThemeGalleryService;
use OCA\Thematiq\Tests\Unit\Service\ThemeGalleryServiceTest;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

// The entry fixture lives in ThemeGalleryServiceTest; tests/ is not autoloaded.
require_once __DIR__ . '/../Service/ThemeGalleryServiceTest.php';

/**
 * Tests for gallery/index.json and gallery/index.schema.json.
 */
class GalleryIndexSchemaTest extends TestCase {

	/**
	 * Validate a decoded document against the shipped schema.
	 *
	 * @param mixed $document The document, decoded to objects.
	 *
	 * @return boolean Whether it is valid.
	 */
	private function valid($document): bool {
		$schema = json_decode((string)file_get_contents(\dirname(__DIR__, 3) . '/gallery/index.schema.json'));
		return (new Validator())->validate($document, $schema)->isValid();
	}//end valid()

	/**
	 * The shipped index is valid.
	 *
	 * @return void
	 */
	public function testTheShippedIndexIsValid(): void {
		$index = json_decode((string)file_get_contents(\dirname(__DIR__, 3) . '/gallery/index.json'));
		$this->assertNotNull($index, 'gallery/index.json is not JSON');
		$this->assertTrue($this->valid($index));
	}//end testTheShippedIndexIsValid()

	/**
	 * The schema accepts what the runtime lists and refuses what it drops.
	 *
	 * @return void
	 */
	public function testTheSchemaAndTheRuntimeAgree(): void {
		$noLicence = ThemeGalleryServiceTest::entry();
		unset($noLicence['licence']);
		$cases = [
			'valid' => [ThemeGalleryServiceTest::entry(), true],
			'no licence' => [$noLicence, false],
			'short hash' => [ThemeGalleryServiceTest::entry(['sha256' => 'abc']), false],
			'plain http' => [ThemeGalleryServiceTest::entry(['fileUrl' => 'http://x.example/x.css']), false],
			'named colour' => [ThemeGalleryServiceTest::entry(['swatches' => ['primary' => 'red', 'background' => '#fff', 'text' => '#000']]), false],
			'spaced id' => [ThemeGalleryServiceTest::entry(['id' => 'Bad Id']), false],
			'unknown format' => [ThemeGalleryServiceTest::entry(['format' => 'scss']), false],
		];

		foreach ($cases as $label => [$entry, $expected]) {
			$document = json_decode((string)json_encode(['entries' => [$entry]]));
			$this->assertSame($expected, $this->valid($document), "schema on: {$label}");
			$this->assertSame($expected, ThemeGalleryService::validateEntry(raw: $entry) !== null, "runtime on: {$label}");
		}
	}//end testTheSchemaAndTheRuntimeAgree()
}//end class
