// End-to-end test of the plugin in a real WordPress (WordPress Playground),
// with the live widget.js from cdn.replyze.chat and a mocked Replyze API, so
// nothing reaches a real Replyze workspace.
//
//   npm ci && npx playwright install chromium
//   node e2e.mjs [--php 8.3] [--wp latest] [--port 9400]
//
// It checks: activation, the settings page, the widget tag and config, the
// hide rules and shortcode, identify() with and without the identity secret
// (the HMAC the Replyze API checks), sign-out and user switch on one browser,
// the README's tracking recipe, PHP notices, and uninstall.
import { spawn } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseArgs } from 'node:util';
import { chromium } from 'playwright';

const here = path.dirname(fileURLToPath(import.meta.url));
const pluginDir = path.resolve(here, '../../replyze');
const { values: opt } = parseArgs({ options: { php: { type: 'string', default: '8.3' }, wp: { type: 'string', default: 'latest' }, port: { type: 'string', default: '9400' } } });
const outDir = path.join(here, 'out', `php-${opt.php}-wp-${opt.wp}`);
fs.mkdirSync(outDir, { recursive: true });

const TOKEN = 'wgt_DemoToken_1234';
// Upper case on purpose: the plugin stores it lower case, as Replyze issues it.
const SECRET = 'ABABABAB' + '0123456789abcdef'.repeat(4).slice(0, 56);
const results = [];
function check(name, ok, detail = '') {
  results.push({ name, ok: !!ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${!ok && detail ? ' :: ' + detail : ''}`);
}

// ---- WordPress Playground ---------------------------------------------------

function blueprint() {
  // The plugin's files are written into the site (not mounted), so deleting
  // the plugin in the test never touches this repository.
  const steps = [{ step: 'mkdir', path: '/wordpress/wp-content/plugins/replyze' }];
  for (const f of fs.readdirSync(pluginDir)) {
    steps.push({ step: 'writeFile', path: `/wordpress/wp-content/plugins/replyze/${f}`, data: fs.readFileSync(path.join(pluginDir, f), 'utf8') });
  }
  steps.push({ step: 'activatePlugin', pluginPath: 'replyze/replyze.php' });
  steps.push({ step: 'writeFile', path: '/wordpress/wp-content/mu-plugins/replyze-probe.php', data: fs.readFileSync(path.join(here, 'probe.php'), 'utf8') });
  steps.push({ step: 'runPHP', code: fs.readFileSync(path.join(here, 'setup.php'), 'utf8') });
  const file = path.join(outDir, 'blueprint.json');
  fs.writeFileSync(file, JSON.stringify({ preferredVersions: { php: opt.php, wp: opt.wp }, steps }, null, 1));
  return file;
}

async function startWordPress() {
  const cli = path.join(here, 'node_modules', '@wp-playground', 'cli', 'wp-playground.js');
  const log = fs.createWriteStream(path.join(outDir, 'server.log'));
  const child = spawn(process.execPath, [cli, 'server', `--php=${opt.php}`, `--wp=${opt.wp}`, `--port=${opt.port}`, `--blueprint=${blueprint()}`], { stdio: ['ignore', 'pipe', 'pipe'] });
  let ready = false;
  let exited = null;
  child.stdout.on('data', (d) => { if (String(d).includes('Ready!')) ready = true; });
  child.stdout.pipe(log);
  child.stderr.pipe(log);
  child.on('exit', (code) => { exited = code; });
  const started = Date.now();
  // It answers before the blueprint has run: wait for its "Ready!".
  while (!ready) {
    if (exited !== null) throw new Error(`Playground exited with ${exited}; see ${path.join(outDir, 'server.log')}`);
    if (Date.now() - started > 300000) throw new Error('Playground not ready after 300 s');
    await new Promise((r) => setTimeout(r, 500));
  }
  return { url: `http://127.0.0.1:${opt.port}`, stop: () => new Promise((r) => { if (exited !== null) return r(); child.on('exit', r); child.kill(); }) };
}

// ---- Mocked Replyze API (client.replyze.chat) -------------------------------

let nextContact = 500;
let aliceId = null;
const apiLog = [];
async function mockApi(route) {
  const req = route.request();
  let body = {};
  try { body = req.postDataJSON() || {}; } catch { /* no body */ }
  const p = new URL(req.url()).pathname.replace(/^\/api\/w\/[^/]+/, '');
  apiLog.push({ path: p, body, visitorKey: req.headers()['x-visitor-key'] || null });
  const json = (data, delay = 0) => new Promise((res) => setTimeout(() => res(route.fulfill({ status: 200, contentType: 'application/json', headers: { 'access-control-allow-origin': '*' }, body: JSON.stringify(data) })), delay));
  if (req.method() === 'OPTIONS') return route.fulfill({ status: 204, headers: { 'access-control-allow-origin': '*', 'access-control-allow-headers': '*', 'access-control-allow-methods': '*' } });
  if (p === '/config') return json({ widget: {}, features: {} });
  if (p === '/session/start') {
    const known = body.contact_id ? String(body.contact_id) : null;
    const id = known || String(nextContact++);
    const session = { session_id: '0', contact_id: id, visitor_key: 'vk' + id, channel_key: 'ck' + id, messages: [], widget: {}, features: {} };
    // A returning visitor's chat comes back a little later, like a real API
    // call: the window in which a sign-out must not be undone.
    return json(session, known ? 900 : 0);
  }
  if (p === '/identify') {
    if (body.user_hash) {
      const id = String(body.user_id).endsWith(':' + aliceId) ? '900' : '901';
      return json({ contact_id: id, visitor_key: 'vk' + id, channel_key: 'ck' + id, email: body.email, name: body.name, verified: true, switched: String(body.contact_id) !== id, form_data: [] });
    }
    return json({ contact_id: String(body.contact_id), email: body.email, name: body.name, form_data: {} });
  }
  return json({});
}

// ---- Page helpers -----------------------------------------------------------

async function login(page, url, user, pass) {
  await page.goto(url + '/wp-login.php');
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
}

async function logout(page, url) {
  await page.goto(url + '/wp-login.php?action=logout');
  const link = page.locator('a[href*="action=logout"]');
  if (await link.count()) await Promise.all([page.waitForNavigation(), link.first().click()]);
}

async function saveSettings(page, url, fill) {
  await page.goto(url + '/wp-admin/options-general.php?page=replyze');
  await fill(page);
  await Promise.all([page.waitForNavigation(), page.click('#submit')]);
  return page.content();
}

/** The widget tag and the inline script WordPress prints before it. */
async function widgetTag(page) {
  return page.evaluate(() => {
    const before = document.getElementById('replyze-widget-js-before');
    const tag = document.getElementById('replyze-widget-js');
    return {
      before: before ? before.textContent.trim() : null,
      src: tag ? tag.getAttribute('src') : null,
      async: tag ? tag.hasAttribute('async') : null,
      inFooter: tag ? !document.head.contains(tag) : null,
      configFirst: before && tag ? !!(before.compareDocumentPosition(tag) & Node.DOCUMENT_POSITION_FOLLOWING) : null,
    };
  });
}

/** The arguments of the plugin's visitor script: (window, document, key, prefix, cookie, marker, who). */
function visitorArgs(before) {
  const start = '})(window,document,';
  const i = before.indexOf(start);
  if (i < 0) return null;
  const rest = before.slice(i + start.length);
  const [key, prefix, cookie, marker, who] = JSON.parse('[' + rest.slice(0, rest.lastIndexOf(');')) + ']');
  return { key, prefix, cookie, marker, who };
}

async function widgetState(page) {
  return page.evaluate(() => {
    const ls = {};
    for (let i = 0; i < localStorage.length; i++) { const k = localStorage.key(i); ls[k] = localStorage.getItem(k); }
    const w = window.Replyze && window.Replyze._widget;
    return { session: w ? w.session : null, ls };
  });
}

const widgetStarted = (page) => page.waitForFunction(() => window.Replyze && window.Replyze._initialized, null, { timeout: 20000 });

// ---- The test ---------------------------------------------------------------

const browser = await chromium.launch();
const wp = await startWordPress().catch(async (e) => { await browser.close(); throw e; });
try {
  const url = wp.url;
  const probe = async () => (await fetch(url + '/?replyze_probe=1')).json();
  let info = await probe();
  console.log(`WordPress ${info.wp}, PHP ${info.php}`);
  aliceId = info.users.find((u) => u.login === 'alice').id;
  const hidden = info.pages.find((p) => p.title === 'Hidden page');
  const forced = info.pages.find((p) => p.title === 'Forced page');
  check('activation saves the defaults', info.active && info.option.token === '' && info.option.enabled == 1 && info.option.exclude_admin == 1, JSON.stringify(info.option));

  // Admin: plugins screen and settings page.
  const admin = await (await browser.newContext({ viewport: { width: 1280, height: 1000 } })).newPage();
  await login(admin, url, 'admin', 'password');
  await admin.goto(url + '/wp-admin/plugins.php');
  const row = admin.locator('tr[data-plugin="replyze/replyze.php"]');
  check('plugins screen: active, with a Settings link', (await row.getAttribute('class')).includes('active') && /page=replyze/.test(await row.locator('a', { hasText: 'Settings' }).first().getAttribute('href')));
  await admin.goto(url + '/wp-admin/options-general.php?page=replyze');
  check('settings page without a token: the setup steps', (await admin.locator('.wrap h1').innerText()).includes('Replyze Chat') && (await admin.content()).includes('Get started'));
  await admin.screenshot({ path: path.join(outDir, 'settings-setup.png'), fullPage: true });

  const anon0 = await (await browser.newContext()).newPage();
  await anon0.goto(url + '/');
  check('no token: no widget', (await widgetTag(anon0)).src === null);
  await anon0.context().close();

  let html = await saveSettings(admin, url, async (p) => { await p.fill('#replyze_token', 'abc'); });
  check('a wrong token is refused, with one message', html.split('look like a widget token').length - 1 === 1 && (await probe()).option.token === '');
  html = await saveSettings(admin, url, async (p) => {
    await p.fill('#replyze_token', '  ' + TOKEN + ' ');
    await p.check('input[name="replyze_settings[identify_users]"]');
    await p.fill('#replyze_identity_secret', 'not-a-secret');
  });
  check('a wrong identity secret is refused', html.includes('look like an identity secret'));
  html = await saveSettings(admin, url, async (p) => { await p.fill('#replyze_identity_secret', SECRET); });
  info = await probe();
  check('token saved (trimmed), secret saved lower case', info.option.token === TOKEN && info.option.identity_secret === SECRET.toLowerCase() && info.option.identify_users == 1);
  check('the secret is never printed', html.includes('Token saved') && !html.toLowerCase().includes(SECRET.toLowerCase()));
  await admin.goto(url + '/');
  check('signed-in administrator: no widget (default)', (await widgetTag(admin)).src === null);

  // A visitor: the tag, the config, the live widget.
  const ctxA = await browser.newContext();
  await ctxA.route('https://client.replyze.chat/**', mockApi);
  const anon = await ctxA.newPage();
  await anon.goto(url + '/');
  const tA = await widgetTag(anon);
  check('visitor: widget.js from the CDN, in the footer', tA.src === 'https://cdn.replyze.chat/widget.js' && tA.inFooter, JSON.stringify(tA));
  check('visitor: ReplyzeConfig before the script', tA.configFirst && tA.before.startsWith(`window.ReplyzeConfig = {"token":"${TOKEN}","apiUrl":"https:\\/\\/client.replyze.chat"};`), tA.before);
  check('visitor: nobody identified', visitorArgs(tA.before).who === null);
  console.log(`  script async: ${tA.async}`);
  await widgetStarted(anon);
  check('visitor: the live widget starts', true);

  // Hide rules and the shortcode.
  await saveSettings(admin, url, async (p) => { await p.fill('#replyze_excluded_ids', ` ${hidden.id}, abc ,${hidden.id}`); });
  check('page IDs cleaned when saved', (await probe()).option.excluded_ids === String(hidden.id));
  await anon.goto(hidden.link);
  check('hidden by page ID', (await widgetTag(anon)).src === null);
  await saveSettings(admin, url, async (p) => { await p.fill('#replyze_excluded_ids', ''); await p.check('input[value="page"]'); });
  await anon.goto(hidden.link);
  check('hidden by content type', (await widgetTag(anon)).src === null);
  await anon.goto(forced.link);
  check('[replyze] shows it despite the rules', (await widgetTag(anon)).src !== null);
  await saveSettings(admin, url, async (p) => { await p.uncheck('input[value="page"]'); await p.check('input[name="replyze_settings[match_language]"]'); });
  await anon.goto(url + '/');
  check('page language in the config', (await widgetTag(anon)).before.includes('"language":"en_US"'));
  await saveSettings(admin, url, async (p) => { await p.uncheck('input[name="replyze_settings[match_language]"]'); });
  await ctxA.close();

  // README recipe: no visitor tracking until consent.
  apiLog.length = 0;
  const ctxT = await browser.newContext();
  await ctxT.route('https://client.replyze.chat/**', mockApi);
  const nt = await ctxT.newPage();
  await nt.goto(url + '/?rz_notrack=1');
  await widgetStarted(nt);
  await nt.waitForTimeout(1500);
  check('README recipe: tracking off, no page views and no visitor cookie', !apiLog.some((r) => r.path === '/visit') && !(await ctxT.cookies()).some((c) => c.name === 'rz_track_id'));
  await ctxT.close();

  // A signed-in user with the identity secret.
  const ctxU = await browser.newContext();
  await ctxU.route('https://client.replyze.chat/**', mockApi);
  const user = await ctxU.newPage();
  await login(user, url, 'alice', 'alicepass');
  apiLog.length = 0;
  await user.goto(url + '/');
  const va = visitorArgs((await widgetTag(user)).before);
  const userId = `wp:${new URL(info.home).hostname}:${aliceId}`;
  // The Replyze API: hash_hmac('sha256', trim(user_id), secret).
  const hash = crypto.createHmac('sha256', SECRET.toLowerCase()).update(userId.trim()).digest('hex');
  check('identify(): email, name, user_id and its HMAC', va.who && va.who.email === 'Alice.Example@Example.com' && va.who.name === 'Alice Example' && va.who.user_id === userId && va.who.user_hash === hash, JSON.stringify(va.who));
  check('the marker is opaque', /^[0-9a-f]{20}$/.test(va.marker));
  await widgetStarted(user);
  await user.waitForTimeout(1500);
  const sent = apiLog.find((r) => r.path === '/identify');
  check('the live widget sends that identity to the API', sent && sent.body.user_id === userId && sent.body.user_hash === hash && sent.body.email === va.who.email && sent.body.name === va.who.name, JSON.stringify(sent && sent.body));
  let st = await widgetState(user);
  check('the widget becomes her verified contact', st.session && st.session.contact_id === '900', JSON.stringify(st.session));
  await user.goto(url + '/');
  await user.waitForTimeout(1500);
  st = await widgetState(user);
  check('and stays it on the next page', st.session && st.session.contact_id === '900');
  const aliceVisitorId = st.ls.aichat_rz_track_id;

  // She signs out; the next page on the same browser.
  await logout(user, url);
  apiLog.length = 0;
  await user.goto(url + '/');
  check('after sign-out: nobody identified', visitorArgs((await widgetTag(user)).before).who === null);
  await user.waitForTimeout(2500);
  st = await widgetState(user);
  check('after sign-out: no API call as her', !apiLog.some((r) => r.visitorKey === 'vk900' || String(r.body.contact_id) === '900'), JSON.stringify(apiLog.map((r) => [r.path, r.body.contact_id || null, r.visitorKey])));
  check('after sign-out: no page view under her visitor id', aliceVisitorId && !apiLog.some((r) => r.body.session_token === aliceVisitorId) && st.ls.aichat_rz_track_id !== aliceVisitorId);
  check('after sign-out: the widget forgot her', !(st.session && st.session.contact_id === '900') && st.ls.aichat_contact_id !== '900' && !st.ls.replyze_wp_identified, JSON.stringify({ session: st.session, stored: st.ls.aichat_contact_id || null }));

  // A very long name is cut to what the API accepts.
  const ctxC = await browser.newContext();
  const carol = await ctxC.newPage();
  await login(carol, url, 'carol', 'carolpass');
  await carol.goto(url + '/');
  const whoC = visitorArgs((await widgetTag(carol)).before).who;
  check('a 300-character name is sent as 255 characters', whoC && [...whoC.name].length === 255);
  await ctxC.close();

  // Without the secret: another user signs in on the same browser.
  await saveSettings(admin, url, async (p) => { await p.check('input[name="replyze_settings[clear_identity_secret]"]'); });
  check('the secret can be removed', (await probe()).option.identity_secret === '');
  await login(user, url, 'alice', 'alicepass');
  await user.goto(url + '/');
  const whoN = visitorArgs((await widgetTag(user)).before).who;
  check('without the secret: only email and name', whoN && !('user_id' in whoN) && !('user_hash' in whoN));
  await widgetStarted(user);
  await user.evaluate(() => window.Replyze._widget.ensureContact());
  await user.waitForTimeout(1200);
  const aliceContact = (await widgetState(user)).session?.contact_id;
  await logout(user, url);
  await login(user, url, 'bob', 'bobpass');
  await user.goto(url + '/');
  await user.waitForTimeout(2500);
  st = await widgetState(user);
  check('user switch: bob does not get her chat', aliceContact && st.session?.contact_id !== aliceContact && st.ls.aichat_contact_id !== aliceContact, JSON.stringify({ aliceContact, session: st.session }));
  await ctxU.close();

  // Uninstall: deactivate and delete in Plugins.
  await admin.goto(url + '/wp-admin/plugins.php');
  await Promise.all([admin.waitForNavigation(), admin.locator('tr[data-plugin="replyze/replyze.php"] .deactivate a').click()]);
  admin.on('dialog', (d) => d.accept());
  await admin.locator('tr[data-plugin="replyze/replyze.php"] .delete a').click();
  for (let i = 0; i < 30; i++) {
    info = await probe();
    if (info.option === 'MISSING') break;
    await admin.waitForTimeout(1000);
  }
  check('no PHP notice about the plugin in debug.log', info.debug_log.length === 0, JSON.stringify(info.debug_log.slice(0, 3)));
  check('uninstall deletes the settings', info.option === 'MISSING');
} catch (e) {
  console.error(e);
  check('the test ran to the end', false, e.message);
} finally {
  await browser.close();
  await wp.stop();
}

const failed = results.filter((r) => !r.ok);
fs.writeFileSync(path.join(outDir, 'results.json'), JSON.stringify(results, null, 2));
console.log(`\n${results.length - failed.length}/${results.length} passed (PHP ${opt.php}, WordPress ${opt.wp})`);
process.exit(failed.length ? 1 : 0);
