#!/usr/bin/env node

/**
 * Build Fonts Script
 *
 * Copies Fira Sans fonts from node_modules to css/fonts directory
 * and generates the fonts.css file with @font-face declarations.
 */

const fs = require('fs')
const path = require('path')

const FONTS_DIR = path.join(__dirname, '..', 'css', 'fonts')
const FONTS_CSS = path.join(__dirname, '..', 'css', 'fonts.css')
const FONTSOURCE_DIR = path.join(
	__dirname,
	'..',
	'node_modules',
	'@fontsource',
	'fira-sans',
	'files',
)

// Create fonts directory if it doesn't exist.
if (!fs.existsSync(FONTS_DIR)) {
	fs.mkdirSync(FONTS_DIR, { recursive: true })
	console.log('✓ Created css/fonts directory')
}

// Copy font files from node_modules.
const fontFiles = [
	'fira-sans-latin-400-normal.woff2',
	'fira-sans-latin-400-normal.woff',
	'fira-sans-latin-400-italic.woff2',
	'fira-sans-latin-400-italic.woff',
	'fira-sans-latin-500-normal.woff2',
	'fira-sans-latin-500-normal.woff',
	'fira-sans-latin-500-italic.woff2',
	'fira-sans-latin-500-italic.woff',
	'fira-sans-latin-600-normal.woff2',
	'fira-sans-latin-600-normal.woff',
	'fira-sans-latin-600-italic.woff2',
	'fira-sans-latin-600-italic.woff',
	'fira-sans-latin-700-normal.woff2',
	'fira-sans-latin-700-normal.woff',
	'fira-sans-latin-700-italic.woff2',
	'fira-sans-latin-700-italic.woff',
]

let copiedCount = 0
if (fs.existsSync(FONTSOURCE_DIR)) {
	fontFiles.forEach((file) => {
		const sourcePath = path.join(FONTSOURCE_DIR, file)
		const destPath = path.join(FONTS_DIR, file)
		if (fs.existsSync(sourcePath)) {
			fs.copyFileSync(sourcePath, destPath)
			copiedCount++
		}
	})
	console.log(`✓ Copied ${copiedCount} font files to css/fonts/`)
} else {
	console.log('⚠ Warning: @fontsource/fira-sans not found in node_modules')
	console.log('  Run: npm install')
}

// Generate fonts.css with correct paths.
const fontsCss = `/**
 * Fira Sans Fonts
 * 
 * Open-source alternative to RijksoverheidSansWebText
 * from @rijkshuisstijl-community/font package
 */

@font-face {
    font-family: 'Fira Sans';
    src: local('Fira Sans'),
         url('fonts/fira-sans-latin-400-normal.woff2') format('woff2'),
         url('fonts/fira-sans-latin-400-normal.woff') format('woff');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Fira Sans';
    src: local('Fira Sans Italic'),
         url('fonts/fira-sans-latin-400-italic.woff2') format('woff2'),
         url('fonts/fira-sans-latin-400-italic.woff') format('woff');
    font-weight: 400;
    font-style: italic;
    font-display: swap;
}

@font-face {
    font-family: 'Fira Sans';
    src: local('Fira Sans Medium'),
         url('fonts/fira-sans-latin-500-normal.woff2') format('woff2'),
         url('fonts/fira-sans-latin-500-normal.woff') format('woff');
    font-weight: 500;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Fira Sans';
    src: local('Fira Sans Medium Italic'),
         url('fonts/fira-sans-latin-500-italic.woff2') format('woff2'),
         url('fonts/fira-sans-latin-500-italic.woff') format('woff');
    font-weight: 500;
    font-style: italic;
    font-display: swap;
}

@font-face {
    font-family: 'Fira Sans';
    src: local('Fira Sans SemiBold'),
         url('fonts/fira-sans-latin-600-normal.woff2') format('woff2'),
         url('fonts/fira-sans-latin-600-normal.woff') format('woff');
    font-weight: 600;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Fira Sans';
    src: local('Fira Sans SemiBold Italic'),
         url('fonts/fira-sans-latin-600-italic.woff2') format('woff2'),
         url('fonts/fira-sans-latin-600-italic.woff') format('woff');
    font-weight: 600;
    font-style: italic;
    font-display: swap;
}

@font-face {
    font-family: 'Fira Sans';
    src: local('Fira Sans Bold'),
         url('fonts/fira-sans-latin-700-normal.woff2') format('woff2'),
         url('fonts/fira-sans-latin-700-normal.woff') format('woff');
    font-weight: 700;
    font-style: normal;
    font-display: swap;
}

@font-face {
    font-family: 'Fira Sans';
    src: local('Fira Sans Bold Italic'),
         url('fonts/fira-sans-latin-700-italic.woff2') format('woff2'),
         url('fonts/fira-sans-latin-700-italic.woff') format('woff');
    font-weight: 700;
    font-style: italic;
    font-display: swap;
}
`

fs.writeFileSync(FONTS_CSS, fontsCss)
console.log('✓ Generated css/fonts.css')

console.log('\n✅ Font build complete!')
console.log(`   ${copiedCount} font files copied to css/fonts/`)
