<?php
/**
 * Smoke/integration tests: boots the app on PHP's built-in server against a THROWAWAY database
 * (migrate/fresh + seed/all), then exercises the public pages, forms, sitemaps, portal login and the coming-soon gate.
 *
 *   TEST_DB_DSN="mysql:host=127.0.0.1;dbname=entityindex_test" TEST_DB_USER=root TEST_DB_PASS= php tests/run.php
 *
 * WARNING: drops every table in the TEST_DB_DSN database. It never reads DB_* / .env for that reason.
 */

$root = dirname(__DIR__);
$dsn = getenv('TEST_DB_DSN');
if (!$dsn) {
    fwrite(STDERR, "Set TEST_DB_DSN (and TEST_DB_USER / TEST_DB_PASS) to a throwaway database. See the header of this file.\n");
    exit(2);
}
$env = array_merge(getenv(), [
    'YII_ENV' => 'test', 'YII_DEBUG' => 'false',
    'DB_DSN' => $dsn, 'DB_USER' => getenv('TEST_DB_USER') ?: 'root', 'DB_PASS' => getenv('TEST_DB_PASS') ?: '',
    'COOKIE_VALIDATION_KEY' => 'test-key-0123456789abcdef0123456789', 'SITE_URL' => 'http://localhost:18080', 'SITE_MODE' => 'live',
]);

$failures = 0;
$checks = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures++;
        echo "  FAIL  $name" . ($detail !== '' ? "  ($detail)" : '') . "\n";
    }
}

function run(string $cmd, array $env, string $cwd): void
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    if (proc_close($p) !== 0) {
        fwrite(STDERR, "Command failed: $cmd\n$out\n");
        exit(1);
    }
}

function startServer(int $port, array $env, string $root)
{
    $p = proc_open(['php', '-S', "127.0.0.1:$port", '-t', "$root/web", "$root/config/dev-router.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root, $env);
    for ($i = 0; $i < 50; $i++) {
        if (@fsockopen('127.0.0.1', $port)) {
            return $p;
        }
        usleep(100000);
    }
    fwrite(STDERR, "Server did not start on port $port\n");
    exit(1);
}

/** Minimal HTTP client with a cookie jar. */
class Http
{
    public string $jar;
    public function __construct(public string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'eijar');
    }
    public function req(string $method, string $path, array $post = [], array $headers = []): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $raw = curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $res = ['status' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
        curl_close($ch);
        $res['location'] = preg_match('/^Location:\s*(\S+)/mi', $res['headers'], $m) ? $m[1] : null;
        return $res;
    }
    public function get(string $path): array
    {
        return $this->req('GET', $path);
    }
    public function csrf(string $path): string
    {
        $b = $this->get($path)['body'];
        preg_match('/name="csrf-token" content="([^"]+)"/', $b, $m);
        return $m[1] ?? '';
    }
    public function postForm(string $path, array $fields, ?string $csrfFrom = null): array
    {
        $fields['_csrf'] = $this->csrf($csrfFrom ?? $path);
        return $this->req('POST', $path, $fields);
    }
    public function ajax(string $path, array $fields, string $csrfFrom): array
    {
        $t = $this->csrf($csrfFrom);
        return $this->req('POST', $path, $fields, ["X-CSRF-Token: $t", 'X-Requested-With: XMLHttpRequest']);
    }
}

echo "Preparing test database...\n";
run('php yii migrate/fresh --interactive=0', $env, $root);
run('php yii seed/all', $env, $root);

$srv = startServer(18080, $env, $root);
$h = new Http('http://127.0.0.1:18080');
echo "Public pages\n";
foreach (['/' => 'Every new US business', '/search?q=roofing&state=FL' => 'Results for', '/new' => 'New business filings', '/new/fl' => 'New Florida businesses',
    '/states' => 'Coverage', '/pricing' => 'Priced for teams', '/opt-out' => 'Remove my information', '/legal/privacy' => 'Privacy Policy', '/legal/terms' => 'Terms of Service'] as $path => $needle) {
    $r = $h->get($path);
    check("GET $path is 200", $r['status'] === 200, (string) $r['status']);
    check("GET $path contains '$needle'", str_contains($r['body'], $needle));
}
$r = $h->get('/new/florida');
check('/new/florida redirects to /new/fl (308)', $r['status'] === 308 && str_ends_with((string) $r['location'], '/new/fl'), $r['status'] . ' ' . $r['location']);
check('unknown state is 404', $h->get('/new/zz')['status'] === 404);
check('unknown path is 404', $h->get('/no-such-page')['status'] === 404);

echo "Company pages and search\n";
$r = $h->get('/search?q=7045435');
check('filing number search finds Redline Creative LLC', str_contains($r['body'], 'Redline Creative LLC') && str_contains($r['body'], '1 company'));
preg_match('#href="(/company/ny/redline-creative-llc-7045435)"#', $r['body'], $m);
check('search result links to the company page', isset($m[1]));
$r = $h->get('/company/ny/redline-creative-llc-7045435');
check('company page 200', $r['status'] === 200);
check('company page has canonical + JSON-LD', str_contains($r['body'], 'rel="canonical"') && str_contains($r['body'], 'application/ld+json'));
$r = $h->get('/company/new-york/old-name-7045435');
check('old slug / full state name redirects (308)', $r['status'] === 308 && str_ends_with((string) $r['location'], '/company/ny/redline-creative-llc-7045435'), $r['status'] . ' ' . $r['location']);
check('unknown company is 404', $h->get('/company/ny/nothing-0000000')['status'] === 404);
$all = $h->get('/search')['body'];
check('empty search lists everything (6,000 companies)', str_contains($all, '6,000 companies'));
check('stopword-only query does not error', $h->get('/search?q=the')['status'] === 200);
check('short query does not error', $h->get('/search?q=ab')['status'] === 200);
check('bad date filter is ignored, not an error', $h->get('/search?from=nope&to=2026-13-45')['status'] === 200);

echo "Sitemaps\n";
$r = $h->get('/sitemap.xml');
check('sitemap index is XML', $r['status'] === 200 && @simplexml_load_string($r['body']) !== false && str_contains($r['body'], 'sitemap-company-0.xml'));
$r = $h->get('/sitemap-company-0.xml');
check('company sitemap lists company URLs', $r['status'] === 200 && substr_count($r['body'], '/company/') >= 6000);
check('out-of-range sitemap is 404', $h->get('/sitemap-company-5.xml')['status'] === 404);

echo "Forms\n";
$r = $h->ajax('/api/waitlist', ['email' => 'test@example.com', 'plan' => 'pro', 'use_case' => 'Other', 'website' => ''], '/pricing');
check('waitlist accepts a valid email', $r['status'] === 200 && str_contains($r['body'], '"ok":true'));
$r = $h->ajax('/api/waitlist', ['email' => 'not-an-email'], '/pricing');
check('waitlist rejects a bad email (400)', $r['status'] === 400 && str_contains($r['body'], '"ok":false'));
$r = $h->ajax('/api/waitlist', ['email' => 'bot@example.com', 'website' => 'http://spam'], '/pricing');
check('honeypot looks successful', $r['status'] === 200);
$r = $h->req('POST', '/api/waitlist', ['email' => 'nocsrf@example.com']);
check('POST without CSRF token is rejected', $r['status'] === 400);
check('GET on the form endpoint is 405', $h->get('/api/waitlist')['status'] === 405);
$r = $h->ajax('/api/opt-out', ['entity_ref' => 'http://x/y', 'person_name' => 'Jane Doe', 'email' => 'jane@example.com', 'reason' => 'home address'], '/opt-out');
check('opt-out request accepted', $r['status'] === 200);
$r = $h->ajax('/api/opt-out', ['entity_ref' => '', 'person_name' => '', 'email' => 'x'], '/opt-out');
check('opt-out validates required fields (400)', $r['status'] === 400);

echo "Portal\n";
$p = new Http('http://127.0.0.1:18080');
foreach (['/portal', '/portal/account'] as $path) {
    $r = $p->get($path);
    check("anonymous $path redirects to login", $r['status'] === 302 && str_contains((string) $r['location'], '/portal/auth/login'), $r['status'] . ' ' . $r['location']);
}
check('login page renders AdminLTE', str_contains($p->get('/portal/auth/login')['body'], 'login-box'));
$r = $p->postForm('/portal/auth/register', ['RegisterForm' => ['name' => 'Test User', 'email' => 'user@example.com', 'password' => 'password1', 'password_repeat' => 'different']]);
check('register rejects mismatched passwords', $r['status'] === 200 && str_contains($r['body'], 'Passwords do not match'));
$r = $p->postForm('/portal/auth/register', ['RegisterForm' => ['name' => 'Test User', 'email' => 'user@example.com', 'password' => 'password1', 'password_repeat' => 'password1']]);
check('register succeeds and redirects to the dashboard', $r['status'] === 302 && str_ends_with((string) $r['location'], '/portal'), $r['status'] . ' ' . $r['location']);
$r = $p->get('/portal');
check('dashboard shows for the new user', $r['status'] === 200 && str_contains($r['body'], 'Free plan') && !str_contains($r['body'], 'Opt-out requests pending'));
$r = (new Http('http://127.0.0.1:18080'))->postForm('/portal/auth/register', ['RegisterForm' => ['name' => 'Dup', 'email' => 'USER@example.com', 'password' => 'password1', 'password_repeat' => 'password1']]);
check('duplicate email (case-insensitive) is rejected', str_contains($r['body'], 'already exists'));
$r = $p->postForm('/portal/account', ['AccountForm' => ['name' => 'Renamed', 'current_password' => 'bad', 'new_password' => 'newpassword1', 'new_password_repeat' => 'newpassword1']]);
check('password change needs the current password', str_contains($r['body'], 'Current password is incorrect'));
$r = $p->postForm('/portal/account', ['AccountForm' => ['name' => 'Renamed', 'current_password' => 'password1', 'new_password' => 'newpassword1', 'new_password_repeat' => 'newpassword1']]);
check('password change succeeds', $r['status'] === 302);
check('session survives the password change', $p->get('/portal/account')['status'] === 200);
check('logout via GET is refused', $p->get('/portal/auth/logout')['status'] === 405);
$t = $p->csrf('/portal');
$r = $p->req('POST', '/portal/auth/logout', ['_csrf' => $t]);
check('logout via POST redirects home', $r['status'] === 302);
check('portal is locked again after logout', $p->get('/portal')['status'] === 302);
$r = $p->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'user@example.com', 'password' => 'password1']]);
check('old password no longer works', $r['status'] === 200 && str_contains($r['body'], 'Incorrect email or password'));
$r = $p->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'user@example.com', 'password' => 'newpassword1']]);
check('new password logs in', $r['status'] === 302 && str_ends_with((string) $r['location'], '/portal'), $r['status'] . ' ' . $r['location']);

$l = new Http('http://127.0.0.1:18080');
for ($i = 0; $i < 5; $i++) {
    $l->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'victim@example.com', 'password' => "wrong$i"]]);
}
$r = $l->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'victim@example.com', 'password' => 'wrong-again']]);
check('login locks out after 5 failures', str_contains($r['body'], 'Too many failed attempts'));

run('php yii seed/admin admin@example.com Admin-pass-1', $env, $root);
$a = new Http('http://127.0.0.1:18080');
$a->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'admin@example.com', 'password' => 'Admin-pass-1']]);
$r = $a->get('/portal');
check('admin dashboard shows admin counters', str_contains($r['body'], 'Opt-out requests pending') && str_contains($r['body'], '1 on waitlist'));

proc_terminate($srv);

echo "Coming-soon gate\n";
$srv2 = startServer(18081, array_merge($env, ['SITE_MODE' => 'coming_soon']), $root);
$g = new Http('http://127.0.0.1:18081');
foreach (['/', '/search', '/company/ny/redline-creative-llc-7045435', '/pricing'] as $path) {
    check("gate: $path shows the waitlist page", str_contains($g->get($path)['body'], 'Join waitlist'));
}
check('gate: opt-out page stays reachable', str_contains($g->get('/opt-out')['body'], 'Remove my information'));
check('gate: legal pages stay reachable', str_contains($g->get('/legal/terms')['body'], 'Terms of Service'));
$r = $g->ajax('/api/waitlist', ['email' => 'gate@example.com'], '/coming-soon');
check('gate: waitlist API stays reachable', $r['status'] === 200);
proc_terminate($srv2);

echo "\n" . ($checks - $failures) . "/$checks checks passed" . ($failures ? ", $failures FAILED" : '') . ".\n";
exit($failures ? 1 : 0);
