import http from 'k6/http';
import { check, sleep } from 'k6';

const baseUrl = (__ENV.API_BASE_URL || 'http://localhost:8000').replace(/\/$/, '');
const vus = Number(__ENV.VUS || 10);
const duration = __ENV.DURATION || '60s';
const label = __ENV.LABEL || 'baseline'; // مثال: LABEL=before أو LABEL=after

const ENDPOINTS = ['products-list', 'product-details', 'orders-list', 'order-details'];

// thresholds الوهمية (max>=0) تجعل k6 يحسب أرقام كل endpoint على حدة دون أن تفشل أبداً
const perEndpoint = {};
ENDPOINTS.forEach((name) => {
    perEndpoint[`http_req_duration{endpoint:${name}}`] = ['max>=0'];
});

export const options = {
    vus,
    duration,
    summaryTrendStats: ['p(50)', 'p(95)', 'p(99)'],
    thresholds: {
        http_req_failed: ['rate<0.01'],
        checks: ['rate>0.99'],
        ...perEndpoint,
    },
};

function get(path, name) {
    const response = http.get(`${baseUrl}/api/performance/baseline${path}`, {
        tags: { endpoint: name },
        timeout: '60s',
    });

    check(response, {
        [`${name} returns HTTP 200`]: (result) => result.status === 200,
    });
}

export default function () {
    const productId = ((__VU * 997 + __ITER * 101) % 5000) + 1;
    const orderId = ((__VU * 9973 + __ITER * 101) % 100000) + 1;

    get('/products?per_page=20', 'products-list');
    get(`/products/${productId}`, 'product-details');
    get('/orders?per_page=15', 'orders-list');
    get(`/orders/${orderId}`, 'order-details');

    sleep(0.5);
}

// ---------- ملخص نظيف ----------
const fmt = (ms) => (ms === undefined ? '-' : ms >= 1000 ? `${(ms / 1000).toFixed(2)} s` : `${ms.toFixed(0)} ms`);

function row(name, metric) {
    const v = metric ? metric.values : {};
    return {
        name,
        p50: v['p(50)'],
        p95: v['p(95)'],
        p99: v['p(99)'],
    };
}

export function handleSummary(data) {
    const m = data.metrics;
    const rows = ENDPOINTS.map((name) => row(name, m[`http_req_duration{endpoint:${name}}`]));
    rows.push(row('ALL (overall)', m.http_req_duration));

    const pad = (s, n) => String(s).padEnd(n);
    const lines = [];
    lines.push('');
    lines.push(`=== Response time [${label}] | VUs=${vus} | duration=${duration} ===`);
    lines.push(pad('Endpoint', 18) + pad('p50', 12) + pad('p95', 12) + pad('p99', 12));
    lines.push('-'.repeat(54));
    rows.forEach((r) => {
        lines.push(pad(r.name, 18) + pad(fmt(r.p50), 12) + pad(fmt(r.p95), 12) + pad(fmt(r.p99), 12));
    });
    lines.push('-'.repeat(54));
    lines.push(`Total requests : ${m.http_reqs.values.count}  (${m.http_reqs.values.rate.toFixed(2)} req/s)`);
    lines.push(`Failed requests: ${(m.http_req_failed.values.rate * 100).toFixed(2)} %`);
    lines.push('');

    const json = {
        label,
        vus,
        duration,
        total_requests: m.http_reqs.values.count,
        req_per_sec: Number(m.http_reqs.values.rate.toFixed(2)),
        failed_percent: Number((m.http_req_failed.values.rate * 100).toFixed(2)),
        response_time_ms: Object.fromEntries(
            rows.map((r) => [r.name, { p50: r.p50, p95: r.p95, p99: r.p99 }])
        ),
    };

    return {
        stdout: lines.join('\n'),
        [`${label}-summary.json`]: JSON.stringify(json, null, 2),
    };
}
