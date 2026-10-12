<?php

/**
 * The optional action colour: unset, nothing changes; set, the main button follows it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/action-colour-token/specs/action-colour/spec.md#requirement-a-set-without-an-action-colour-renders-as-before
 * @spec openspec/changes/action-colour-token/specs/action-colour/spec.md#requirement-a-token-set-may-declare-an-action-colour
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\DarkPaletteService;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use OCA\Thematiq\Service\TokenSetPreviewService;
use OCP\App\IAppManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * `--nldesign-color-action` (with `-hover` and `-text`) is declared by a token
 * set that wants its main button apart from its primary, and by no other. It
 * is never given a default. Every stylesheet that reads it writes
 * `var(--nldesign-color-action…, <what the line was before>)`, so for a set
 * without the token the old value is still the value.
 *
 * That is the property this class pins, three ways: each reading line names
 * its old value as the fallback, no reading line is without a fallback, and
 * no shipped set declares the token. The services that resolve a set are then
 * run over every shipped set, with and without the token.
 *
 * No Nextcloud runtime is needed: the files are this repository's own.
 *
 * @spec openspec/changes/action-colour-token/specs/action-colour/spec.md#requirement-a-set-without-an-action-colour-renders-as-before
 */
class ActionColourTokenTest extends TestCase {

	/**
	 * The hover step every bundle declares the same way.
	 *
	 * @var string
	 */
	private const HOVER_STEP = 'var( --nldesign-color-action-hover, color-mix(in srgb, var(--nldesign-color-action) 85%, black) )';

	/**
	 * The shipped sets that declare an action colour, and the colour. Every
	 * other set must declare none, and render as it always did.
	 *
	 * @var array<string, string>
	 */
	private const SETS_WITH_AN_ACTION_COLOUR = ['cunningham' => '#1f883d'];

	/**
	 * What each reading declaration must be: file => property => value, white space collapsed.
	 *
	 * The inner `var()` of each value is the whole value the line had before the action colour existed.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const READERS = [
		'css/systems/nldesign/overrides.css' => [
			'--thematiq-action-hover' => self::HOVER_STEP,
			'--color-primary-element' => 'var( --nldesign-color-action, var(--nldesign-color-primary) )',
			'--color-primary-element-hover' => 'var( --thematiq-action-hover, var(--nldesign-color-primary-hover) )',
			'--color-primary-element-text' => 'var( --nldesign-color-action-text, var(--nldesign-color-primary-text) )',
		],
		'css/systems/nldesign/theme.css' => [
			'--thematiq-action-hover' => self::HOVER_STEP,
			'--color-primary-element' => 'var( --nldesign-color-action, var(--nldesign-color-primary) )',
			'--color-primary-element-hover' => 'var( --thematiq-action-hover, var(--nldesign-color-primary-hover) )',
			'--color-primary-element-text' => 'var( --nldesign-color-action-text, var(--nldesign-color-primary-text) )',
		],
		'css/systems/nldesign/utrecht-bridge.css' => [
			'--nldesign-component-button-primary-action-background-color'
				=> 'var( --nldesign-color-action, var( --utrecht-button-primary-action-background-color, var(--nldesign-color-primary) ) )',
			'--nldesign-component-button-primary-action-color'
				=> 'var( --nldesign-color-action-text, var( --utrecht-button-primary-action-color, var(--nldesign-color-primary-text) ) )',
			'--nldesign-component-button-primary-action-border-color'
				=> 'var( --nldesign-color-action, var( --utrecht-button-primary-action-border-color, var(--nldesign-color-primary) ) )',
			'--nldesign-component-button-primary-action-hover-background-color'
				=> 'var( --thematiq-action-hover, var( --utrecht-button-primary-action-hover-background-color, var(--nldesign-color-primary-hover) ) )',
		],
		'css/systems/nldesign/defaults.css' => [
			'--nldesign-component-button-primary-action-background-color' => 'var( --nldesign-color-action, var(--nldesign-color-primary) )',
			'--nldesign-component-button-primary-action-color' => 'var( --nldesign-color-action-text, var(--nldesign-color-primary-text) )',
			'--nldesign-component-button-primary-action-border-color' => 'var( --nldesign-color-action, var(--nldesign-color-primary) )',
			'--nldesign-component-button-primary-action-hover-background-color'
				=> 'var( --nldesign-color-action-hover, var(--nldesign-color-primary-hover) )',
			'--nldesign-component-button-primary-action-hover-color' => 'var( --nldesign-color-action-text, var(--nldesign-color-primary-text) )',
		],
		'css/systems/lasuite/bridge.css' => [
			'--thematiq-action-hover' => self::HOVER_STEP,
			'--color-primary-element' => 'var( --nldesign-color-action, var(--lasuite-color-brand-550) )',
			'--color-primary-element-hover' => 'var( --thematiq-action-hover, var(--lasuite-color-brand-650) )',
			'--color-primary-element-text' => 'var( --nldesign-color-action-text, var(--lasuite-color-brand-050) )',
		],
	];

	/**
	 * The repository root.
	 *
	 * @return string The path.
	 */
	private static function root(): string {
		return dirname(__DIR__, 2);
	}//end root()

	/**
	 * A stylesheet with its comments removed.
	 *
	 * @param string $file The path under the repository root.
	 *
	 * @return string The CSS.
	 */
	private function css(string $file): string {
		return (string)preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents(self::root() . '/' . $file));
	}//end css()

	/**
	 * Every value a file declares for one property, white space collapsed and `!important` dropped.
	 *
	 * @param string $css The CSS.
	 * @param string $property The property name.
	 *
	 * @return string[] The values, in file order.
	 */
	private function valuesOf(string $css, string $property): array {
		preg_match_all('/(?<![\w-])' . preg_quote($property, '/') . '\s*:\s*([^;{}]+);/', $css, $matches);

		return array_map(
			static fn (string $value): string => trim((string)preg_replace('/\s+/', ' ', str_replace('!important', '', $value))),
			$matches[1]
		);
	}//end valuesOf()

	/**
	 * A shipped set's declarations, resolved the way the audit and the shell tokens resolve them.
	 *
	 * @param string $id The token set id.
	 *
	 * @return array<string, string> The declarations.
	 */
	private function resolved(string $id): array {
		$resolver = new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService());

		return $resolver->resolveDeclarations(appPath: self::root(), id: $id, theming: []);
	}//end resolved()

	/**
	 * Every shipped token set id.
	 *
	 * @return array<string, array{0: string}> The ids, by id.
	 */
	public static function shippedSetIds(): array {
		$ids = [];
		foreach ((array)glob(self::root() . '/css/tokens/*.css') as $file) {
			$id = basename((string)$file, '.css');
			$ids[$id] = [$id];
		}

		return $ids;
	}//end shippedSetIds()

	/**
	 * Each reading line is the action token with the line's old value as its fallback.
	 *
	 * @return void
	 */
	public function testEveryReaderFallsBackToItsOldValue(): void {
		foreach (self::READERS as $file => $declarations) {
			$css = $this->css(file: $file);
			foreach ($declarations as $property => $expected) {
				$values = $this->valuesOf(css: $css, property: $property);

				$this->assertNotSame([], $values, $file . ' no longer declares ' . $property);
				foreach ($values as $value) {
					$this->assertSame($expected, $value, $file . ' ' . $property);
				}
			}
		}
	}//end testEveryReaderFallsBackToItsOldValue()

	/**
	 * Nowhere is the action token read without a fallback, and nowhere is it given a value.
	 *
	 * A read without a fallback would leave a declaration invalid for every
	 * set there is. A declared default would be resolved where it is declared,
	 * on `:root`, and a dark variant puts its colours on `body`: the default
	 * would then hand the light primary to a dark page.
	 *
	 * @return void
	 */
	public function testTheTokenIsOptionalEverywhere(): void {
		$files = array_merge(
			(array)glob(self::root() . '/css/*.css'),
			(array)glob(self::root() . '/css/systems/*/*.css')
		);

		$reads = 0;
		foreach ($files as $path) {
			$file = substr((string)$path, (strlen(self::root()) + 1));
			$css = $this->css(file: $file);

			$this->assertDoesNotMatchRegularExpression('/--nldesign-color-action[\w-]*\s*:/', $css, $file . ' declares the action colour');

			// The one read with nothing behind it is the hover step's own
			// mix, which is meant to be invalid without an action colour.
			$css = str_replace('color-mix(in srgb, var(--nldesign-color-action) 85%, black)', '', $css);
			$this->assertDoesNotMatchRegularExpression(
				'/var\(\s*(--nldesign-color-action[\w-]*|--thematiq-action-hover)\s*\)/',
				$css,
				$file . ' reads the action colour without a fallback'
			);
			$reads += preg_match_all('/var\(\s*(--nldesign-color-action[\w-]*|--thematiq-action-hover)\s*,/', $css);
		}

		$this->assertGreaterThanOrEqual(20, $reads, 'the readers this test guards are gone');
	}//end testTheTokenIsOptionalEverywhere()

	/**
	 * Only the sets named in {@see self::SETS_WITH_AN_ACTION_COLOUR} declare the action colour, in their file, dark variant or overrides.
	 *
	 * @return void
	 */
	public function testNoShippedSetDeclaresAnActionColour(): void {
		$files = array_merge(
			(array)glob(self::root() . '/css/tokens/*.css'),
			(array)glob(self::root() . '/css/tokens/dark/*.css'),
			(array)glob(self::root() . '/css/token-overrides/*.css')
		);

		$this->assertGreaterThan(100, count($files));
		$declaring = [];
		foreach ($files as $path) {
			$this->assertStringNotContainsString('--thematiq-action-hover', (string)file_get_contents((string)$path), (string)$path);
			if (str_contains((string)file_get_contents((string)$path), '--nldesign-color-action') === true) {
				$declaring[basename((string)$path, '.css')] = true;
			}
		}

		ksort($declaring);
		$this->assertSame(array_keys(self::SETS_WITH_AN_ACTION_COLOUR), array_keys($declaring), 'a set gained or lost an action colour: that is a visible decision, name it here');
	}//end testNoShippedSetDeclaresAnActionColour()

	/**
	 * For every shipped set the resolvers answer what they answered before.
	 *
	 * The set has no action colour, so its action is its primary: in the
	 * resolved declarations, in the shell roles, in the contrast warnings and
	 * in the main button's component tokens.
	 *
	 * @param string $id The token set id.
	 *
	 * @return void
	 */
	#[DataProvider('shippedSetIds')]
	public function testAShippedSetResolvesAsBefore(string $id): void {
		$declared = $this->resolved(id: $id);

		if (isset(self::SETS_WITH_AN_ACTION_COLOUR[$id]) === true) {
			// A set with an action colour: the action is that colour, its primary is not, and the text on it reads.
			$roles = (new ShellRoleMapper(colours: new ContrastService()))->map(tokens: $declared);
			$this->assertSame(self::SETS_WITH_AN_ACTION_COLOUR[$id], $roles['action'], $id);
			$this->assertNotSame($roles['primary'], $roles['action'], $id);
			$this->assertSame($declared['--nldesign-color-primary'], $roles['primary'], $id);
			$this->assertSame([], (new ContrastService())->check(declarations: $declared), $id . ': the text on the action colour must reach 4.5:1');

			return;
		}

		$this->assertArrayNotHasKey('--nldesign-color-action', $declared, $id);
		$this->assertArrayNotHasKey('--nldesign-color-action-hover', $declared, $id);
		$this->assertArrayNotHasKey('--nldesign-color-action-text', $declared, $id);

		// The main button's tokens still end at the primary (or at the set's own value).
		$own = ((new CssParserService())->parseDeclarations(content: (string)file_get_contents(self::root() . '/css/tokens/' . $id . '.css')) ?? []);
		$button = [
			'--nldesign-component-button-primary-action-background-color' => '--nldesign-color-primary',
			'--nldesign-component-button-primary-action-border-color' => '--nldesign-color-primary',
			'--nldesign-component-button-primary-action-color' => '--nldesign-color-primary-text',
			'--nldesign-component-button-primary-action-hover-background-color' => '--nldesign-color-primary-hover',
			'--nldesign-component-button-primary-action-hover-color' => '--nldesign-color-primary-text',
		];
		foreach ($button as $token => $primary) {
			if (isset($own[$token]) === false) {
				$this->assertSame($declared[$primary], $declared[$token], $id . ' ' . $token);
			}
		}

		// The contrast warnings hold no action pair.
		foreach ((new ContrastService())->check(declarations: $declared) as $warning) {
			$this->assertStringNotContainsString('action', $warning['pair'], $id);
		}

		// The shell roles: the action is what a set without the token always got.
		$roles = (new ShellRoleMapper(colours: new ContrastService()))->map(tokens: $declared);
		$before = (new ShellRoleMapper(colours: new ContrastService()))->map(
			tokens: array_diff_key($declared, ['--nldesign-color-action' => 1, '--nldesign-color-action-hover' => 1])
		);
		$this->assertSame($before, $roles, $id);
		if (isset($declared['--summer-color-primary']) === false) {
			$this->assertSame($declared['--nldesign-color-primary'], $roles['action'], $id);
		}
	}//end testAShippedSetResolvesAsBefore()

	/**
	 * The variable-to-token map of the preview is what it was: the element variables still map to the primary.
	 *
	 * @return void
	 */
	public function testThePreviewMapIsUnchanged(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(self::root());
		$preview = new TokenSetPreviewService($appManager);

		$sources = $preview->getTokenSources();
		$this->assertSame('--nldesign-color-primary', $sources['--color-primary-element']);
		$this->assertSame('--nldesign-color-primary-hover', $sources['--color-primary-element-hover']);
		$this->assertSame('--nldesign-color-primary-text', $sources['--color-primary-element-text']);
		$this->assertSame('--nldesign-color-primary', $sources['--color-primary']);
		$this->assertNotContains('--nldesign-color-action', $sources);

		$colours = $preview->getResolvedColors(tokenSetId: 'zuiddrecht');
		$this->assertSame('#3669A5', $colours['--color-primary-element']);
		$this->assertSame('#234A78', $colours['--color-primary-element-hover']);
		$this->assertSame('#ffffff', $colours['--color-primary-element-text']);
	}//end testThePreviewMapIsUnchanged()

	/**
	 * A forced regeneration of a shipped set's dark variant gives the committed file.
	 *
	 * The dark generator reads `defaults.css`, whose main button tokens now
	 * name the action colour first. Without the token they must resolve to the
	 * same literals, and so to the same dark file.
	 *
	 * @return void
	 */
	public function testTheDarkVariantsAreUnchanged(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn(self::root());
		$generator = new DarkPaletteService(new ContrastService(), new CssParserService(), $appManager, $this->createMock(LoggerInterface::class));

		foreach (['zuiddrecht', 'utrecht', 'rijkshuisstijl', 'conduction-new', 'wilgenboom'] as $id) {
			$result = $generator->generateForSet(setId: $id);
			$this->assertNotNull($result, $id);

			$this->assertSame((string)file_get_contents(self::root() . '/css/tokens/dark/' . $id . '.css'), $result['css'], $id);
		}
	}//end testTheDarkVariantsAreUnchanged()

	/**
	 * A set that declares an action colour gets it in the preview and in its dark variant.
	 *
	 * @return void
	 */
	public function testASetWithAnActionColourIsFollowed(): void {
		$generator = new DarkPaletteService(
			new ContrastService(),
			new CssParserService(),
			$this->createMock(IAppManager::class),
			$this->createMock(LoggerInterface::class)
		);
		$light = array_merge(
			((new CssParserService())->parseDeclarations(content: (string)file_get_contents(self::root() . '/css/systems/nldesign/defaults.css')) ?? []),
			['--nldesign-color-primary' => '#3669a5', '--nldesign-color-action' => '#1f883d', '--nldesign-color-action-text' => '#ffffff']
		);

		$dark = $generator->deriveDarkDeclarations(lightDeclarations: $light);
		$withoutAction = $generator->deriveDarkDeclarations(lightDeclarations: array_diff_key($light, ['--nldesign-color-action' => 1]));

		$this->assertArrayHasKey('--nldesign-color-action', $dark, 'the dark variant carries the action colour');
		$this->assertNotSame(
			$withoutAction['--nldesign-component-button-primary-action-background-color'],
			$dark['--nldesign-component-button-primary-action-background-color'],
			'the dark main button is derived from the action colour, not from the primary'
		);
	}//end testASetWithAnActionColourIsFollowed()

	/**
	 * The preview of a set with an action colour shows the element variables in that colour.
	 *
	 * @return void
	 */
	public function testThePreviewShowsASetsActionColour(): void {
		$appPath = sys_get_temp_dir() . '/thematiq-action-' . uniqid();
		mkdir($appPath . '/css/systems/nldesign', 0777, true);
		mkdir($appPath . '/css/tokens', 0777, true);
		foreach (['defaults', 'overrides'] as $sheet) {
			copy(self::root() . '/css/systems/nldesign/' . $sheet . '.css', $appPath . '/css/systems/nldesign/' . $sheet . '.css');
		}

		file_put_contents(
			$appPath . '/css/tokens/green-button.css',
			":root {\n\t--nldesign-color-primary: #1a67a3;\n\t--nldesign-color-action: #1f883d;\n\t--nldesign-color-action-hover: #197032;\n}\n"
		);
		file_put_contents($appPath . '/css/tokens/plain.css', ":root {\n\t--nldesign-color-primary: #1a67a3;\n\t--nldesign-color-primary-hover: #124872;\n}\n");

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppPath')->willReturn($appPath);

		$green = (new TokenSetPreviewService($appManager))->getResolvedColors(tokenSetId: 'green-button');
		$plain = (new TokenSetPreviewService($appManager))->getResolvedColors(tokenSetId: 'plain');

		foreach (['defaults', 'overrides'] as $sheet) {
			unlink($appPath . '/css/systems/nldesign/' . $sheet . '.css');
		}

		unlink($appPath . '/css/tokens/green-button.css');
		unlink($appPath . '/css/tokens/plain.css');
		rmdir($appPath . '/css/tokens');
		rmdir($appPath . '/css/systems/nldesign');
		rmdir($appPath . '/css/systems');
		rmdir($appPath . '/css');
		rmdir($appPath);

		$this->assertSame('#1a67a3', $green['--color-primary'], 'the primary stays the primary');
		$this->assertSame('#1f883d', $green['--color-primary-element']);
		$this->assertSame('#197032', $green['--color-primary-element-hover']);
		$this->assertSame('#ffffff', $green['--color-primary-element-text'], 'no action text declared: the text on the primary');

		$this->assertSame('#1a67a3', $plain['--color-primary-element']);
		$this->assertSame('#124872', $plain['--color-primary-element-hover']);
	}//end testThePreviewShowsASetsActionColour()

	/**
	 * The text on the action colour is checked like the text on the primary, once a set moves it.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/action-colour-token/specs/action-colour/spec.md#requirement-the-text-on-the-action-colour-is-checked
	 */
	public function testTheTextOnTheActionColourIsChecked(): void {
		$contrast = new ContrastService();
		$set = ['--nldesign-color-primary' => '#154273', '--nldesign-color-primary-text' => '#ffffff', '--nldesign-color-background' => '#ffffff'];
		$actionWarnings = static fn (array $warnings): array => array_values(
			array_filter($warnings, static fn (array $warning): bool => str_contains($warning['pair'], 'action'))
		);

		$this->assertSame([], $contrast->check(declarations: $set), 'a set without an action colour: no warning at all');
		$this->assertSame(
			[],
			$contrast->check(declarations: ($set + ['--nldesign-color-action' => '#154273', '--nldesign-color-action-text' => '#ffffff'])),
			'an action pair equal to the primary pair is the primary pair'
		);
		$this->assertSame([], $contrast->check(declarations: ($set + ['--nldesign-color-action' => '#1a7f37'])), 'white on a dark green reads');

		// White on a light green: the set moved the fill and kept the primary's text.
		$pale = $actionWarnings($contrast->check(declarations: ($set + ['--nldesign-color-action' => '#7bd88f'])));
		$this->assertCount(1, $pale);
		$this->assertSame('--nldesign-color-action-text vs --nldesign-color-action', $pale[0]['pair']);
		$this->assertSame(4.5, $pale[0]['threshold']);
		$this->assertLessThan(4.5, $pale[0]['ratio']);

		// Only the text moved: grey on the primary.
		$grey = $actionWarnings($contrast->check(declarations: ($set + ['--nldesign-color-action-text' => '#5a6b7d'])));
		$this->assertCount(1, $grey);

		// A value that cannot be measured is never a pass.
		$unknown = $actionWarnings($contrast->check(declarations: ($set + ['--nldesign-color-action' => 'var(--brand-green)'])));
		$this->assertCount(1, $unknown);
		$this->assertTrue($unknown[0]['unevaluated']);
	}//end testTheTextOnTheActionColourIsChecked()
}//end class
