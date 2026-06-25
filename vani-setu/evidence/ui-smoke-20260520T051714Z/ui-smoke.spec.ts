import { expect, test, type Page } from '@playwright/test'

const baseUrl = process.env.SMOKE_BASE_URL ?? 'http://localhost:5173'
const smokeDir = process.env.SMOKE_DIR ?? '/home/sds-dev/evidence/ui-smoke-20260520T051714Z'

async function screenshot(page: Page, name: string) {
  await page.screenshot({ path: `${smokeDir}/${name}.png`, fullPage: true })
}

async function login(page: Page, employeeId: string, password: string, path: string) {
  const response = await page.request.post(`${baseUrl}/api/auth/login`, {
    data: { employee_id: employeeId, password }
  })
  expect(response.ok(), `${employeeId} login API should succeed`).toBeTruthy()
  const payload = await response.json()
  await page.goto(baseUrl, { waitUntil: 'domcontentloaded' })
  await page.evaluate((stored) => {
    localStorage.setItem('vani-setu-auth', JSON.stringify(stored))
  }, {
    token: payload.token,
    user: payload.user,
    roles: payload.roles,
    permissions: payload.permissions
  })
  await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle' })
}

async function clickFirst(page: Page, selector: string, label: string) {
  const target = page.locator(selector).first()
  await expect(target, `${label} should be visible`).toBeVisible({ timeout: 15000 })
  await target.click()
  await page.waitForLoadState('networkidle')
}

async function editFirstTextbox(page: Page, text: string) {
  const textbox = page.locator('[role="textbox"][contenteditable="true"]').first()
  await expect(textbox).toBeVisible({ timeout: 15000 })
  await textbox.click()
  await textbox.press('End')
  await textbox.type(text)
  await page.waitForTimeout(1200)
}

async function clickButton(page: Page, name: RegExp | string) {
  const button = page.getByRole('button', { name }).first()
  await expect(button).toBeVisible({ timeout: 15000 })
  await button.click()
  await page.waitForLoadState('networkidle').catch(() => undefined)
}

async function clickModalButton(page: Page, name: RegExp | string) {
  const button = page.locator('.modal').getByRole('button', { name }).first()
  await expect(button).toBeVisible({ timeout: 15000 })
  await button.click()
  await page.waitForLoadState('networkidle').catch(() => undefined)
}

test.describe.configure({ mode: 'serial' })

test('stakeholder SG demo role walkthrough', async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } })
  const page = await context.newPage()

  await login(page, 'RPT-001', 'reporter123', '/capture')
  await screenshot(page, '01-reporter-dashboard')
  await page.goto(`${baseUrl}/capture/681`, { waitUntil: 'networkidle' })
  await screenshot(page, '02-reporter-slot')
  await editFirstTextbox(page, ' Stakeholder smoke edit.')
  await screenshot(page, '03-reporter-edited')
  await clickButton(page, /^Commit lane$/)
  await clickModalButton(page, /^Commit lane$/)
  await screenshot(page, '04-reporter-committed')

  await login(page, 'SUP-EN-001', 'sup123', '/supervisor/queue')
  await screenshot(page, '05-supervisor-queue')
  await clickFirst(page, 'button.queue-card', 'supervisor queue card')
  await screenshot(page, '06-supervisor-lane')
  await clickButton(page, /^Forward to Chief$/)
  const note = page.locator('textarea').first()
  if (await note.isVisible().catch(() => false)) {
    await note.fill('Smoke walkthrough forward to Chief.')
  }
  await clickModalButton(page, /^Forward to Chief$/)
  await screenshot(page, '07-supervisor-forwarded')

  await login(page, 'CHF-EN-001', 'chief123', '/chief/queue')
  await screenshot(page, '08-chief-en-queue')
  await page.goto(`${baseUrl}/chief/consolidations/111`, { waitUntil: 'networkidle' })
  await screenshot(page, '09-chief-en-window')
  await editFirstTextbox(page, ' Chief EN smoke edit.')
  await screenshot(page, '10-chief-en-edited')
  await clickButton(page, /^Commit lane$/)
  await clickModalButton(page, /^Commit EN lane$/)
  await screenshot(page, '11-chief-en-committed')

  await login(page, 'CHF-HI-001', 'chief123', '/chief/queue')
  await screenshot(page, '12-chief-hi-queue')
  await page.goto(`${baseUrl}/chief/consolidations/111`, { waitUntil: 'networkidle' })
  await screenshot(page, '13-chief-hi-window')
  await editFirstTextbox(page, ' Chief HI smoke edit.')
  await clickButton(page, /^Commit lane$/)
  await clickModalButton(page, /^Commit HI lane$/)
  await screenshot(page, '14-chief-hi-committed')

  await login(page, 'JS-001', 'js123', '/js/queue')
  await screenshot(page, '15-js-queue')
  await page.goto(`${baseUrl}/js/windows/63`, { waitUntil: 'networkidle' })
  await screenshot(page, '16-js-window')
  await clickButton(page, /Suggested/)
  const accept = page.getByRole('button', { name: /^Accept$/ }).first()
  if (await accept.isVisible().catch(() => false)) {
    await accept.click()
    await page.waitForLoadState('networkidle').catch(() => undefined)
  }
  await screenshot(page, '17-js-accepted-suggested-edit')
  await clickButton(page, /^Forward SG$/)
  await clickModalButton(page, /^Forward$/)
  await screenshot(page, '18-js-forwarded-sg')

  await login(page, 'SG-001', 'sg123', '/sg/tray')
  await screenshot(page, '19-sg-tray')
  await page.goto(`${baseUrl}/sg/windows/64`, { waitUntil: 'networkidle' })
  await screenshot(page, '20-sg-window')
  await clickButton(page, /^Open$/)
  const confirmButtons = page.getByRole('button', { name: /^Confirm$/ })
  const count = await confirmButtons.count()
  for (let i = 0; i < count; i += 1) {
    const button = confirmButtons.nth(i)
    if (await button.isEnabled().catch(() => false)) {
      await button.click()
      await page.waitForLoadState('networkidle').catch(() => undefined)
    }
  }
  await screenshot(page, '21-sg-expunge-confirmed')
  await clickButton(page, /^DSC Sign$/)
  await screenshot(page, '22-sg-signed')

  await login(page, 'DIR-001', 'director123', '/director/inbox')
  await screenshot(page, '23-director-inbox')
  await page.goto(`${baseUrl}/director/jobs/6`, { waitUntil: 'networkidle' })
  await screenshot(page, '24-director-job')
  const push = page.getByRole('button', { name: /^Push$/ }).first()
  if (await push.isEnabled().catch(() => false)) {
    await push.click()
    await page.waitForLoadState('networkidle').catch(() => undefined)
  }
  await screenshot(page, '25-director-crc-preview')

  await context.close()
})
