// Failover test: light continuous load (reads + occasional orders) while stopping and restarting a replica.
// The result file shows, per instance, how many requests it served BEFORE, DURING and AFTER the outage.
//
// Run (see the PowerShell block that stops/starts app2 at 15s / 35s):
//   k6 run -e BASE_URL=http://localhost:8080 -e NAMES=$names -e STOP_AT=15 -e START_AT=35 failover-test.js
// Output: failover-result.txt

import http from 'k6/http';
import { sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE_URL    = __ENV.BASE_URL || 'http://localhost:8080';
const DURATION    = __ENV.DURATION || '75s';
const VUS         = parseInt(__ENV.VUS || '20', 10);
const RESULT_FILE = __ENV.RESULT_FILE || 'failover-result.txt';
const STOP_AT     = parseFloat(__ENV.STOP_AT || '15');   // seconds since load start
const START_AT    = parseFloat(__ENV.START_AT || '35');  // seconds since load start
const NAMES_RAW   = __ENV.NAMES || '';                   // "172.19.0.3=app1,172.19.0.4=app2,..."
const CUSTOMER    = { email: 'ahmadhkalleh@gmail.com', password: 'password' };
const CUSTOM_ORDER_IDS = __ENV.ORDER_PRODUCT_IDS
  ? __ENV.ORDER_PRODUCT_IDS.split(',').map((x) => parseInt(x.trim(), 10))
  : null;
const ORDER_PROBABILITY = 0.01; // ~1 order per 100 requests
const SLOW_MS = 1000;           // a request slower than this is listed as "slow"

const P = '/api/v1';
const JSON_H = { 'Content-Type': 'application/json', Accept: 'application/json' };

// ---- Metrics ----
const CODES = [0, 200, 201, 401, 403, 404, 409, 422, 429, 500, 502, 503, 504];
const stC = {};
CODES.forEach((c) => { stC[c] = new Counter('st_' + c); });
const stOther  = new Counter('st_other');
const ordersOk = new Counter('orders_created');
const errTime  = new Trend('err_time_s');   // second each error occurred since load start
const lat      = new Trend('lat', true);
const slowTime = new Trend('slow_time_s');  // second each slow request occurred
const retryTime = new Trend('retry_time_s');// second each Nginx retry occurred
const retried  = new Counter('retried');
const unknown  = new Counter('srv_unknown');

// served-by counters: phase (0 before, 1 during, 2 after) x last octet of the instance IP
const PHASE_NAMES = ['BEFORE', 'DURING', 'AFTER'];
const srv = [{}, {}, {}];
const errPhase = [new Counter('err_p0'), new Counter('err_p1'), new Counter('err_p2')];
for (let ph = 0; ph < 3; ph++) {
  for (let o = 1; o <= 254; o++) srv[ph][o] = new Counter('p' + ph + '_' + o);
}

const NAME_BY_OCTET = {};
NAMES_RAW.split(',').forEach((pair) => {
  const kv = pair.split('=');
  if (kv.length === 2) {
    const m = kv[0].trim().match(/(\d+)\.(\d+)\.(\d+)\.(\d+)/);
    if (m) NAME_BY_OCTET[parseInt(m[4], 10)] = kv[1].trim();
  }
});

export const options = {
  scenarios: {
    steady: { executor: 'constant-vus', vus: VUS, duration: DURATION },
  },
};

// ---- Helpers ----
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

function servedBy(res) {
  const h = res.headers;
  for (const k in h) { if (k.toLowerCase() === 'x-served-by') return h[k]; }
  return '';
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
  return { token, productIds, orderProductIds, startTs: Date.now() };
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
  } else if (x < 0.4) {
    res = http.get(BASE_URL + P + '/categories', { headers: h });
  } else if (x < 0.7) {
    res = http.get(BASE_URL + P + '/products', { headers: h });
  } else {
    const pid = d.productIds[Math.floor(Math.random() * d.productIds.length)];
    res = http.get(BASE_URL + P + '/products/' + pid, { headers: h });
  }

  const t = (Date.now() - d.startTs) / 1000;
  const ph = t < STOP_AT ? 0 : (t < START_AT ? 1 : 2);

  lat.add(res.timings.duration);
  if (res.timings.duration > SLOW_MS) slowTime.add(t);
  if (stC[res.status]) stC[res.status].add(1); else stOther.add(1);
  if (res.status === 0 || res.status >= 400) { errTime.add(t); errPhase[ph].add(1); }

  // which instance served it (the LAST address in X-Served-By; several = Nginx retried)
  const sb = servedBy(res);
  if (!sb) { unknown.add(1); }
  else {
    const parts = sb.split(',');
    if (parts.length > 1) { retried.add(1); retryTime.add(t); }
    const m = parts[parts.length - 1].trim().match(/(\d+)\.(\d+)\.(\d+)\.(\d+)/);
    const o = m ? parseInt(m[4], 10) : 0;
    if (srv[ph][o]) srv[ph][o].add(1); else unknown.add(1);
  }
  sleep(0.2);
}

// ---- Result file ----
function v(data, n, k) {
  const m = data.metrics[n];
  return m && m.values && m.values[k] !== undefined ? m.values[k] : 0;
}
const f1 = (x) => Number(x).toFixed(1);
function pad(s, w) { s = String(s); while (s.length < w) s += ' '; return s; }
function lpad(s, w) { s = String(s); while (s.length < w) s = ' ' + s; return s; }

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

  o.push('Failover Test Result (stopping a replica during load)');
  o.push('======================================================');
  o.push('URL: ' + BASE_URL + ' | VUs: ' + VUS + ' | Duration: ' + DURATION);
  o.push('');
  o.push('Total requests       : ' + total);
  o.push('Failed requests      : ' + errs + ' (' + f1(total ? (errs * 100) / total : 0) + '%)');
  o.push('Status codes         : ' + codes.join('  '));
  o.push('Response time ms     : avg ' + f1(v(data, 'lat', 'avg')) + ' | p95 ' + f1(v(data, 'lat', 'p(95)')) +
    ' | max ' + f1(v(data, 'lat', 'max')));
  if (errs > 0) {
    o.push('Error timestamps     : from ' + f1(v(data, 'err_time_s', 'min')) +
      's to ' + f1(v(data, 'err_time_s', 'max')) + 's since load start');
  } else {
    o.push('Error timestamps     : no errors');
  }
  const slowN = v(data, 'slow_time_s', 'count');
  if (slowN > 0) {
    o.push('Slow requests (>' + SLOW_MS + 'ms): ' + slowN + ' (between ' + f1(v(data, 'slow_time_s', 'min')) +
      's and ' + f1(v(data, 'slow_time_s', 'max')) + 's)');
  } else {
    o.push('Slow requests (>' + SLOW_MS + 'ms): none');
  }
  o.push('');

  // ---- Per-instance table: BEFORE / DURING / AFTER ----
  o.push('---------- Requests served per instance ----------');
  o.push('Windows (seconds since load start): BEFORE 0-' + STOP_AT + ' | DURING ' + STOP_AT + '-' + START_AT +
    ' (instance stopped) | AFTER ' + START_AT + '-end (instance restarted). Boundaries are approximate (about 1-2s).');
  const rows = [];
  const sums = [0, 0, 0];
  for (let k = 1; k <= 254; k++) {
    const c = [0, 1, 2].map((ph) => v(data, 'p' + ph + '_' + k, 'count'));
    if (c[0] + c[1] + c[2] > 0) rows.push({ k: k, c: c });
    c.forEach((n, ph) => { sums[ph] += n; });
  }
  o.push('');
  o.push(pad('Instance', 18) + lpad('BEFORE', 10) + lpad('DURING', 10) + lpad('AFTER', 10));
  rows.forEach((r) => {
    const name = NAME_BY_OCTET[r.k] ? NAME_BY_OCTET[r.k] : 'instance .' + r.k;
    const cell = (ph) => lpad(r.c[ph], 6) + ' ' + lpad('(' + Math.round(sums[ph] ? (r.c[ph] * 100) / sums[ph] : 0) + '%)', 3);
    o.push(pad(name, 18) + lpad(r.c[0] + ' (' + Math.round(sums[0] ? (r.c[0] * 100) / sums[0] : 0) + '%)', 10) +
      lpad(r.c[1] + ' (' + Math.round(sums[1] ? (r.c[1] * 100) / sums[1] : 0) + '%)', 10) +
      lpad(r.c[2] + ' (' + Math.round(sums[2] ? (r.c[2] * 100) / sums[2] : 0) + '%)', 10));
  });
  o.push(pad('Total', 18) + lpad(sums[0], 10) + lpad(sums[1], 10) + lpad(sums[2], 10));
  o.push(pad('Errors', 18) + lpad(v(data, 'err_p0', 'count'), 10) + lpad(v(data, 'err_p1', 'count'), 10) +
    lpad(v(data, 'err_p2', 'count'), 10));
  o.push('');
  const retN = v(data, 'retried', 'count');
  if (retN > 0) {
    o.push('Retried by Nginx: ' + retN + ' requests (first instance failed, another served it), between ' +
      f1(v(data, 'retry_time_s', 'min')) + 's and ' + f1(v(data, 'retry_time_s', 'max')) + 's');
  } else {
    o.push('Retried by Nginx: 0 requests');
  }
  const unk = v(data, 'srv_unknown', 'count');
  if (unk > 0) o.push('Requests without X-Served-By: ' + unk);
  o.push('');
  o.push('How to read: BEFORE all instances share the load. DURING the stopped instance drops to about zero');
  o.push('and the others absorb its traffic with no errors. AFTER it returns and receives requests again');
  o.push('(a lower share at first, because the restarted container needs time to boot).');
  o.push('');
  o.push('Successful orders    : ' + orders);
  o.push('Expected Mailpit msgs: ' + (orders * 2) + '  (2 emails per order)');
  o.push('Actual               : (from Mailpit) ______');
  o.push('');
  const text = o.join('\n');
  return { [RESULT_FILE]: text, stdout: '\n' + text };
}
