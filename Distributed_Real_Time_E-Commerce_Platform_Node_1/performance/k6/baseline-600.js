// Load test: exactly 600 requests, plain-text result file (with per-instance distribution).
//
// Before (single instance, direct):
//   k6 run -e BASE_URL=http://localhost:8080 -e RESULT_FILE=result-before.txt baseline-600.js
// After (behind Nginx, 3 replicas):
//   k6 run -e BASE_URL=http://localhost:8080 -e RESULT_FILE=result-after.txt -e LABEL="After (3 replicas)" -e NAMES=$names baseline-600.js
//
// NAMES (optional) maps instance IPs to names, e.g. "172.19.0.6=app1,172.19.0.7=app2,172.19.0.11=app3"
// Before each run: flush Redis, purge queues, clear Mailpit.

import http from 'k6/http';
import exec from 'k6/execution';
import { Counter, Trend } from 'k6/metrics';

// ================= Settings =================
const BASE_URL    = __ENV.BASE_URL    || 'http://localhost:8080';
const RESULT_FILE = __ENV.RESULT_FILE || 'result-before.txt';
const LABEL       = __ENV.LABEL       || 'Before (single instance)';
const VUS         = parseInt(__ENV.VUS || '20', 10);
const NAMES_RAW   = __ENV.NAMES || '';

const CUSTOMER = { email: 'ahmadhkalleh@gmail.com', password: 'password' };
const ADMIN    = { email: 'mohahmk03@gmail.com',    password: 'password' };

// Products that have stock in the inventory service
const ORDER_PRODUCT_IDS = (__ENV.ORDER_PRODUCT_IDS || '1,2,3,4,5,6,7,8')
  .split(',').map((x) => parseInt(x.trim(), 10));

const SEED_ORDERS = 3;   // created in setup, outside the 600, so order-detail has real orders
const WARMUP_REQUESTS = 30;

const P = '/api/v1';
const LOGIN_PATH      = P + '/auth/login';
const CATEGORIES_PATH = P + '/categories';
const PRODUCTS_PATH   = P + '/products';
const ORDERS_PATH     = P + '/orders';

// ================= Phases (total 600) =================
const PHASES = [
  { key: 'categories',     label: 'Browse Categories',      count: 50  },
  { key: 'products_list',  label: 'Browse Products',        count: 200 },
  { key: 'product_detail', label: 'Product Detail',         count: 150 },
  { key: 'product_create', label: 'Create Product (Admin)', count: 50  },
  { key: 'order_create',   label: 'Create Order',           count: 50  },
  { key: 'orders_list',    label: 'Browse Orders',          count: 50  },
  { key: 'order_detail',   label: 'Order Detail',           count: 50  },
];
const TOTAL = PHASES.reduce((s, p) => s + p.count, 0); // 600

// ================= Metrics =================
const CODES = [0, 200, 201, 401, 403, 404, 409, 422, 429, 500, 502, 503, 504];
const trend = {}, okC = {}, errC = {}, stC = {};
PHASES.forEach((ph) => {
  trend[ph.key] = new Trend('t_' + ph.key, true);
  okC[ph.key]   = new Counter('ok_' + ph.key);
  errC[ph.key]  = new Counter('err_' + ph.key);
  stC[ph.key]   = {};
  CODES.forEach((c) => { stC[ph.key][c] = new Counter('s_' + ph.key + '_' + c); });
  stC[ph.key].other = new Counter('s_' + ph.key + '_other');
});
const tsStart = new Trend('ts_start');
const tsEnd   = new Trend('ts_end');
const allTime = new Trend('all_time', true);

// Per-instance metrics, keyed by the last octet of the instance IP (from Nginx's X-Served-By header)
const srvCount = {}, srvTime = {};
for (let o = 1; o <= 254; o++) {
  srvCount[o] = new Counter('srv_' + o);
  srvTime[o]  = new Trend('srv_t_' + o, true);
}
const srvUnknown = new Counter('srv_unknown'); // no header (direct access) or unparsable
const srvRetried = new Counter('srv_retried'); // Nginx retried the request on another instance

const NAME_BY_OCTET = {};
NAMES_RAW.split(',').forEach((pair) => {
  const kv = pair.split('=');
  if (kv.length === 2) {
    const m = kv[0].trim().match(/(\d+)\.(\d+)\.(\d+)\.(\d+)/);
    if (m) NAME_BY_OCTET[parseInt(m[4], 10)] = kv[1].trim();
  }
});

export const options = {
  batch: 30,
  batchPerHost: 30,
  scenarios: {
    main: {
      executor: 'shared-iterations',
      vus: VUS,
      iterations: TOTAL, // each iteration = one request
      maxDuration: '10m',
    },
  },
};

// ================= Helpers =================
const JSON_H = { 'Content-Type': 'application/json', Accept: 'application/json' };

function authHeaders(token) {
  return Object.assign({}, JSON_H, { Authorization: 'Bearer ' + token });
}
function body(res) {
  try { return res.json(); } catch (e) { return {}; }
}
function findToken(o) {
  if (!o || typeof o !== 'object') return null;
  if (typeof o.access_token === 'string') return o.access_token;
  if (typeof o.token === 'string') return o.token;
  const keys = Object.keys(o);
  for (let i = 0; i < keys.length; i++) {
    const t = findToken(o[keys[i]]);
    if (t) return t;
  }
  return null;
}
function listOf(b) {
  if (Array.isArray(b)) return b;
  if (b && Array.isArray(b.data)) return b.data;
  // shapes like { data: { orders: [...] } } or { data: { data: [...] } } or { data: { items: [...] } }
  if (b && b.data && typeof b.data === 'object') {
    const keys = Object.keys(b.data);
    for (let i = 0; i < keys.length; i++) {
      if (Array.isArray(b.data[keys[i]])) return b.data[keys[i]];
    }
  }
  return [];
}
function idsOf(b) {
  return listOf(b).map((x) => x && x.id).filter((x) => x !== undefined && x !== null);
}
function fail(msg, res) {
  throw new Error(msg + ' | status=' + res.status + ' | body=' + String(res.body).substring(0, 300));
}
function login(user, who) {
  const res = http.post(BASE_URL + LOGIN_PATH, JSON.stringify(user), { headers: JSON_H });
  const token = res.status === 200 ? findToken(body(res)) : null;
  if (!token) fail('Login failed for ' + who, res);
  return token;
}
function orderPayload(i) {
  return JSON.stringify({
    items: [{ product_id: ORDER_PRODUCT_IDS[i % ORDER_PRODUCT_IDS.length], quantity: 1 }],
  });
}
function servedBy(res) {
  const h = res.headers;
  for (const k in h) {
    if (k.toLowerCase() === 'x-served-by') return h[k];
  }
  return '';
}
function recordInstance(res) {
  const v = servedBy(res);
  if (!v) { srvUnknown.add(1); return; }
  const parts = v.split(',');
  if (parts.length > 1) srvRetried.add(1);
  const m = parts[parts.length - 1].trim().match(/(\d+)\.(\d+)\.(\d+)\.(\d+)/);
  if (!m) { srvUnknown.add(1); return; }
  const o = parseInt(m[4], 10);
  if (srvCount[o]) { srvCount[o].add(1); srvTime[o].add(res.timings.duration); } else srvUnknown.add(1);
}

// ================= Setup (outside the 600) =================
export function setup() {
  const customer = login(CUSTOMER, 'customer');
  const admin = login(ADMIN, 'admin');

  const cats = http.get(BASE_URL + CATEGORIES_PATH, { headers: authHeaders(customer) });
  const categoryIds = idsOf(body(cats));
  if (cats.status !== 200 || categoryIds.length === 0) fail('No categories found', cats);

  const prods = http.get(BASE_URL + PRODUCTS_PATH, { headers: authHeaders(customer) });
  const productIds = idsOf(body(prods));
  if (prods.status !== 200 || productIds.length === 0) fail('No products found', prods);

  for (let i = 0; i < SEED_ORDERS; i++) {
    const r = http.post(BASE_URL + ORDERS_PATH, orderPayload(i), { headers: authHeaders(customer) });
    if (r.status < 200 || r.status > 299) fail('Seed order #' + (i + 1) + ' failed', r);
  }
  const ords = http.get(BASE_URL + ORDERS_PATH, { headers: authHeaders(customer) });
  const orderIds = idsOf(body(ords)).slice(0, 10);
  if (ords.status !== 200 || orderIds.length === 0) fail('No orders found', ords);

  // Warm-up: parallel GETs so every instance wakes its workers (not counted in results)
  const warm = [];
  for (let w = 0; w < WARMUP_REQUESTS; w++) {
    warm.push(['GET', BASE_URL + CATEGORIES_PATH, null, { headers: authHeaders(customer) }]);
  }
  http.batch(warm);

  return { customer, admin, categoryIds, productIds, orderIds, runId: Date.now() };
}

// ================= One request =================
export default function (d) {
  const n = exec.scenario.iterationInTest; // 0..599

  let acc = 0;
  let phase = PHASES[PHASES.length - 1];
  let i = n;
  for (let k = 0; k < PHASES.length; k++) {
    if (n < acc + PHASES[k].count) { phase = PHASES[k]; i = n - acc; break; }
    acc += PHASES[k].count;
  }

  const cust = authHeaders(d.customer);
  let res;
  tsStart.add(Date.now());

  switch (phase.key) {
    case 'categories':
      res = http.get(BASE_URL + CATEGORIES_PATH, { headers: cust });
      break;
    case 'products_list':
      res = http.get(BASE_URL + PRODUCTS_PATH, { headers: cust });
      break;
    case 'product_detail': {
      // Half on 5 "hot" products (cache), half random (database)
      const hot = d.productIds.slice(0, 5);
      const pid = (i % 2 === 0)
        ? hot[Math.floor(i / 2) % hot.length]
        : d.productIds[Math.floor(Math.random() * d.productIds.length)];
      res = http.get(BASE_URL + PRODUCTS_PATH + '/' + pid, { headers: cust });
      break;
    }
    case 'product_create': {
      const payload = {
        category_id: d.categoryIds[i % d.categoryIds.length],
        name: 'LT-' + d.runId + '-' + i,
        description: 'Load test product #' + i,
        price: (10 + (i % 90)) + '.99',
        quantity: 100,
        is_active: true,
      };
      res = http.post(BASE_URL + PRODUCTS_PATH, JSON.stringify(payload), { headers: authHeaders(d.admin) });
      break;
    }
    case 'order_create':
      res = http.post(BASE_URL + ORDERS_PATH, orderPayload(i), { headers: cust });
      break;
    case 'orders_list':
      res = http.get(BASE_URL + ORDERS_PATH, { headers: cust });
      break;
    default: // order_detail
      res = http.get(BASE_URL + ORDERS_PATH + '/' + d.orderIds[i % d.orderIds.length], { headers: cust });
  }

  tsEnd.add(Date.now());
  trend[phase.key].add(res.timings.duration);
  allTime.add(res.timings.duration);
  recordInstance(res);
  if (res.status >= 200 && res.status <= 299) okC[phase.key].add(1); else errC[phase.key].add(1);
  if (stC[phase.key][res.status]) stC[phase.key][res.status].add(1); else stC[phase.key].other.add(1);
}

// ================= Result file =================
function val(data, name, key) {
  const m = data.metrics[name];
  return m && m.values && m.values[key] !== undefined ? m.values[key] : 0;
}
function f1(x) { return Number(x).toFixed(1); }

export function handleSummary(data) {
  const out = [];
  let sent = 0, errs = 0;

  const secs = (val(data, 'ts_end', 'max') - val(data, 'ts_start', 'min')) / 1000;

  out.push('Load Test Result: ' + LABEL);
  out.push('====================================');
  out.push('Target URL       : ' + BASE_URL);
  out.push('Virtual Users    : ' + VUS);
  out.push('');

  PHASES.forEach((ph) => {
    const ok = val(data, 'ok_' + ph.key, 'count');
    const er = val(data, 'err_' + ph.key, 'count');
    sent += ok + er; errs += er;
    out.push('[' + ph.label + ']  (planned ' + ph.count + ' | sent ' + (ok + er) + ' | errors ' + er + ')');
    out.push('  Response time ms: avg ' + f1(val(data, 't_' + ph.key, 'avg')) +
      ' | med ' + f1(val(data, 't_' + ph.key, 'med')) +
      ' | p95 ' + f1(val(data, 't_' + ph.key, 'p(95)')) +
      ' | max ' + f1(val(data, 't_' + ph.key, 'max')));
    const codes = [];
    CODES.forEach((c) => {
      const k = val(data, 's_' + ph.key + '_' + c, 'count');
      if (k > 0) codes.push(c + 'x' + k);
    });
    const o = val(data, 's_' + ph.key + '_other', 'count');
    if (o > 0) codes.push('other x' + o);
    out.push('  Status codes: ' + codes.join('  '));
    out.push('');
  });

  out.push('---------- Summary ----------');
  out.push('Requests sent        : ' + sent + ' of ' + TOTAL);
  out.push('Failed requests      : ' + errs + ' (' + f1(sent ? (errs * 100) / sent : 0) + '%)');
  out.push('Main phase duration  : ' + f1(secs) + 's (excluding setup)');
  out.push('Throughput           : ' + f1(secs > 0 ? sent / secs : 0) + ' req/s');
  out.push('All requests (ms)    : avg ' + f1(val(data, 'all_time', 'avg')) +
    ' | med ' + f1(val(data, 'all_time', 'med')) +
    ' | p95 ' + f1(val(data, 'all_time', 'p(95)')) +
    ' | max ' + f1(val(data, 'all_time', 'max')));
  out.push('');

  // ---- Per-instance distribution ----
  out.push('---------- Requests per instance (from X-Served-By) ----------');
  const rows = [];
  for (let o = 1; o <= 254; o++) {
    const c = val(data, 'srv_' + o, 'count');
    if (c > 0) rows.push({ o: o, c: c, avg: val(data, 'srv_t_' + o, 'avg'), p95: val(data, 'srv_t_' + o, 'p(95)') });
  }
  const unknown = val(data, 'srv_unknown', 'count');
  const retried = val(data, 'srv_retried', 'count');
  if (rows.length === 0) {
    out.push('No X-Served-By header (direct access, no load balancer): ' + unknown + ' requests');
  } else {
    let sum = 0, mx = 0, mn = Infinity;
    rows.forEach((r) => {
      sum += r.c;
      if (r.c > mx) mx = r.c;
      if (r.c < mn) mn = r.c;
    });
    rows.forEach((r) => {
      const name = NAME_BY_OCTET[r.o] ? NAME_BY_OCTET[r.o] : 'instance .' + r.o;
      out.push(name + '  : ' + r.c + ' requests (' + f1((r.c * 100) / sum) + '%)' +
        ' | avg ' + f1(r.avg) + ' ms | p95 ' + f1(r.p95) + ' ms');
    });
    out.push('Instances used      : ' + rows.length);
    out.push('Balance (max/min)   : ' + f1(mx / mn) + '  (1.0 = perfectly even)');
    out.push('Retried by Nginx    : ' + retried + ' requests');
    if (unknown > 0) out.push('Without header      : ' + unknown + ' requests');
  }
  out.push('');

  out.push('---------- Manual Post-Run Verification ----------');
  out.push('Expected Mailpit msgs : ' + ((PHASES[4].count + SEED_ORDERS) * 2) +
    '  = (' + PHASES[4].count + ' orders + ' + SEED_ORDERS + ' seed) * 2');
  out.push('Actual                : (from Mailpit) ______');
  out.push('Queues notification.email and notification.email.dlq: expected 0 and 0');
  if (sent !== TOTAL) out.push('WARNING: requests sent is not ' + TOTAL + ', check the log.');
  out.push('');

  const text = out.join('\n');
  return { [RESULT_FILE]: text, stdout: '\n' + text };
}
