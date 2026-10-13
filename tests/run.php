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
foreach (['/' => 'Every new US business', '/features' => 'Features', '/pricing' => 'Priced for teams', '/faq' => 'Frequently asked questions',
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
check('company page renders for a logged-in user', $r['status'] === 200 && str_contains($r['body'], 'Company details') && str_contains($r['body'], 'Statewide Filing Agents LLC'));
$q = $pdo->prepare("SELECT p.name FROM people p JOIN entities e ON e.id = p.entity_id WHERE e.url_key = '7045435' AND p.role <> 'agent' ORDER BY p.id LIMIT 1");
$q->execute();
$officer = (string) $q->fetchColumn();
check('free plan: officers are locked', $officer !== '' && !str_contains($r['body'], $officer) && str_contains($r['body'], 'Unlock with Pro'));
$r = $p->get('/portal/company/new-york/old-name-7045435');
check('old slug / full state name redirects (308)', $r['status'] === 308 && str_ends_with((string) $r['location'], '/portal/company/ny/redline-creative-llc-7045435'), $r['status'] . ' ' . $r['location']);
check('unknown company is 404', $p->get('/portal/company/ny/nothing-0000000')['status'] === 404);
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'user@example.com'");
$r = $p->get('/portal/company/ny/redline-creative-llc-7045435');
check('pro plan: officers and addresses are visible', str_contains($r['body'], $officer) && !str_contains($r['body'], 'Unlock with Pro'));
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

echo "\n" . ($checks - $failures) . "/$checks checks passed" . ($failures ? ", $failures FAILED" : '') . ".\n";
exit($failures ? 1 : 0);
