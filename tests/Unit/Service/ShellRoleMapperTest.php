<?php

/**
 * A token set becomes eighteen shell roles, and no role is ever empty.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @category  Test
 * @package   OCA\Thematiq\Tests\Unit\Service
 * @author    Conduction <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://github.com/ConductionNL/thematiq
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-every-shell-role-always-has-a-value
 */

declare(strict_types=1);

namespace OCA\Thematiq\Tests\Unit\Service;

use OCA\Thematiq\Service\ContrastService;
use OCA\Thematiq\Service\CssParserService;
use OCA\Thematiq\Service\ShellRoleMapper;
use OCA\Thematiq\Service\ShippedTokenSetAuditService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The mapper is pure, so most cases hand it a map and read the roles back.
 * Two cases read the token files this app ships, through the resolver the
 * endpoint uses, so the mapping is checked against real declarations and not
 * only against the names this test happens to spell.
 *
 * @spec openspec/changes/shell-tokens-endpoint/specs/shell-tokens/spec.md#requirement-every-shell-role-always-has-a-value
 */
class ShellRoleMapperTest extends TestCase {

	/**
	 * The mapper under test.
	 *
	 * @return ShellRoleMapper The mapper.
	 */
	private function mapper(): ShellRoleMapper {
		return new ShellRoleMapper(colours: new ContrastService());
	}//end mapper()

	/**
	 * The app directory this test runs in.
	 *
	 * @return string The path.
	 */
	private static function appPath(): string {
		return dirname(__DIR__, 3);
	}//end appPath()

	/**
	 * A shipped set's declarations, resolved the way the endpoint resolves them.
	 *
	 * @param string $id The token set id.
	 *
	 * @return array<string, string> The declarations.
	 */
	private function shipped(string $id): array {
		$resolver = new ShippedTokenSetAuditService(contrast: new ContrastService(), parser: new CssParserService());

		return $resolver->resolveDeclarations(appPath: self::appPath(), id: $id, theming: []);
	}//end shipped()

	/**
	 * Every shipped token set id.
	 *
	 * @return array<string, array{0: string}> The ids, by id.
	 */
	public static function shippedSetIds(): array {
		$ids = [];
		foreach ((array)glob(self::appPath() . '/css/tokens/*.css') as $file) {
			$id = basename((string)$file, '.css');
			$ids[$id] = [$id];
		}

		return $ids;
	}//end shippedSetIds()

	/**
	 * With nothing declared, every role takes its documented default.
	 *
	 * @return void
	 */
	public function testAnEmptySetGetsTheDocumentedDefaults(): void {
		$roles = $this->mapper()->map(tokens: []);

		$this->assertSame(expected: ShellRoleMapper::ROLES, actual: array_keys($roles));
		$this->assertSame(expected: ShellRoleMapper::DEFAULTS, actual: $roles);
		$this->assertCount(expectedCount: 19, haystack: $roles);
	}//end testAnEmptySetGetsTheDocumentedDefaults()

	/**
	 * Gemeente Zuiddrecht, the set the shell roles were drawn for, maps onto its own colours.
	 *
	 * @return void
	 */
	public function testZuiddrechtMapsOntoItsOwnColours(): void {
		$roles = $this->mapper()->map(tokens: $this->shipped(id: 'zuiddrecht'));

		$font = $roles['font-family'];
		unset($roles['font-family']);
		$this->assertSame(expected: '#ffffff', actual: $roles['menu'], message: 'zuiddrecht keeps the white navigation column of the defaults');
		unset($roles['menu']);

		$this->assertSame(
			expected: [
				'primary' => '#3669A5',
				'primary-deep' => '#234A78',
				'primary-light' => '#EAF0F7',
				'action' => '#3669A5',
				'action-deep' => '#234A78',
				'link' => '#3669A5',
				'accent' => '#CC0000',
				'ink' => '#1B1C1D',
				'text' => '#3d3f43',
				'muted' => '#5E6168',
				'line' => '#E4E6EA',
				'soft' => '#EDEFF2',
				'ground' => '#F5F6F8',
				'chip' => '#F0F1F3',
				'field' => '#828A95',
				'radius' => '8px',
				'radius-lg' => '12px',
			],
			actual: $roles
		);
		$this->assertStringStartsWith(prefix: "'Fira Sans', ", string: $font);
		$this->assertStringNotContainsString(needle: "\n", haystack: $font);
	}//end testZuiddrechtMapsOntoItsOwnColours()

	/**
	 * Every set this app ships gets all eighteen roles, each a usable value.
	 *
	 * @param string $id The token set id.
	 *
	 * @return void
	 */
	#[DataProvider('shippedSetIds')]
	public function testEveryShippedSetGetsEveryRole(string $id): void {
		$roles = $this->mapper()->map(tokens: $this->shipped(id: $id));

		$this->assertSame(expected: ShellRoleMapper::ROLES, actual: array_keys($roles), message: $id);
		foreach ($roles as $role => $value) {
			$this->assertNotSame(expected: '', actual: $value, message: $id . ' ' . $role);
			$this->assertStringNotContainsString(needle: 'var(', haystack: $value, message: $id . ' ' . $role);
			$this->assertDoesNotMatchRegularExpression(pattern: '/[;{}]/', string: $value, message: $id . ' ' . $role);
		}

		foreach (['radius', 'radius-lg'] as $role) {
			$this->assertMatchesRegularExpression(pattern: '/^[\d.]+(px|rem|em|%)$/', string: $roles[$role], message: $id . ' ' . $role);
		}
	}//end testEveryShippedSetGetsEveryRole()

	/**
	 * An administrator's own value of a Nextcloud variable wins over the set's token.
	 *
	 * @return void
	 */
	public function testAnAdministratorsOwnValueWins(): void {
		$roles = $this->mapper()->map(
			tokens: [
				'--nldesign-color-primary' => '#3669a5',
				'--nldesign-color-text' => '#1b1c1d',
				'--nldesign-border-radius' => '8px',
				'--nldesign-font-family' => "'Fira Sans', sans-serif",
			],
			overrides: [
				'--color-primary' => '#aa0000',
				'--color-primary-element' => '#00aa00',
				'--nldesign-component-button-primary-action-background-color' => '#aa00aa',
				'--color-main-text' => '#000000',
				'--border-radius' => '2px',
				'--font-face' => 'Inter, sans-serif',
			]
		);

		$this->assertSame(expected: '#aa0000', actual: $roles['primary']);
		$this->assertSame(expected: '#aa00aa', actual: $roles['action'], message: 'the main button follows its own token, not the element variable');
		$this->assertSame(expected: '#000000', actual: $roles['ink']);
		$this->assertSame(expected: '2px', actual: $roles['radius']);
		$this->assertSame(expected: 'Inter, sans-serif', actual: $roles['font-family']);
	}//end testAnAdministratorsOwnValueWins()

	/**
	 * The deep roles are the dark step: a hover colour lighter than its base is not taken.
	 *
	 * @return void
	 */
	public function testALighterHoverIsNotTakenAsTheDeepColour(): void {
		$roles = $this->mapper()->map(
			tokens: [
				'--nldesign-color-primary' => '#154273',
				'--nldesign-color-primary-hover' => '#1d5499',
			]
		);

		$this->assertSame(expected: '#0f2e51', actual: $roles['primary-deep']);
		$this->assertSame(expected: '#0f2e51', actual: $roles['action-deep']);
	}//end testALighterHoverIsNotTakenAsTheDeepColour()

	/**
	 * Roles without a token of their own are derived from the roles next to them.
	 *
	 * @return void
	 */
	public function testMissingRolesAreDerived(): void {
		$roles = $this->mapper()->map(
			tokens: [
				'--nldesign-color-primary' => '#3669a5',
				'--nldesign-color-text' => '#000000',
				'--nldesign-color-text-muted' => '#808080',
				'--nldesign-color-border' => '#cccccc',
				'--nldesign-border-radius' => '4px',
			]
		);

		$this->assertSame(expected: '#3669a5', actual: $roles['action'], message: 'the action colour is the primary colour');
		$this->assertSame(expected: '#3669a5', actual: $roles['link'], message: 'a link without its own colour is primary');
		$this->assertSame(expected: '#ebf0f6', actual: $roles['primary-light'], message: 'primary on white, one part in ten');
		$this->assertSame(expected: '#404040', actual: $roles['text'], message: 'halfway between ink and muted');
		$this->assertSame(expected: '#cccccc', actual: $roles['field'], message: 'a field without its own border wears the line colour');
		$this->assertSame(expected: '4px', actual: $roles['radius-lg'], message: 'the large radius falls back to the radius');
	}//end testMissingRolesAreDerived()

	/**
	 * A value a consumer cannot write into a stylesheet is passed over.
	 *
	 * @return void
	 */
	public function testAnUnusableValueIsPassedOver(): void {
		$roles = $this->mapper()->map(
			tokens: [
				'--nldesign-color-primary' => 'var(--brand-that-nothing-declares)',
				'--nldesign-color-link' => 'red; } body { display: none',
				'--nldesign-color-border' => 'url(https://example.org/x.png)',
				'--nldesign-color-text' => '   ',
				'--nldesign-font-family' => "'Fira Sans',\n\t\tArial,\n\t\tsans-serif",
				'--nldesign-border-radius' => '0',
			]
		);

		$this->assertSame(expected: ShellRoleMapper::DEFAULTS['primary'], actual: $roles['primary']);
		$this->assertSame(expected: ShellRoleMapper::DEFAULTS['primary'], actual: $roles['link']);
		$this->assertSame(expected: ShellRoleMapper::DEFAULTS['line'], actual: $roles['line']);
		$this->assertSame(expected: ShellRoleMapper::DEFAULTS['ink'], actual: $roles['ink']);
		$this->assertSame(expected: "'Fira Sans', Arial, sans-serif", actual: $roles['font-family']);
		$this->assertSame(expected: '0px', actual: $roles['radius'], message: 'a bare 0 is given its unit');
	}//end testAnUnusableValueIsPassedOver()

	/**
	 * A colour the mapper cannot do sums on is passed on as it is.
	 *
	 * The deep and light steps are mixed from the primary. A primary written
	 * in a notation the mapper does not parse has no mix, so the deep steps
	 * stay on the colour itself and the light step takes the default.
	 *
	 * @return void
	 */
	public function testAColourThatCannotBeMixedIsNotDerivedFrom(): void {
		$roles = $this->mapper()->map(tokens: ['--nldesign-color-primary' => 'oklch(0.5 0.15 250)']);

		$this->assertSame(expected: 'oklch(0.5 0.15 250)', actual: $roles['primary']);
		$this->assertSame(expected: 'oklch(0.5 0.15 250)', actual: $roles['primary-deep']);
		$this->assertSame(expected: 'oklch(0.5 0.15 250)', actual: $roles['action']);
		$this->assertSame(expected: 'oklch(0.5 0.15 250)', actual: $roles['action-deep']);
		$this->assertSame(expected: ShellRoleMapper::DEFAULTS['primary-light'], actual: $roles['primary-light']);
	}//end testAColourThatCannotBeMixedIsNotDerivedFrom()

	/**
	 * The text role is the set's own shell text when it has one, else ink and muted mixed.
	 *
	 * The OpenProject plugin writes its text value under this name, so a file from
	 * the plugin gives the same text here.
	 *
	 * @return void
	 */
	public function testTheTextRoleReadsItsOwnToken(): void {
		$tokens = ['--nldesign-color-text' => '#000000', '--nldesign-color-text-muted' => '#808080'];

		$this->assertSame(expected: '#404040', actual: $this->mapper()->map(tokens: $tokens)['text']);
		$this->assertSame(expected: '#484a4d', actual: $this->mapper()->map(tokens: ($tokens + ['--thematiq-shell-text' => '#484a4d']))['text']);
	}//end testTheTextRoleReadsItsOwnToken()

	/**
	 * The menu role is the main menu's background: the plugin's own name, then the column's
	 * token, then the set's navigation colour, then white.
	 *
	 * @return void
	 */
	public function testTheMenuIsTheNavigationColumn(): void {
		$this->assertSame(expected: '#ffffff', actual: $this->mapper()->map(tokens: [])['menu']);
		$this->assertSame(expected: '#eeeeee', actual: $this->mapper()->map(tokens: ['--nldesign-color-nav-background' => '#eeeeee'])['menu']);
		$this->assertSame(expected: '#dddddd', actual: $this->mapper()->map(tokens: ['--nldesign-color-nav-background' => '#eeeeee', '--nldesign-component-navigation-background-color' => '#dddddd'])['menu']);
		$this->assertSame(expected: '#123456', actual: $this->mapper()->map(tokens: ['--nldesign-color-nav-background' => '#eeeeee', '--thematiq-shell-menu' => '#123456'])['menu']);
	}//end testTheMenuIsTheNavigationColumn()

	/**
	 * The accent is the set's accent, and the error colour for a set without one.
	 *
	 * @return void
	 */
	public function testTheAccentFallsBackToTheErrorColour(): void {
		$this->assertSame(expected: '#f5b82e', actual: $this->mapper()->map(tokens: ['--nldesign-color-accent' => '#f5b82e', '--nldesign-color-error' => '#cc0000'])['accent']);
		$this->assertSame(expected: '#cc0000', actual: $this->mapper()->map(tokens: ['--nldesign-color-error' => '#cc0000'])['accent']);
	}//end testTheAccentFallsBackToTheErrorColour()

	/**
	 * A set's action colour is the action role, apart from its primary.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/action-colour-token/specs/action-colour/spec.md#requirement-the-shell-tokens-answer-the-action-colour
	 */
	public function testASetsActionColourIsTheActionRole(): void {
		$set = [
			'--nldesign-color-primary' => '#1a67a3',
			'--nldesign-color-primary-hover' => '#124872',
			'--nldesign-color-link' => '#175a8e',
		];

		$without = $this->mapper()->map(tokens: $set);
		$this->assertSame(expected: '#1a67a3', actual: $without['action'], message: 'no action colour: the action is the primary');
		$this->assertSame(expected: '#124872', actual: $without['action-deep']);

		$full = $this->mapper()->map(tokens: ($set + ['--nldesign-color-action' => '#1f883d', '--nldesign-color-action-hover' => '#197032']));
		$this->assertSame(expected: '#1f883d', actual: $full['action']);
		$this->assertSame(expected: '#197032', actual: $full['action-deep']);
		$this->assertSame(expected: '#1a67a3', actual: $full['primary'], message: 'the primary does not move with the action');
		$this->assertSame(expected: '#124872', actual: $full['primary-deep']);
		$this->assertSame(expected: '#175a8e', actual: $full['link'], message: 'links stay with the set');

		// Without its own hover step the action darkens by 15%, as the page
		// draws it, and never borrows the primary's blue hover.
		$alone = $this->mapper()->map(tokens: ($set + ['--nldesign-color-action' => '#1f883d']));
		$this->assertSame(expected: '#1a7434', actual: $alone['action-deep']);

		// An administrator's value for Nextcloud's element variable moves checked
		// boxes and switches, not the main button, so the action role ignores it.
		$element = $this->mapper()->map(tokens: ($set + ['--nldesign-color-action' => '#1f883d']), overrides: ['--color-primary-element' => '#aa0000']);
		$this->assertSame(expected: '#1f883d', actual: $element['action']);

		// An administrator's value for the main button's own token wins.
		$button = $this->mapper()->map(
			tokens: ($set + ['--nldesign-color-action' => '#1f883d']),
			overrides: ['--nldesign-component-button-primary-action-background-color' => '#aa0000']
		);
		$this->assertSame(expected: '#aa0000', actual: $button['action']);
		$this->assertSame(expected: '#910000', actual: $button['action-deep'], message: 'and hovers a little darker');
	}//end testASetsActionColourIsTheActionRole()

	/**
	 * On a dark page the text on the selected item's tint must read: a darker step that does not is passed over.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/shell-catalogue/spec.md#requirement-the-dark-roles-read-on-each-other
	 */
	public function testOnADarkPageTheSelectedItemsTextReads(): void {
		$dark = [
			'--nldesign-color-primary' => '#84aad7',
			'--nldesign-color-primary-hover' => '#234a78',
			'--nldesign-color-primary-light' => '#13202e',
			'--nldesign-color-text' => '#e2e3e4',
			'--nldesign-color-background' => '#171a20',
		];

		$this->assertSame(expected: '#234a78', actual: $this->mapper()->map(tokens: $dark)['primary-deep'], message: 'the plain mapping keeps the darker step');

		$roles = $this->mapper()->mapDark(tokens: $dark);
		$this->assertSame(expected: '#84aad7', actual: $roles['primary-deep'], message: 'the dark mapping writes the primary on the tint, as Nextcloud does');
		$this->assertSame(expected: '#84aad7', actual: $roles['primary']);
		$this->assertSame(expected: ShellRoleMapper::ROLES, actual: array_keys($roles));

		// A step that already reads is left alone.
		$fine = $this->mapper()->mapDark(tokens: (['--nldesign-color-primary-hover' => '#7397c4'] + $dark));
		$this->assertSame(expected: '#7397c4', actual: $fine['primary-deep']);
	}//end testOnADarkPageTheSelectedItemsTextReads()

	/**
	 * Summer Breeze keeps its own vocabulary, and is read by it.
	 *
	 * @return void
	 */
	public function testSummerBreezeIsReadByItsOwnNames(): void {
		$roles = $this->mapper()->map(tokens: $this->shipped(id: 'summer-breeze'));

		$this->assertSame(expected: '#21468b', actual: $roles['primary']);
		$this->assertSame(expected: '#1b3a75', actual: $roles['primary-deep']);
		$this->assertSame(expected: '#21468b', actual: $roles['link']);
		$this->assertSame(expected: '#02162e', actual: $roles['ink']);
		$this->assertSame(expected: '#e3e9f0', actual: $roles['line']);
		$this->assertSame(expected: '#7a8b9f', actual: $roles['field']);
		$this->assertSame(expected: '#f7f7f7', actual: $roles['ground']);
		$this->assertSame(expected: '12px', actual: $roles['radius']);
		$this->assertStringStartsWith(prefix: "'Inter', ", string: $roles['font-family']);
	}//end testSummerBreezeIsReadByItsOwnNames()
}//end class
