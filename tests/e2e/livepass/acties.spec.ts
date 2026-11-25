import { test } from '@playwright/test'
import { readFileSync } from 'fs'
const OUT = process.env.LP_OUT ?? '/tmp'
const CASE = readFileSync(
	`${process.env.HOME}/sig-lanes/livepass/thematiq-work/caseid.txt`,
	'utf8',
)
	.replace('CASEID', '')
	.trim()
test('dossiq case Acties menu: one colour, no underline', async ({ page }) => {
	test.setTimeout(180_000)
	await page.goto(`/index.php/apps/dossiq/cases/${CASE}`, { waitUntil: 'load' })
	await page.waitForTimeout(8000)
	const cancel = page.getByRole('dialog').getByRole('button', { name: 'Cancel' })
	if (await cancel.count()) {
		await cancel.first().click()
		await page.waitForTimeout(1500)
	}
	await page.keyboard.press('Escape')
	const btn = page.getByRole('button', { name: /^(Acties|Actions)$/ }).first()
	console.log(
		'BUTTONS',
		JSON.stringify(await page.getByRole('button').allInnerTexts()).slice(0, 600),
	)
	await btn.click({ timeout: 20000 })
	await page.waitForTimeout(1500)
	const items = await page.evaluate(() => {
		const pop = document.querySelector(
			'.v-popper__popper--shown, [role=menu]',
		) as HTMLElement
		const root = pop || document.body
		return {
			mainText: getComputedStyle(document.body)
				.getPropertyValue('--color-main-text')
				.trim(),
			link: getComputedStyle(document.body)
				.getPropertyValue('--nldesign-color-link')
				.trim(),
			items: Array.from(
				root.querySelectorAll(
					'.action-link, .action-button, .action-router, a[role=menuitem], button[role=menuitem]',
				),
			).map((e: any) => {
				const text =
					e.querySelector(
						'.action-link__text, .action-button__text, .action-router__text',
					) || e
				const cs = getComputedStyle(text)
				return {
					cls: String(e.className).slice(0, 40),
					text: (e.innerText || '').trim().slice(0, 30),
					color: cs.color,
					deco: cs.textDecorationLine,
					outerDeco: getComputedStyle(e).textDecorationLine,
				}
			}),
		}
	})
	console.log('MENU', JSON.stringify(items))
	await page.screenshot({ path: `${OUT}/dossiq-acties.png` })
})
