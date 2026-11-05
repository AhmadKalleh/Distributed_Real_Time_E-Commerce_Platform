// Sequential authentication/authorization smoke test for the Orders API.
// Node.js 20+ is required. The API must already be running.

const apiBaseUrl = (process.env.API_BASE_URL || 'http://127.0.0.1:8080/api/v1').replace(/\/+$/, '');
const testPath = process.env.AUTH_TEST_PATH || '/orders';
const testMethod = (process.env.AUTH_TEST_METHOD || 'GET').toUpperCase();
const expectedUserAStatus = Number(process.env.AUTH_EXPECTED_USER_A_STATUS || 200);
const expectedUserBStatus = Number(process.env.AUTH_EXPECTED_USER_B_STATUS || 200);
const rawBody = process.env.AUTH_TEST_BODY;

const users = [
  {
    label: 'User A',
    email: process.env.AUTH_USER_A_EMAIL,
    password: process.env.AUTH_USER_A_PASSWORD,
    expectedStatus: expectedUserAStatus,
  },
  {
    label: 'User B',
    email: process.env.AUTH_USER_B_EMAIL,
    password: process.env.AUTH_USER_B_PASSWORD,
    expectedStatus: expectedUserBStatus,
  },
];

for (const user of users) {
  if (!user.email || !user.password) {
    throw new Error('Set AUTH_USER_A_EMAIL, AUTH_USER_A_PASSWORD, AUTH_USER_B_EMAIL, and AUTH_USER_B_PASSWORD.');
  }
}

if (!Number.isInteger(expectedUserAStatus) || !Number.isInteger(expectedUserBStatus) ||
    expectedUserAStatus < 100 || expectedUserAStatus > 599 ||
    expectedUserBStatus < 100 || expectedUserBStatus > 599) {
  throw new Error('Expected HTTP statuses must be integers between 100 and 599.');
}

let body;
if (rawBody !== undefined) {
  try {
    body = JSON.parse(rawBody);
  } catch {
    throw new Error('AUTH_TEST_BODY must contain valid JSON.');
  }
}

function endpoint(path) {
  return `${apiBaseUrl}/${path.replace(/^\/+/, '')}`;
}

async function send(path, { method = 'GET', token, json } = {}) {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (json !== undefined) headers['Content-Type'] = 'application/json';

  return fetch(endpoint(path), {
    method,
    headers,
    ...(json === undefined ? {} : { body: JSON.stringify(json) }),
  });
}

async function login(user) {
  const response = await send('/auth/login', {
    method: 'POST',
    json: { email: user.email, password: user.password },
  });
  const result = await response.json().catch(() => ({}));

  if (response.status !== 200 || typeof result?.data?.access_token !== 'string') {
    throw new Error(`${user.label} login failed (HTTP ${response.status}).`);
  }

  console.log(`${user.label} login: HTTP ${response.status}`);
  return result.data.access_token;
}

function checkStatus(label, actual, expected, failures) {
  const passed = actual === expected;
  console.log(`${passed ? 'PASS' : 'FAIL'} ${label}: expected HTTP ${expected}, got HTTP ${actual}`);
  if (!passed) failures.push(label);
}

async function requestProtected(user, token, failures) {
  const response = await send(testPath, {
    method: testMethod,
    token,
    json: body,
  });
  checkStatus(`${user.label} ${testMethod} ${testPath}`, response.status, user.expectedStatus, failures);
}

const failures = [];
let userAToken;
let userBToken;

try {
  userAToken = await login(users[0]);
  await requestProtected(users[0], userAToken, failures);

  const logoutA = await send('/auth/logout', { method: 'POST', token: userAToken });
  checkStatus('User A logout', logoutA.status, 200, failures);

  const revokedTokenRequest = await send(testPath, {
    method: testMethod,
    token: userAToken,
    json: body,
  });
  checkStatus('User A request after logout', revokedTokenRequest.status, 401, failures);

  userBToken = await login(users[1]);
  await requestProtected(users[1], userBToken, failures);

  const logoutB = await send('/auth/logout', { method: 'POST', token: userBToken });
  checkStatus('User B logout', logoutB.status, 200, failures);
} catch (error) {
  console.error(`ERROR: ${error.message}`);
  process.exitCode = 1;
}

if (failures.length > 0) {
  console.error(`FAIL: ${failures.length} authorization check(s) did not match.`);
  process.exitCode = 1;
} else if (process.exitCode !== 1) {
  console.log('PASS: login, protected request, logout revocation, and second-user flow completed.');
}