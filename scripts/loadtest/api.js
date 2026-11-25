import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = (__ENV.BASE_URL || 'http://18.213.161.69').replace(/\/+$/, '');
const KEY = __ENV.API_KEY || '';
const PROFILE = __ENV.PROFILE || 'smoke';
const STAGES = (__ENV.STAGES || '10,25,50,100,200').split(',').map((v) => parseInt(v, 10)).filter((v) => v > 0);
const STEP = __ENV.STEP || '3m';
const P95_MS = parseInt(__ENV.P95_MS || '2000', 10);
const PAUSE = parseFloat(__ENV.PAUSE || '0.5');

const STATES = ['NY', 'CO', 'NC', 'MI', 'IL', 'WA', 'VA', 'NJ', 'KY', 'AZ', 'FL', 'TX', 'CA', 'GA', 'MA'];
const TYPES = ['LLC', 'Corporation', 'Foreign LLC'];
const WORDS = ['holdings', 'consulting', 'properties', 'capital', 'group', 'services', 'solutions', 'investments', 'management', 'realty'];
const ENDPOINTS = ['list', 'list_state', 'list_state_type', 'search', 'search_state', 'page2', 'view', 'filing'];

const rateLimited = new Counter('rate_limited');

function stages() {
    const out = [];
    for (const target of STAGES) {
        out.push({ duration: '30s', target });
        out.push({ duration: STEP, target });
    }
    out.push({ duration: '30s', target: 0 });
    return out;
}

const scenarios = {
    smoke: { executor: 'constant-vus', vus: 2, duration: '1m' },
    ramp: { executor: 'ramping-vus', startVUs: 1, stages: stages(), gracefulRampDown: '10s' },
};

const ABORT = PROFILE === 'ramp';
const thresholds = {
    http_req_failed: [{ threshold: 'rate<0.01', abortOnFail: ABORT, delayAbortEval: '30s' }],
    http_req_duration: [{ threshold: `p(95)<${P95_MS}`, abortOnFail: ABORT, delayAbortEval: '30s' }],
    rate_limited: ['count<1'],
};
for (const name of ENDPOINTS) {
    thresholds[`http_req_duration{name:${name}}`] = [`p(95)<${P95_MS}`];
}

export const options = {
    scenarios: { [PROFILE]: scenarios[PROFILE] || fail(`PROFILE must be smoke or ramp, got ${PROFILE}`) },
    thresholds,
    summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
    userAgent: 'EntityIndex-loadtest/1.0',
};

const headers = { Authorization: `Bearer ${KEY}`, Accept: 'application/json' };

function get(path, name) {
    const res = http.get(`${BASE}${path}`, { headers, tags: { name } });
    if (res.status === 429) {
        rateLimited.add(1);
    }
    check(res, { [`${name} 200`]: (r) => r.status === 200 });
    return res;
}

function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
}

export function setup() {
    if (!KEY) {
        fail('Set API_KEY to an admin API key: k6 run -e API_KEY=... scripts/loadtest/api.js');
    }
    const me = http.get(`${BASE}/api/v1/me`, { headers });
    if (me.status !== 200) {
        fail(`GET /api/v1/me answered ${me.status}: ${me.body}`);
    }
    const perMinute = me.json('quota.rate_limit_per_minute');
    if (perMinute !== null && perMinute !== undefined) {
        console.warn(`This key (plan ${me.json('plan')}) is limited to ${perMinute} requests a minute; use an admin key or the test only measures the rate limit.`);
    }
    const samples = [];
    const cursors = [];
    for (const state of STATES.slice(0, 5)) {
        const res = http.get(`${BASE}/api/v1/entities?state=${state}&order=desc&limit=100`, { headers });
        if (res.status !== 200) {
            continue;
        }
        const body = res.json();
        for (const e of body.data) {
            if (e.state && e.filing_number && /^[A-Za-z0-9-]+$/.test(e.filing_number)) {
                samples.push({ id: e.id, state: e.state, filing: e.filing_number });
            }
        }
        if (body.next_cursor) {
            cursors.push({ state, cursor: body.next_cursor });
        }
    }
    if (!samples.length) {
        fail('No companies came back from /api/v1/entities; check BASE_URL and the key.');
    }
    return { samples, cursors };
}

export default function (data) {
    const roll = Math.random();
    if (roll < 0.15) {
        get('/api/v1/entities?order=desc&limit=25', 'list');
    } else if (roll < 0.3) {
        get(`/api/v1/entities?state=${pick(STATES)}&order=desc&limit=50`, 'list_state');
    } else if (roll < 0.42) {
        get(`/api/v1/entities?state=${pick(STATES)}&type=${encodeURIComponent(pick(TYPES))}&limit=50`, 'list_state_type');
    } else if (roll < 0.54) {
        get(`/api/v1/entities?q=${pick(WORDS)}&limit=25`, 'search');
    } else if (roll < 0.64) {
        get(`/api/v1/entities?q=${pick(WORDS)}&state=${pick(STATES)}&limit=25`, 'search_state');
    } else if (roll < 0.7 && data.cursors.length) {
        const c = pick(data.cursors);
        get(`/api/v1/entities?state=${c.state}&order=desc&limit=100&cursor=${c.cursor}`, 'page2');
    } else if (roll < 0.85) {
        get(`/api/v1/entities/${pick(data.samples).id}`, 'view');
    } else {
        const s = pick(data.samples);
        get(`/api/v1/entities/${s.state}/${s.filing}`, 'filing');
    }
    sleep(PAUSE * (0.5 + Math.random()));
}
