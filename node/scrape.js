#!/usr/bin/env node
'use strict';

/**
 * Headless scraper — Puppeteer (VividSeats) + SeatGeek bypass attempts.
 *
 * Usage : node scrape.js <url> [seatgeek_client_id] [datadome_cookie]
 * Output: JSON { success, tickets, eventTitle, platform, error?, restricted?, needsClientId? }
 *
 * Contract: this script NEVER throws to the shell. Every exit path prints a
 * single JSON object on stdout so the PHP layer can always parse a result.
 */

const https = require('https');

const puppeteerExtra = require('puppeteer-extra');
const StealthPlugin  = require('puppeteer-extra-plugin-stealth');
puppeteerExtra.use(StealthPlugin());

const url        = process.argv[2];
const sgClientId = process.argv[3] || process.env.SEATGEEK_CLIENT_ID || '';
const sgDataDome = process.argv[4] || process.env.SEATGEEK_DATADOME  || '';

// Headful "manual assist" mode: opens a visible Chromium so the user can clear
// a DataDome challenge once by hand, after which we capture the listings XHR.
// This is the most reliable path on a personal machine. Enable with:
//   SEATGEEK_HEADFUL=1 node node/scrape.js "<url>"
const HEADFUL = process.env.SEATGEEK_HEADFUL === '1';

if (!url) {
  console.log(JSON.stringify({ success: false, error: 'No URL provided' }));
  process.exit(0);
}

const host = (() => { try { return new URL(url).hostname; } catch { return ''; } })();

// A realistic desktop-Chrome header set. The internal SeatGeek endpoint inspects
// these (UA + client hints + fetch metadata) as part of its anti-bot scoring.
const BROWSER_HEADERS = {
  'User-Agent':
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36',
  'Accept-Language':    'en-US,en;q=0.9',
  'sec-ch-ua':          '"Chromium";v="148", "Google Chrome";v="148", "Not.A/Brand";v="24"',
  'sec-ch-ua-mobile':   '?0',
  'sec-ch-ua-platform': '"Windows"',
  'Sec-Fetch-Dest':     'empty',
  'Sec-Fetch-Mode':     'cors',
  'Sec-Fetch-Site':     'same-origin',
};

// ── Helpers ───────────────────────────────────────────────────────────────────

/** GET a URL and resolve { status, data, raw }. Never rejects on bad JSON. */
function httpGet(apiUrl, headers = {}) {
  return new Promise((resolve, reject) => {
    https.get(apiUrl, {
      headers: { Accept: 'application/json', 'User-Agent': BROWSER_HEADERS['User-Agent'], ...headers },
    }, res => {
      let body = '';
      res.on('data', d => { body += d; });
      res.on('end', () => {
        let data = null;
        try { data = JSON.parse(body); } catch { /* non-JSON (HTML challenge page) */ }
        resolve({ status: res.statusCode, data, raw: body });
      });
    }).on('error', reject);
  });
}

/** Pull the trailing numeric event id out of a SeatGeek URL. */
function extractSeatGeekEventId(u) {
  const parts = u.replace(/[?#].*$/, '').replace(/\/+$/, '').split('/');
  return parts.reverse().find(p => /^\d+$/.test(p)) || null;
}

/** A SeatGeek web token decodes to "id|timestamp"; a dev key does not. */
function looksLikeSessionToken(token) {
  try {
    return /^\d+\|\d+(\.\d+)?$/.test(Buffer.from(token, 'base64').toString('utf8'));
  } catch { return false; }
}

function mapTickets(arr, platform) {
  return arr.map(t => ({
    section:  t.sectionName || t.section || t.s || 'Unknown',
    row:      t.row         || t.r       || 'GA',
    price:    parseFloat(t.allInPricePerTicket || t.price || t.lowest_price || t.p || 0),
    quantity: parseInt(t.quantity || t.q || 1, 10),
    platform,
  })).filter(t => t.price > 0);
}

// ── VividSeats (Puppeteer + Hermes API interception) ─────────────────────────

async function scrapeVividSeats(browser) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 800 });

  let hermesData = null;
  page.on('response', async res => {
    if (res.url().includes('/hermes/api/v1/listings')) {
      try { hermesData = await res.json(); } catch {}
    }
  });

  await page.goto(url, { waitUntil: 'networkidle2', timeout: 45000 });
  const eventTitle = await page.title().catch(() => '');
  await new Promise(r => setTimeout(r, 2000));
  await page.close();

  if (!hermesData || !Array.isArray(hermesData.tickets)) {
    throw new Error('VividSeats: could not capture listing data.');
  }

  return { tickets: mapTickets(hermesData.tickets, 'VividSeats'), eventTitle, platform: 'VividSeats' };
}

// ── SeatGeek — strategy: direct hit on the internal listings endpoint ─────────
// This is the "last attempt": skip the JS challenge page entirely and call the
// XHR endpoint the SPA itself uses, replaying the browser's datadome cookie and
// client hints. If DataDome accepts the cookie (same IP/UA it was issued to) we
// get JSON; otherwise it returns a 403 HTML challenge, which we detect cleanly.
async function scrapeSeatGeekInternal(eventId, dataDomeCookie, clientId) {
  const qs = new URLSearchParams({ id: String(eventId), per_page: '5000' });
  if (clientId) qs.set('client_id', clientId);

  const headers = { ...BROWSER_HEADERS, Referer: url };
  if (dataDomeCookie) headers.Cookie = `datadome=${dataDomeCookie}`;

  const { status, data } = await httpGet(`https://seatgeek.com/api/event_listings_v2?${qs}`, headers);

  if (status === 403 || status === 401) throw new Error(`BLOCKED:${status}`);
  if (status !== 200 || !data)          throw new Error(`SeatGeek internal API: HTTP ${status}`);

  const listings = data.listings || data.tickets || [];
  if (!listings.length) throw new Error('SeatGeek internal API: no listings returned.');

  return {
    tickets:    mapTickets(listings, 'SeatGeek'),
    eventTitle: data.event?.title || data.title || '',
    platform:   'SeatGeek',
  };
}

// ── SeatGeek — browser strategy (headless or headful manual-assist) ───────────
// Loads the event page, optionally injecting a datadome cookie, and polls for
// the listings XHR until it appears or `maxWaitMs` elapses. In headful mode the
// long window gives the user time to clear a DataDome challenge by hand.

async function scrapeSeatGeekBrowser(browser, { cookie = '', maxWaitMs = 12000, blockCode = 'puppeteer' } = {}) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  await page.setUserAgent(BROWSER_HEADERS['User-Agent']);

  if (cookie) {
    await page.setCookie({
      name: 'datadome', value: cookie, domain: '.seatgeek.com',
      path: '/', secure: true, httpOnly: true, sameSite: 'Lax',
    });
  }

  let captured = null;
  page.on('response', async res => {
    const u = res.url();
    if (res.status() === 200 && (u.includes('event_listings') || u.includes('/api/event'))) {
      try { const j = await res.json(); if (j.listings || j.tickets) captured = j; } catch {}
    }
  });

  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 40000 });
  const eventTitle = await page.title().catch(() => '');

  if (HEADFUL) {
    process.stderr.write(
      '\n  → A Chromium window has opened. If SeatGeek shows a CAPTCHA / "verify you are human",\n' +
      '    solve it once. The listings will be captured automatically (up to ' +
      Math.round(maxWaitMs / 1000) + 's).\n\n'
    );
  }

  // Poll: succeed as soon as the listings XHR is captured.
  const deadline = Date.now() + maxWaitMs;
  while (!captured && Date.now() < deadline) {
    await new Promise(r => setTimeout(r, 1000));
  }

  // Last resort: read the embedded __NEXT_DATA__ payload.
  if (!captured) {
    captured = await page.evaluate(() => {
      const el = document.getElementById('__NEXT_DATA__');
      if (!el) return null;
      try {
        const pp = JSON.parse(el.textContent)?.props?.pageProps;
        return pp?.listings || pp?.event?.listings || null;
      } catch { return null; }
    }).catch(() => null);
  }

  await page.close();
  if (!captured) throw new Error('BLOCKED:' + blockCode);

  const raw = Array.isArray(captured) ? captured : (captured.listings || captured.tickets || []);
  return { tickets: mapTickets(raw, 'SeatGeek'), eventTitle, platform: 'SeatGeek' };
}

/** Launch options shared by every SeatGeek browser attempt. */
function browserLaunchOpts() {
  const args = ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
                '--disable-blink-features=AutomationControlled', '--window-size=1440,900'];
  if (!HEADFUL) args.push('--disable-gpu');
  return { headless: !HEADFUL, args };
}

// ── SeatGeek — strategy: public developer API (needs a real dev client_id) ────

async function scrapeSeatGeekApi(eventId) {
  const { status, data } = await httpGet(
    `https://api.seatgeek.com/2/listings?event_id=${eventId}&per_page=5000&client_id=${encodeURIComponent(sgClientId)}`
  );

  if (status === 401 || status === 403) throw new Error('BLOCKED:apikey');
  if (status !== 200 || !data)          throw new Error(`SeatGeek API: HTTP ${status}`);

  const listings = data.listings || [];
  if (!listings.length) throw new Error('SeatGeek API: no listings returned (the public API no longer exposes listings).');

  let eventTitle = '';
  try {
    const ev = await httpGet(`https://api.seatgeek.com/2/events/${eventId}?client_id=${encodeURIComponent(sgClientId)}`);
    eventTitle = ev.data?.short_title || ev.data?.title || '';
  } catch {}

  return { tickets: mapTickets(listings, 'SeatGeek'), eventTitle, platform: 'SeatGeek' };
}

// ── SeatGeek orchestration ────────────────────────────────────────────────────
// The UI sends exactly one credential. We try the strategies that match it and,
// on failure, report `restricted: true` so the PHP layer can render the elegant
// "perimeter security" message instead of a raw error.

async function runSeatGeek() {
  const eventId = extractSeatGeekEventId(url);
  let browser;

  // Method A — DataDome cookie.
  if (sgDataDome) {
    if (!eventId) {
      return { success: false, restricted: true, error: 'Could not extract the SeatGeek event ID from the URL.' };
    }
    // A1: direct internal endpoint (fast, no browser). Skipped in headful mode,
    // where the whole point is to drive the visible browser.
    if (!HEADFUL) {
      try {
        return { success: true, ...(await scrapeSeatGeekInternal(eventId, sgDataDome, sgClientId)) };
      } catch { /* fall through */ }
    }

    // A2: render the page with the cookie injected (visible browser if HEADFUL).
    try {
      browser = await puppeteerExtra.launch(browserLaunchOpts());
      const result = await scrapeSeatGeekBrowser(browser, {
        cookie: sgDataDome, blockCode: 'datadome', maxWaitMs: HEADFUL ? 90000 : 12000,
      });
      return { success: true, ...result };
    } catch {
      return { success: false, restricted: true, error: 'BLOCKED:datadome' };
    } finally {
      if (browser) await browser.close().catch(() => {});
    }
  }

  // Method B — developer API key.
  if (sgClientId) {
    if (looksLikeSessionToken(sgClientId)) {
      return {
        success: false, restricted: true,
        error: 'NOT_A_DEV_KEY',
      };
    }
    if (!eventId) {
      return { success: false, restricted: true, error: 'Could not extract the SeatGeek event ID from the URL.' };
    }
    try {
      return { success: true, ...(await scrapeSeatGeekApi(eventId)) };
    } catch (err) {
      return { success: false, restricted: true, error: err.message };
    }
  }

  // No credentials — one plain attempt (or a manual-assist window in headful mode).
  try {
    browser = await puppeteerExtra.launch(browserLaunchOpts());
    const result = await scrapeSeatGeekBrowser(browser, {
      blockCode: 'nocreds', maxWaitMs: HEADFUL ? 90000 : 10000,
    });
    return { success: true, ...result };
  } catch {
    if (HEADFUL) return { success: false, restricted: true, error: 'BLOCKED:datadome' };
    return { success: false, needsClientId: true, error: 'BLOCKED:nocreds' };
  } finally {
    if (browser) await browser.close().catch(() => {});
  }
}

// ── Main ──────────────────────────────────────────────────────────────────────

(async () => {
  try {
    if (host.includes('seatgeek.com')) {
      console.log(JSON.stringify(await runSeatGeek()));
      return;
    }

    if (host.includes('vividseats.com')) {
      let browser;
      try {
        browser = await puppeteerExtra.launch({
          headless: true,
          args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
        });
        console.log(JSON.stringify({ success: true, ...(await scrapeVividSeats(browser)) }));
      } finally {
        if (browser) await browser.close().catch(() => {});
      }
      return;
    }

    console.log(JSON.stringify({ success: false, error: `Unsupported platform: ${host || 'unknown'}` }));
  } catch (err) {
    // Last-resort guard: still emit valid JSON so the PHP layer never breaks.
    console.log(JSON.stringify({ success: false, error: err?.message || 'Unexpected scraper error' }));
  }
})();
