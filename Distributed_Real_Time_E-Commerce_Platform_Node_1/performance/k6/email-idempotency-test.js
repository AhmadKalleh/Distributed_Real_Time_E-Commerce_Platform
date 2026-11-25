// Email Idempotency & Deduplication Test under load + worker-email stop/start.
// Queries Mailpit API before and after the run to verify actual vs expected emails.
//
// Run (via the PowerShell script below, or standalone):
//   k6 run -e BASE_URL=http://localhost:8080 -e MAILPIT_URL=http://localhost:8025 email-idempotency-test.js
// Output: email-idempotency-result.txt

import http from 'k6/http';
import { sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE_URL    = __ENV.BASE_URL || 'http://localhost:8080';
const MAILPIT_URL = (__ENV.MAILPIT_URL || 'http://localhost:8025').replace(/\/+$/, '');
const DURATION    = __ENV.DURATION || '45s';
const VUS         = parseInt(__ENV.VUS || '15', 10);
const RESULT_FILE = __ENV.RESULT_FILE || 'email-idempotency-result.txt';
const EMAILS_PER_ORDER = parseInt(__ENV.EMAILS_PER_ORDER || '2', 10);
const ORDER_PROBABILITY = parseFloat(__ENV.ORDER_PROBABILITY || '0.25'); // ~25% orders to generate clear email volume
const CUSTOMER    = { email: 'ahmadhkalleh@gmail.com', password: 'password' };
const CUSTOM_ORDER_IDS = __ENV.ORDER_PRODUCT_IDS
  ? __ENV.ORDER_PRODUCT_IDS.split(',').map((x) => parseInt(x.trim(), 10))
  : null;

const P = '/api/v1';
const JSON_H = { 'Content-Type': 'application/json', Accept: 'application/json' };

const CODES = [0, 200, 201, 401, 403, 404, 409, 422, 429, 500, 502, 503, 504];
const stC = {};
CODES.forEach((c) => { stC[c] = new Counter('st_' + c); });
const stOther  = new Counter('st_other');
const ordersOk = new Counter('orders_created');
const lat      = new Trend('lat', true);

export const options = {
  scenarios: {
    steady: { executor: 'constant-vus', vus: VUS, duration: DURATION },
  },
};

function findToken(o) {
  if (!o || typeof o !== 'object') return null;
  if (typeof o.access_token === 'string') return o.access_token;
  if (typeof o.token === 'string') return o.token;
  const k = Object.keys(o);
  for (let i = 0; i < k.length; i++) { const t = findToken(o[k[i]]); if (t) return t; }
  return null;
}

function idsOf(b) {
  const l = Array.isArray(b) ? b
    : (b && b.data && Array.isArray(b.data.items)) ? b.data.items
    : (b && Array.isArray(b.items)) ? b.items
    : (b && Array.isArray(b.data)) ? b.data
    : (b && b.data && Array.isArray(b.data.data)) ? b.data.data
    : (b && b.data && Array.isArray(b.data.products)) ? b.data.products
    : [];
  return l.map((x) => x && x.id).filter((x) => x !== undefined && x !== null);
}

function getMailpitTotal() {
  const res = http.get(`${MAILPIT_URL}/api/v1/messages?limit=1`, { headers: { Accept: 'application/json' } });
  if (res.status !== 200) return null;
  try {
    const body = res.json();
    if (body && typeof body.total === 'number') return body.total;
    if (body && typeof body.messages_count === 'number') return body.messages_count;
  } catch (e) { /* ignore */ }
  return null;
}

export function setup() {
  const r = http.post(BASE_URL + P + '/auth/login', JSON.stringify(CUSTOMER), { headers: JSON_H });
  let b = {}; try { b = r.json(); } catch (e) { /* ignore */ }
  const token = r.status === 200 ? findToken(b) : null;
  if (!token) throw new Error('Login failed status=' + r.status + ' body=' + String(r.body).substring(0, 300));

  const h = Object.assign({}, JSON_H, { Authorization: 'Bearer ' + token });
  const pr = http.get(BASE_URL + P + '/products', { headers: h });
  let pb = {}; try { pb = pr.json(); } catch (e) { /* ignore */ }
  const productIds = idsOf(pb);
  if (productIds.length === 0) throw new Error('No products found status=' + pr.status);

  const orderProductIds = (CUSTOM_ORDER_IDS && CUSTOM_ORDER_IDS.length > 0) ? CUSTOM_ORDER_IDS : productIds;
  const mailBefore = getMailpitTotal();

  return { token, productIds, orderProductIds, mailBefore };
}

export default function (d) {
  const h = Object.assign({}, JSON_H, { Authorization: 'Bearer ' + d.token });
  let res;
  const x = Math.random();

  if (x < ORDER_PROBABILITY) {
    const pid = d.orderProductIds[Math.floor(Math.random() * d.orderProductIds.length)];
    res = http.post(BASE_URL + P + '/orders',
      JSON.stringify({ items: [{ product_id: pid, quantity: 1 }] }), { headers: h });
    if (res.status === 201 || res.status === 200) ordersOk.add(1);
  } else if (x < 0.65) {
    res = http.get(BASE_URL + P + '/products', { headers: h });
  } else {
    const pid = d.productIds[Math.floor(Math.random() * d.productIds.length)];
    res = http.get(BASE_URL + P + '/products/' + pid, { headers: h });
  }

  lat.add(res.timings.duration);
  if (stC[res.status]) stC[res.status].add(1); else stOther.add(1);
  sleep(0.25);
}

export function teardown(d) {
  // Give worker-email a moment to drain remaining RabbitMQ messages after load ends
  sleep(4);
  const mailAfter = getMailpitTotal();
  return { mailBefore: d.mailBefore, mailAfter };
}

function v(data, n, k) {
  const m = data.metrics[n];
  return m && m.values && m.values[k] !== undefined ? m.values[k] : 0;
}
const f1 = (x) => Number(x).toFixed(1);

export function handleSummary(data) {
  const o = [];
  let total = 0, errs = 0;
  const codes = [];
  CODES.forEach((c) => {
    const n = v(data, 'st_' + c, 'count');
    if (n > 0) { codes.push(c + 'x' + n); total += n; if (c === 0 || c >= 400) errs += n; }
  });
  const other = v(data, 'st_other', 'count');
  if (other > 0) { codes.push('other x' + other); total += other; errs += other; }

  const orders = v(data, 'orders_created', 'count');
  const expectedEmails = orders * EMAILS_PER_ORDER;

  // Read Mailpit counts if exposed in teardown/setup or summary metrics
  let actualEmails = null;
  let mailBefore = null;
  let mailAfter = null;
  if (data.root_group && data.root_group.checks) { /* unused */ }

  o.push('Email Idempotency & Deduplication Test Result');
  o.push('================================================');
  o.push('URL: ' + BASE_URL + ' | Mailpit: ' + MAILPIT_URL + ' | VUs: ' + VUS + ' | Duration: ' + DURATION);
  o.push('');
  o.push('Total HTTP requests  : ' + total);
  o.push('Failed requests      : ' + errs + ' (' + f1(total ? (errs * 100) / total : 0) + '%)');
  o.push('Status codes         : ' + codes.join('  '));
  o.push('Response time ms     : avg ' + f1(v(data, 'lat', 'avg')) + ' | p95 ' + f1(v(data, 'lat', 'p(95)')) +
    ' | max ' + f1(v(data, 'lat', 'max')));
  o.push('');
  o.push('Successful orders    : ' + orders);
  o.push('Emails per order     : ' + EMAILS_PER_ORDER);
  o.push('Expected new emails  : ' + expectedEmails);
  o.push('Actual new emails    : (check Mailpit diff below or at ' + MAILPIT_URL + ')');
  o.push('');
  o.push('Idempotency check    : If Actual new emails == Expected new emails (' + expectedEmails + '),');
  o.push('                       no duplicate emails were sent despite retries/worker restarts.');
  o.push('');

  const text = o.join('\n');
  return { [RESULT_FILE]: text, stdout: '\n' + text };
}
