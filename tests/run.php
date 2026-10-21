<?php
/**
 * Smoke/integration tests: boots the app on PHP's built-in server against a THROWAWAY database
 * (migrate/fresh + seed/all), then exercises the public pages, forms, sitemaps, portal login, the coming-soon gate and the
 * EIN skip-tracing sync (its source table `manual_companies` is faked inside the same test database).
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
    // The skip-tracing "production" source is a fake manual_companies table in the test database; never the real one.
    'DB_DSN_EIN_SKIP_TRACING' => $dsn, 'DB_USER_EIN_SKIP_TRACING' => getenv('TEST_DB_USER') ?: 'root', 'DB_PASS_EIN_SKIP_TRACING' => getenv('TEST_DB_PASS') ?: '',
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
foreach (['/' => 'Find new businesses', '/features' => 'Features', '/pricing' => 'Priced for teams', '/faq' => 'Frequently asked questions',
    '/about' => 'About EntityIndex', '/contact' => 'Contact us', '/opt-out' => 'Remove my information', '/legal/privacy' => 'Privacy Policy', '/legal/terms' => 'Terms of Service'] as $path => $needle) {
    $r = $h->get($path);
    check("GET $path is 200", $r['status'] === 200, (string) $r['status']);
    check("GET $path contains '$needle'", str_contains($r['body'], $needle));
}
$home = $h->get('/')['body'];
check('home links to sign-in and register', str_contains($home, '/portal/auth/login') && str_contains($home, '/portal/auth/register'));
check('home shows no company data (names or filing links)', !str_contains($home, '/portal/company/') && !str_contains($home, 'Redline Creative'));
check('contact topic preselects from the query string', str_contains($h->get('/contact?topic=sales')['body'], 'value="sales" selected'));
check('unknown path is 404', $h->get('/no-such-page')['status'] === 404);

echo "Product pages are not public\n";
foreach (['/search', '/search?q=roofing', '/company/ny/redline-creative-llc-7045435', '/new', '/new/fl', '/states'] as $path) {
    check("old public URL $path is gone (404)", $h->get($path)['status'] === 404);
}
foreach (['/portal', '/portal/search', '/portal/search?q=roofing', '/portal/company/ny/redline-creative-llc-7045435', '/portal/new', '/portal/new/fl', '/portal/states', '/portal/account'] as $path) {
    $r = $h->get($path);
    check("anonymous $path redirects to login", $r['status'] === 302 && str_contains((string) $r['location'], '/portal/auth/login'), $r['status'] . ' ' . $r['location']);
}

echo "noindex\n";
$hasMeta = fn(string $b) => (bool) preg_match('/<meta name="robots" content="noindex,nofollow">/', $b);
$hasHeader = fn(array $r) => (bool) preg_match('/^X-Robots-Tag:\s*noindex, nofollow/mi', $r['headers']);
foreach (['/', '/features', '/pricing', '/faq', '/about', '/contact', '/opt-out', '/legal/terms', '/portal/auth/login', '/portal/auth/register', '/no-such-page'] as $path) {
    $r = $h->get($path);
    check("noindex meta + header on $path", $hasMeta($r['body']) && $hasHeader($r), $r['status'] . '');
}
foreach (['/sitemap.xml', '/robots.txt'] as $path) {
    $r = $h->get($path);
    check("X-Robots-Tag header on $path", $hasHeader($r) || $path === '/robots.txt', $r['status'] . '');
}
check('JSON form endpoint carries the header', $hasHeader($h->ajax('/api/waitlist', ['email' => 'robots@example.com'], '/pricing')));
check('robots.txt does not Disallow: /', !preg_match('/^Disallow:\s*\/\s*$/mi', $h->get('/robots.txt')['body']));

echo "Sitemap\n";
$r = $h->get('/sitemap.xml');
check('sitemap is XML listing the public pages', $r['status'] === 200 && @simplexml_load_string($r['body']) !== false && str_contains($r['body'], '/features') && str_contains($r['body'], '/pricing'));
check('sitemap lists no portal or company URLs', !str_contains($r['body'], '/portal') && !str_contains($r['body'], '/company/'));
check('company sitemap files are gone', $h->get('/sitemap-company-0.xml')['status'] === 404);

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
$r = $h->ajax('/api/contact', ['name' => 'Pat Doe', 'email' => 'pat@example.com', 'topic' => 'sales', 'message' => 'Do you cover Wyoming?', 'website' => ''], '/contact');
check('contact message accepted', $r['status'] === 200 && str_contains($r['body'], '"ok":true'));
$r = $h->ajax('/api/contact', ['name' => 'Pat', 'email' => 'bad', 'message' => 'short'], '/contact');
check('contact validates email and message length (400)', $r['status'] === 400);
$r = $h->ajax('/api/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap pills now', 'website' => 'http://spam'], '/contact');
check('contact honeypot looks successful', $r['status'] === 200);
check('contact POST without CSRF is rejected', $h->req('POST', '/api/contact', ['name' => 'x', 'email' => 'x@example.com', 'message' => 'no csrf token here'])['status'] === 400);
$pdo = new PDO($dsn, getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASS') ?: '');
check('only the real contact message was stored', (int) $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn() === 1);

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
check('dashboard shows for the new user', $r['status'] === 200 && str_contains($r['body'], 'Your plan') && !str_contains($r['body'], 'opt-out requests pending'));
echo "Portal product pages (free plan)\n";
foreach (['/portal', '/portal/search', '/portal/new', '/portal/new/fl', '/portal/states', '/portal/account'] as $path) {
    $r = $p->get($path);
    check("portal $path renders AdminLTE", $r['status'] === 200 && str_contains($r['body'], 'main-sidebar') && $hasMeta($r['body']) && $hasHeader($r), (string) $r['status']);
}
$r = $p->get('/portal');
check('dashboard stat cards are white AdminLTE info-boxes', substr_count($r['body'], 'class="info-box"') === 4 && !str_contains($r['body'], 'small-box'));
check('sidebar is the light variant, with no user panel, plan text or help section', str_contains($r['body'], 'sidebar-light-primary') && !str_contains($r['body'], 'sidebar-dark') && !str_contains($r['body'], 'user-panel') && !str_contains($r['body'], 'nav-header') && !str_contains($r['body'], 'Help &amp; contact'));
$side = substr($r['body'], strpos($r['body'], '<aside'), strpos($r['body'], '</aside>') - strpos($r['body'], '<aside'));
check('sidebar lists the five product links and nothing else', substr_count($side, 'class="nav-link') === 5 && str_contains($side, '/portal/search') && str_contains($side, '/portal/new') && str_contains($side, '/portal/states') && str_contains($side, '/portal/account') && !str_contains($side, '/pricing') && !str_contains($side, '/faq'));
check('top bar has only the toggle and sign out', str_contains($r['body'], 'data-widget="pushmenu"') && str_contains($r['body'], 'Sign out') && !str_contains($r['body'], '>Website<'));
check('portal loads no custom portal stylesheet', !str_contains($r['body'], 'portal.css'));
check('dashboard shows newest filings and the state grid', str_contains($r['body'], 'Newest filings') && str_contains($r['body'], 'href="/portal/new/fl"'));
$r = $p->get('/portal/search?q=7045435');
check('filing number search finds Redline Creative LLC', str_contains($r['body'], 'Redline Creative LLC') && str_contains($r['body'], '1 company'));
check('search result links to the portal company page', str_contains($r['body'], 'href="/portal/company/ny/redline-creative-llc-7045435"'));
check('search shows 6,000 companies without filters', str_contains($p->get('/portal/search')['body'], '6,000 companies'));
check('stopword-only and short queries do not error', $p->get('/portal/search?q=the')['status'] === 200 && $p->get('/portal/search?q=ab')['status'] === 200);
check('bad date filters are ignored, not an error', $p->get('/portal/search?from=nope&to=2026-13-45')['status'] === 200);
check('new filings by state renders', str_contains($p->get('/portal/new/fl')['body'], 'New Florida businesses'));
check('/portal/new/florida redirects to /portal/new/fl (308)', ($r2 = $p->get('/portal/new/florida'))['status'] === 308 && str_ends_with((string) $r2['location'], '/portal/new/fl'), $r2['status'] . ' ' . $r2['location']);
check('unknown state is 404', $p->get('/portal/new/zz')['status'] === 404);
check('coverage table lists Wyoming', str_contains($p->get('/portal/states')['body'], 'Wyoming'));
$r = $p->get('/portal/company/ny/redline-creative-llc-7045435');
check('company page renders for a logged-in user', $r['status'] === 200 && str_contains($r['body'], 'Company details'));
check('free plan: registered agent is locked', !str_contains($r['body'], 'Statewide Filing Agents LLC') && str_contains($r['body'], 'Registered agent details are a Pro feature'));
$q = $pdo->prepare("SELECT p.name FROM people p JOIN entities e ON e.id = p.entity_id WHERE e.url_key = '7045435' AND p.role <> 'agent' ORDER BY p.id LIMIT 1");
$q->execute();
$officer = (string) $q->fetchColumn();
check('free plan: officers are locked', $officer !== '' && !str_contains($r['body'], $officer) && str_contains($r['body'], 'Upgrade to Pro'));
$r = $p->get('/portal/company/new-york/old-name-7045435');
check('old slug / full state name redirects (308)', $r['status'] === 308 && str_ends_with((string) $r['location'], '/portal/company/ny/redline-creative-llc-7045435'), $r['status'] . ' ' . $r['location']);
check('unknown company is 404', $p->get('/portal/company/ny/nothing-0000000')['status'] === 404);
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'user@example.com'");
$r = $p->get('/portal/company/ny/redline-creative-llc-7045435');
check('pro plan: officers, agent and addresses are visible', str_contains($r['body'], $officer) && str_contains($r['body'], 'Statewide Filing Agents LLC') && !str_contains($r['body'], 'Upgrade to Pro'));
$pdo->exec("UPDATE `user` SET plan = 'free' WHERE email = 'user@example.com'");

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
check('admin dashboard shows admin counters', str_contains($r['body'], 'opt-out requests pending') && str_contains($r['body'], 'new contact messages') && (bool) preg_match('/\d+ on waitlist/', $r['body']));

run('php yii seed/user pro@example.com Pro-pass-1 pro', $env, $root);
$pro = new Http('http://127.0.0.1:18080');
$r = $pro->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'pro@example.com', 'password' => 'Pro-pass-1']]);
check('seed/user: a pro test user can sign in', $r['status'] === 302, (string) $r['status']);
check('seed/user: stored as a customer on the pro plan', $pdo->query("SELECT CONCAT(role, '/', plan) FROM user WHERE email = 'pro@example.com'")->fetchColumn() === 'customer/pro');

proc_terminate($srv);

echo "Configuration errors\n";
/** Environment for a subprocess: the test settings minus $drop, plus $add. */
$envWithout = function (array $drop = [], array $add = []) use ($env) {
    $e = array_diff_key($env, array_flip($drop)) + $add;
    return array_intersect_key($e, array_flip(['PATH', 'HOME'])) + array_filter($e, fn($k) => !in_array($k, ['PATH', 'HOME'], true), ARRAY_FILTER_USE_KEY);
};
$capture = function (string $cmd, array $e) use ($root): array {
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $e);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return [proc_close($p), $out];
};
[$code] = $capture('php yii seed/user x@example.com Pass-123456 gold', $env);
check('seed/user: rejects an unknown plan', $code !== 0);
$base = ['PATH' => getenv('PATH') ?: '/usr/bin:/bin', 'ENV_FILE' => '/nonexistent/.env'] + $env; // never read the developer's real .env here
$required = ['DB_DSN', 'DB_USER', 'COOKIE_VALIDATION_KEY', 'SITE_URL'];
[$code, $out] = $capture('php yii env/check', array_diff_key($base, array_flip($required)) + ['DB_PASS' => 'SECRET-PASS-123']);
check('env/check fails and names every missing required setting', $code === 1 && str_contains($out, 'DB_DSN') && str_contains($out, 'COOKIE_VALIDATION_KEY') && str_contains($out, 'MISSING'), "exit $code");
check('env/check never prints secret values', !str_contains($out, 'SECRET-PASS-123') && !str_contains($out, $env['COOKIE_VALIDATION_KEY']));
[$code, $out] = $capture('php yii env/check', $base);
check('env/check passes with every setting present and the DB reachable', $code === 0 && str_contains($out, 'All good') && str_contains($out, 'Database: connected'), "exit $code");
[$code, $out] = $capture('php yii migrate/up --interactive=0', array_diff_key($base, ['DB_DSN' => 1]));
check('console commands refuse to run without DB_DSN, with a clear message', $code === 1 && str_contains($out, 'DB_DSN'), "exit $code");

@unlink("$root/runtime/logs/config-error.log");
$srvA = startServer(18082, ['YII_DEBUG' => 'true'] + array_diff_key($base, ['COOKIE_VALIDATION_KEY' => 1]), $root);
$a = new Http('http://127.0.0.1:18082');
$r = $a->get('/');
check('missing COOKIE_VALIDATION_KEY (debug): 500 with a message naming it', $r['status'] === 500 && str_contains($r['body'], 'Configuration error') && str_contains($r['body'], 'COOKIE_VALIDATION_KEY'), $r['status'] . '');
check('config failure is written to runtime/logs/config-error.log', str_contains((string) @file_get_contents("$root/runtime/logs/config-error.log"), 'COOKIE_VALIDATION_KEY'));
proc_terminate($srvA);
$srvB = startServer(18083, array_diff_key($base, ['DB_DSN' => 1, 'YII_DEBUG' => 1]), $root);
$b = new Http('http://127.0.0.1:18083');
$r = $b->get('/');
check('missing DB_DSN (prod): 500 with a generic pointer, no setting names or values', $r['status'] === 500 && str_contains($r['body'], 'env/check') && !str_contains($r['body'], 'DB_DSN') && !str_contains($r['body'], $env['DB_USER'] . '@'), $r['status'] . '');
proc_terminate($srvB);
$srvC = startServer(18084, array_diff_key($base, ['SITE_NOINDEX' => 1, 'SITE_MODE' => 1]), $root);
$c = new Http('http://127.0.0.1:18084');
$r = $c->get('/features');
check('SITE_NOINDEX unset still means noindex (fail-safe default)', $r['status'] === 200 && $hasHeader($r) && $hasMeta($r['body']));
proc_terminate($srvC);
$srvD = startServer(18085, ['SITE_NOINDEX' => 'false'] + $base, $root);
$d = new Http('http://127.0.0.1:18085');
$r = $d->get('/features');
check('SITE_NOINDEX=false makes the site indexable', $r['status'] === 200 && !$hasHeader($r) && !str_contains($r['body'], 'content="noindex'));
proc_terminate($srvD);
@unlink("$root/runtime/logs/config-error.log");

echo "Coming-soon gate\n";
$srv2 = startServer(18081, array_merge($env, ['SITE_MODE' => 'coming_soon']), $root);
$g = new Http('http://127.0.0.1:18081');
foreach (['/', '/features', '/pricing', '/portal/auth/login'] as $path) {
    check("gate: $path shows the waitlist page", str_contains($g->get($path)['body'], 'Join waitlist'));
}
check('gate: coming-soon page is noindex', $hasMeta($g->get('/')['body']));
check('gate: opt-out page stays reachable', str_contains($g->get('/opt-out')['body'], 'Remove my information'));
check('gate: legal pages stay reachable', str_contains($g->get('/legal/terms')['body'], 'Terms of Service'));
$r = $g->ajax('/api/waitlist', ['email' => 'gate@example.com'], '/coming-soon');
check('gate: waitlist API stays reachable', $r['status'] === 200);
proc_terminate($srv2);

echo "EIN skip-tracing sync\n";
require_once "$root/services/EinSkipTracing/InvalidSourceRowException.php";
require_once "$root/services/EinSkipTracing/ManualCompanyMapper.php";
require_once "$root/services/MirrorStats.php";
$fixture = array_column(json_decode(file_get_contents("$root/tests/fixtures/ein_manual_companies.json"), true), null, 'id');
$srcRow = fn(int $id) => ['data' => json_encode($fixture[$id]['data'])] + $fixture[$id];
$mapper = new app\services\EinSkipTracing\ManualCompanyMapper();

$m = $mapper->map($srcRow(101));
check('mapper: 2024 format, m/d/y date', $m['company']['date_filed'] === '2024-08-27' && $m['company']['is_active'] === true && count($m['people']) === 1);
check('mapper: whitespace collapsed, full_name built from fname/lname', $m['people'][0]['address'] === '100 MAIN ST STE B' && $m['people'][0]['full_name'] === 'Javier Luis Sample' && $m['people'][0]['title'] === 'Owner');
$m = $mapper->map($srcRow(102));
check('mapper: d-M-Y and m-d-Y dates', $m['company']['date_filed'] === '2024-09-05' && $m['company']['effective_date'] === '2024-09-04');
check('mapper: leading zeros kept', $m['company']['document_number'] === '001800001' && $m['people'][0]['zip_code'] === '01201');
check('mapper: officers follow the agent, in order', array_column($m['people'], 'role') === ['registered_agent', 'officer', 'officer'] && array_column($m['people'], 'position') === [0, 0, 1]);
check('mapper: officer state uppercased, ra flag kept', $m['people'][1]['state'] === 'MA' && $m['people'][2]['is_ra'] === true && $m['people'][1]['is_ra'] === false);
$m = $mapper->map($srcRow(103));
check('mapper: junk date is NULL, raw text kept', $m['company']['date_filed'] === null && $m['company']['date_filed_raw'] === 'Test date' && $m['company']['effective_date_raw'] === null && $m['company']['is_active'] === false);
$m = $mapper->map($srcRow(201));
check('mapper: current format agent fields', $m['people'][0]['address_source'] === 'Smarty' && $m['people'][0]['mailing_full_address'] !== null && $m['people'][0]['phone'] === null && $m['people'][0]['is_agent_company'] === false);
check('mapper: long values truncated to the column', mb_strlen($m['company']['company_url']) === 2048 && $m['company']['company_url'] !== null);
check('mapper: snapshot columns copied', $m['company']['qualified'] === 1 && $m['company']['processed'] === 0 && $m['company']['source_user_id'] === 4 && $m['company']['source_date_added'] === '2026-10-14 11:05:22');
$m = $mapper->map($srcRow(202));
check('mapper: ZIP+4 normalized', $m['people'][0]['zip_code'] === '60612-4028' && $m['people'][0]['full_name'] === 'TRACY SAMPLE JR' && $m['company']['date_filed'] === '2026-10-13');
$m = $mapper->map($srcRow(203));
check('mapper: agent company keeps only the full address', $m['people'][0]['is_agent_company'] === true && $m['people'][0]['address'] === null && $m['people'][0]['full_address'] !== null);
$m = $mapper->map($srcRow(204));
check('mapper: officers without address, empty title is NULL', count($m['people']) === 3 && $m['people'][1]['full_name'] === 'ALBANO SAMPLE' && $m['people'][2]['title'] === null && $m['people'][2]['address'] === null);
$m = $mapper->map($srcRow(205));
check('mapper: more_info flattened', $m['company']['no_officer_reason'] === 'NO_OFFICER_AVAILABLE' && $m['company']['company_data_found'] === true && $m['people'][0]['no_info_found'] === true);
$m = $mapper->map($srcRow(206));
check('mapper: unknown keys are reported, not dropped silently', in_array('unknown key $.company.new_flag', $m['warnings'], true) && in_array('unknown key $.registered_agent.email', $m['warnings'], true));
check('mapper: known-shape rows produce no warnings', $mapper->map($srcRow(204))['warnings'] === [] && $mapper->map($srcRow(102))['warnings'] === []);
try {
    $mapper->map($srcRow(209));
    check('mapper: data that is not an object is rejected', false);
} catch (app\services\EinSkipTracing\InvalidSourceRowException $e) {
    check('mapper: data that is not an object is rejected', str_contains($e->getMessage(), 'id=209'));
}

$einEnv = ['ENV_FILE' => '/nonexistent/.env'] + $env;
$pdo->exec('DROP TABLE IF EXISTS manual_companies');
$pdo->exec('CREATE TABLE manual_companies (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, data LONGTEXT NOT NULL,
    ignored_validation_count INT DEFAULT 0, qualified INT DEFAULT NULL, processed INT DEFAULT 0, date_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$insSrc = $pdo->prepare('INSERT INTO manual_companies (id, user_id, data, ignored_validation_count, qualified, processed, date_added) VALUES (?, ?, ?, ?, ?, ?, ?)');
$addSrc = function (array $ids) use ($insSrc, $srcRow) {
    foreach ($ids as $id) {
        $r = $srcRow($id);
        $insSrc->execute([$r['id'], $r['user_id'], $r['data'], $r['ignored_validation_count'], $r['qualified'], $r['processed'], $r['date_added']]);
    }
};
$einCount = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
$addSrc([101, 102]);
[$code, $out] = $capture('php yii ein-sync/companies', $einEnv);
check('ein-sync: first run only records the starting point', $code === 0 && str_contains($out, 'starting after manual_companies.id 102') && $einCount('SELECT COUNT(*) FROM ein_companies') === 0, "exit $code: $out");
$addSrc([103, 201, 202, 203, 204, 205, 206, 207, 208, 209, 210]);
[$code, $out] = $capture('php yii ein-sync/companies --limit=50', $einEnv);
check('ein-sync: at most 10 rows per run, bad row skipped and reported', $code === 65 && str_contains($out, 'fetched 10: 9 inserted, 0 updated, 0 unchanged, 1 failed') && str_contains($out, 'Cursor at id 209'), "exit $code: $out");
check('ein-sync: unknown keys are printed as warnings', str_contains($out, 'manual_companies.id=206: unknown key $.company.new_flag'));
check('ein-sync: the failure is kept in ein_sync_state.last_error', str_contains((string) $pdo->query("SELECT last_error FROM ein_sync_state")->fetchColumn(), 'id=209'));
[$code, $out] = $capture('php yii ein-sync/companies', $einEnv);
check('ein-sync: next run continues after the cursor', $code === 0 && str_contains($out, 'fetched 1: 1 inserted') && str_contains($out, 'Cursor at id 210'), "exit $code: $out");
[$code, $out] = $capture('php yii ein-sync/companies', $einEnv);
check('ein-sync: nothing new, nothing done', $code === 0 && str_contains($out, 'fetched 0:'), "exit $code: $out");
check('ein-sync: rows before the starting point are not copied', $einCount('SELECT COUNT(*) FROM ein_companies') === 10 && $einCount('SELECT COUNT(*) FROM ein_companies WHERE source_id IN (101, 102)') === 0);
check('ein-sync: agent and officers stored', $einCount("SELECT COUNT(*) FROM ein_company_people p JOIN ein_companies c ON c.id = p.company_id WHERE c.source_id = 204") === 3
    && $einCount("SELECT COUNT(*) FROM ein_companies WHERE source_id = 202 AND date_filed = '2026-10-13' AND state = 'IL'") === 1);
$pdo->exec("UPDATE ein_sync_state SET last_source_id = 102");
$pdo->exec("UPDATE manual_companies SET processed = 1 WHERE id = 201");
[$code, $out] = $capture('php yii ein-sync/companies', $einEnv);
check('ein-sync: re-sync rewrites only changed rows', str_contains($out, 'fetched 10: 0 inserted, 1 updated, 8 unchanged, 1 failed'), "exit $code: $out");
check('ein-sync: re-sync does not duplicate people', $einCount('SELECT COUNT(*) FROM ein_companies') === 10
    && $einCount("SELECT COUNT(*) FROM ein_company_people p JOIN ein_companies c ON c.id = p.company_id WHERE c.source_id = 201") === 1
    && $einCount("SELECT processed FROM ein_companies WHERE source_id = 201") === 1);

@mkdir("$root/runtime/mutex", 0775, true);
$lock = fopen("$root/runtime/mutex/" . md5('ein-sync-companies') . '.lock', 'c');
flock($lock, LOCK_EX);
[$code, $out] = $capture('php yii ein-sync/companies', $einEnv);
check('ein-sync: an overlapping run exits right away', $code === 0 && str_contains($out, 'still going'), "exit $code: $out");
flock($lock, LOCK_UN);
fclose($lock);
[$code, $out] = $capture('php yii ein-sync/companies', array_diff_key($einEnv, ['DB_DSN_EIN_SKIP_TRACING' => 1]));
check('ein-sync: refuses to run without its settings, naming them', $code === 78 && str_contains($out, 'DB_DSN_EIN_SKIP_TRACING'), "exit $code");
$probe = 'require "vendor/autoload.php"; require "config/env.php"; require "vendor/yiisoft/yii2/Yii.php"; new yii\console\Application(require "config/console.php");'
    . ' try { Yii::$app->dbEinSkipTracing->createCommand("UPDATE manual_companies SET processed = 2")->execute(); echo "WROTE"; } catch (Throwable $e) { echo $e->getMessage(); }';
[$code, $out] = $capture('php -r ' . escapeshellarg($probe), $einEnv);
check('ein-sync: the source connection is read-only', str_contains($out, 'READ ONLY') && $einCount('SELECT COUNT(*) FROM manual_companies WHERE processed = 2') === 0, $out);
[$code, $out] = $capture('php yii env/check', ['DB_PASS_EIN_SKIP_TRACING' => 'EIN-SECRET-999'] + $einEnv);
check('env/check lists the skip-tracing settings without their values', str_contains($out, 'DB_PASS_EIN_SKIP_TRACING') && !str_contains($out, 'EIN-SECRET-999'));
echo "Home page numbers (stats/refresh)\n";
$srvS = startServer(18086, $env, $root);
$h = new Http('http://127.0.0.1:18086');
$check0 = $h->get('/')['body'];
check('home renders before the first stats run', str_contains($check0, 'once the first data refresh has run'));
$testDb = preg_match('/dbname=([^;]+)/', $dsn, $mm) ? $mm[1] : '';
$statsEnv = ['MIRROR_DB' => $testDb, 'MIRROR_TZ' => 'America/New_York', 'REPORT_TZ' => 'America/New_York'] + $einEnv;
// Make the fixture rows recent: 201..210 within the last day, 101..103 older than 30 days.
$pdo->exec("UPDATE manual_companies SET date_added = CONVERT_TZ(UTC_TIMESTAMP() - INTERVAL (210 - id) * 30 MINUTE, '+00:00', 'America/New_York') WHERE id >= 201");
$pdo->exec("UPDATE manual_companies SET date_added = '2024-01-01 00:00:00' WHERE id < 201");
[$code, $out] = $capture('php yii stats/refresh', $statsEnv);
check('stats/refresh runs and reports the numbers', $code === 0 && str_contains($out, 'total 13') && str_contains($out, 'last 24h 9'), "exit $code: $out");
$st = json_decode((string) $pdo->query("SELECT data FROM site_stats WHERE name = 'landing'")->fetchColumn(), true);
check('stats: 14 daily buckets, today last', count($st['daily'] ?? []) === 14 && array_sum(array_column($st['daily'], 'count')) === 9, json_encode($st['daily'] ?? null));
check('stats: feed keeps only type, state and time (no names)', ($st['feed'][0] ?? null) !== null && array_keys($st['feed'][0]) === ['type', 'state', 'at']);
check('stats: entity types counted for the last 30 days', array_sum($st['types_30d'] ?? []) === 9);
check('stats: invalid states are not counted', $st['states_30d'] >= 1 && !in_array('XX', array_column($st['top_states_7d'], 'code'), true));
$pdo->exec("INSERT INTO manual_companies (id, user_id, data, date_added) SELECT 211, user_id, data, date_added FROM manual_companies WHERE id = 210");
[$code, $out] = $capture('php yii stats/refresh', $statsEnv);
check('stats: total adds rows newer than the daily base count', str_contains($out, 'total 14'), $out);
$home = $h->get('/')['body'];
check('home shows the refreshed numbers', str_contains($home, '>14<') && str_contains($home, 'in 30 days') && str_contains($home, 'companies in the index · updated'));
check('home feed shows no company names', !str_contains($home, 'ROW 210 LLC') && !str_contains($home, 'SAVITUR') && str_contains($home, 'New LLC'));
check('stats: entity kinds', app\services\MirrorStats::entityKind('Foreign Limited Liab') === 'Foreign LLC' && app\services\MirrorStats::entityKind('S-Corp') === 'Corporation'
    && app\services\MirrorStats::entityKind('Limited Partnership') === 'Partnership' && app\services\MirrorStats::entityKind('') === 'Business');
proc_terminate($srvS);
echo "Portal companies from the mirror (mirror/backfill, mirror/apply)\n";
$pdo->exec("INSERT INTO manual_companies (id, user_id, data, date_added) VALUES (300, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'FL', 'name' => 'PHONE SAMPLE LLC',
    'document_number' => 'L26000300', 'type' => 'LLC', 'date_filed' => '10/20/2026', 'status' => '1'], 'registered_agent' => ['fname' => 'Ana', 'lname' => 'Sample',
    'full_address' => '1 Main St, Miami, FL 33101', 'phone' => '305-555-0142'], 'officers' => [['fname' => 'Bo', 'lname' => 'Owner', 'o_title' => 'MGR', 'phone' => '(786) 555-0199']], 'more_info' => []])) . ", NOW()),
    (301, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'FL', 'name' => 'PHONE SAMPLE LLC RENAMED', 'document_number' => 'L26000300', 'type' => 'LLC', 'status' => '1'], 'registered_agent' => [], 'officers' => [], 'more_info' => []])) . ", NOW())");
$mirrorEnv = ['MIRROR_DB' => $testDb, 'MIRROR_TZ' => 'UTC'] + $einEnv;
[$code, $out] = $capture('php yii mirror/backfill --batch=4', $mirrorEnv);
check('mirror/backfill converts every source row', $code === 0 && str_contains($out, 'backfill done: 16 rows'), "exit $code: $out");
$conv = fn(string $sql) => $pdo->query($sql)->fetchColumn();
check('mirror: repeat submissions are one company, newest row wins', $conv("SELECT COUNT(*) FROM entities WHERE state = 'FL' AND url_key = 'l26000300'") == 1
    && $conv("SELECT name FROM entities WHERE url_key = 'l26000300'") === 'PHONE SAMPLE LLC RENAMED');
check('mirror: malformed rows are kept with missing values empty', $conv("SELECT COUNT(*) FROM entities WHERE source_id = 209 AND state = 'NA' AND url_key = 'r209' AND name = ''") == 1);
check('mirror: rerunning backfill does nothing new', str_contains($capture('php yii mirror/backfill', $mirrorEnv)[1], 'backfill done: 0 rows'));
$pdo->exec("DELETE FROM manual_companies WHERE id = 301");
$pdo->exec("INSERT INTO mirror_changes (source_id) VALUES (301), (209)");
$pdo->exec("DELETE FROM manual_companies WHERE id = 209");
[$code, $out] = $capture('php yii mirror/apply', $mirrorEnv);
check('mirror/apply replays a delete: the company falls back to the older submission', $code === 0 && str_contains($out, 'applied 2 changes')
    && $conv("SELECT name FROM entities WHERE url_key = 'l26000300'") === 'PHONE SAMPLE LLC'
    && $conv("SELECT COUNT(*) FROM people p JOIN entities e ON e.id = p.entity_id WHERE e.url_key = 'l26000300'") == 2, $out);
check('mirror/apply: a company with no rows left is removed', $conv("SELECT COUNT(*) FROM entities WHERE url_key = 'r209'") == 0 && $conv('SELECT COUNT(*) FROM mirror_changes') == 0);
$srvM = startServer(18087, $env, $root);
$m = new Http('http://127.0.0.1:18087');
$m->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'pro@example.com', 'password' => 'Pro-pass-1']]);
$page = $m->get('/portal/company/fl/phone-sample-llc-l26000300')['body'];
check('pro: phones are masked with a reveal button', str_contains($page, '(305) •••-••42') && str_contains($page, 'data-phone="(305) 555-0142"') && str_contains($page, 'Ana Sample'));
check('pro: missing values show N/A', str_contains($page, 'N/A'));
$pdo->exec("UPDATE `user` SET plan = 'free' WHERE email = 'pro@example.com'");
$page = $m->get('/portal/company/fl/phone-sample-llc-l26000300')['body'];
check('free: no phone numbers or agent in the page at all', !str_contains($page, '555-0142') && !str_contains($page, '••42') && !str_contains($page, 'Ana Sample') && str_contains($page, 'Upgrade to Pro'));
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'pro@example.com'");
proc_terminate($srvM);
$pdo->exec('DROP TABLE manual_companies');

echo "\n" . ($checks - $failures) . "/$checks checks passed" . ($failures ? ", $failures FAILED" : '') . ".\n";
exit($failures ? 1 : 0);
