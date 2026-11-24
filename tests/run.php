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

function mailsWith(string $needle, int $since): array
{
    $out = [];
    foreach (glob(dirname(__DIR__) . '/runtime/mail/*.eml') ?: [] as $f) {
        $body = quoted_printable_decode((string) file_get_contents($f));
        if (filemtime($f) >= $since && str_contains($body, $needle)) {
            $out[] = $body;
        }
    }
    return $out;
}

echo "Preparing test database...\n";
run('php yii migrate/fresh --interactive=0', $env, $root);
run('php yii seed/all', $env, $root);

$srv = startServer(18080, $env, $root);
$h = new Http('http://127.0.0.1:18080');
echo "Public pages\n";
foreach (['/' => 'The index of every new US business', '/features' => 'Features', '/pricing' => 'Self-serve signup', '/faq' => 'Frequently asked questions',
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
foreach (['/portal', '/portal/companies', '/portal/companies?q=roofing', '/portal/company/ny/redline-creative-llc-7045435', '/portal/states', '/portal/account'] as $path) {
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
check('dashboard shows for the new user', $r['status'] === 200 && str_contains($r['body'], 'Total companies') && !str_contains($r['body'], 'Your plan'));
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'user@example.com'");
echo "Portal product pages\n";
foreach (['/portal', '/portal/companies', '/portal/companies?state=FL', '/portal/states', '/portal/account'] as $path) {
    $r = $p->get($path);
    check("portal $path renders AdminLTE", $r['status'] === 200 && str_contains($r['body'], 'main-sidebar') && $hasMeta($r['body']) && $hasHeader($r), (string) $r['status']);
}
$r = $p->get('/portal');
check('dashboard stat cards are AdminLTE info-boxes', preg_match_all('/class="info-box[ "]/', $r['body']) === 4 && !str_contains($r['body'], 'small-box'));
check('portal is dark: dark-mode body and dark sidebar, with no user panel, plan text or help section', str_contains($r['body'], 'dark-mode') && str_contains($r['body'], 'sidebar-dark-primary') && !str_contains($r['body'], 'sidebar-light') && !str_contains($r['body'], 'user-panel') && !str_contains($r['body'], 'nav-header') && !str_contains($r['body'], 'Help &amp; contact'));
$side = substr($r['body'], strpos($r['body'], '<aside'), strpos($r['body'], '</aside>') - strpos($r['body'], '<aside'));
check('sidebar lists the five product links and nothing else', substr_count($side, 'class="nav-link') === 5 && str_contains($side, '/portal/api-keys') && str_contains($side, '/portal/api-docs') && str_contains($side, '>Companies<') && str_contains($side, '/portal/companies') && !str_contains($side, '/portal/new') && !str_contains($side, '/portal/search') && str_contains($side, '/portal/states') && !str_contains($side, '/portal/account') && !str_contains($side, '/pricing') && !str_contains($side, '/faq'));
$top = substr($r['body'], strpos($r['body'], '<nav'), strpos($r['body'], '</nav>') - strpos($r['body'], '<nav'));
check('top bar has the menu toggle and the user avatar (initial of the name), and nothing else', str_contains($top, 'data-widget="pushmenu"') && str_contains($top, 'class="btn ei-avatar"') && str_contains($top, '>T</button>')
    && !str_contains($top, 'data-ei-theme-toggle') && !str_contains($top, '>Website<'));
$menu = substr($top, strpos($top, 'dropdown-menu'));
$at = fn(string $needle) => (int) strpos($menu, $needle);
check('user menu: name and email, then Account settings, Theme (light / dark / system), Sign out', str_contains($menu, 'Test User') && str_contains($menu, 'user@example.com')
    && $at('user@example.com') < $at('Account settings') && $at('Account settings') < $at('data-ei-theme-set="light"') && $at('data-ei-theme-set="system"') < $at('Sign out')
    && str_contains($menu, 'href="/portal/account"') && str_contains($menu, 'data-ei-theme-set="dark"') && str_contains($menu, '/portal/auth/logout'));
check('user menu marks dark as the current theme by default', str_contains($menu, 'class="active" data-ei-theme-set="dark"') && str_contains($r['body'], 'data-ei-theme="dark"'));
check('portal loads the dark theme stylesheet', str_contains($r['body'], 'css/portal.css'));
check('portal is dark by default and loads the theme script in the head', !str_contains($r['body'], 'ei-light') && strpos($r['body'], 'js/portal-theme.js') < strpos($r['body'], '<body'));
// The toggle's cookie goes into the jar: a second Cookie header next to the session's would be ignored by PHP.
file_put_contents($p->jar, "127.0.0.1\tFALSE\t/\tFALSE\t0\tei_theme\tlight\n", FILE_APPEND);
$lr = $p->get('/portal');
file_put_contents($p->jar, preg_replace('/^.*\tei_theme\t.*\n/m', '', file_get_contents($p->jar)));
check('ei_theme=light renders the light theme', $lr['status'] === 200 && str_contains($lr['body'], 'ei-light') && str_contains($lr['body'], 'sidebar-light-primary') && !str_contains($lr['body'], 'dark-mode') && str_contains($lr['body'], 'class="active" data-ei-theme-set="light"'), (string) $lr['status']);
file_put_contents($p->jar, "127.0.0.1\tFALSE\t/\tFALSE\t0\tei_theme\tsystem\n", FILE_APPEND);
$lr = $p->get('/portal');
file_put_contents($p->jar, preg_replace('/^.*\tei_theme\t.*\n/m', '', file_get_contents($p->jar)));
check('ei_theme=system renders dark and syncs to the device before paint', str_contains($lr['body'], 'data-ei-theme="system"') && str_contains($lr['body'], 'dark-mode')
    && str_contains($lr['body'], 'class="active" data-ei-theme-set="system"') && strpos($lr['body'], 'eiTheme.sync()') < strpos($lr['body'], 'content-wrapper'));
$lr = (new Http('http://127.0.0.1:18080'))->req('GET', '/portal/auth/login', [], ['Cookie: ei_theme=light']);
check('sign-in page follows the theme cookie', str_contains($lr['body'], 'ei-light') && str_contains($lr['body'], 'data-ei-theme-toggle'));
check('unknown theme cookie falls back to dark', str_contains((new Http('http://127.0.0.1:18080'))->req('GET', '/portal/auth/login', [], ['Cookie: ei_theme=<x>'])['body'], 'dark-mode'));
check('dashboard shows the 7-day overview, the plan limits and the top 10 states', str_contains($r['body'], 'Structured addresses') && str_contains($r['body'], 'Your limits') && !str_contains($r['body'], 'Coming soon')
    && str_contains($r['body'], 'Top 10 states') && substr_count($r['body'], '<tr>') <= 11 && !str_contains($r['body'], 'Newest filings') && !str_contains($r['body'], 'ei-state-grid'));
$r = $p->get('/portal/companies?q=7045435');
check('filing number search finds Redline Creative LLC', str_contains($r['body'], 'Redline Creative LLC') && str_contains($r['body'], '1 company'));
check('search result links to the portal company page', str_contains($r['body'], 'href="/portal/company/ny/redline-creative-llc-7045435"'));
$r = $p->get('/portal/companies');
$rows = preg_match_all('/href="\/portal\/company\/[^"]+"/', $r['body'], $m) ? array_values(array_unique($m[0])) : [];
check('companies shows all 6,000 without filters, 50 per page', str_contains($r['body'], '6,000 companies') && count($rows) === 50 && str_contains($r['body'], '1–50 of 6,000'), (string) count($rows));
check('companies has the date range picker in the filter row', str_contains($r['body'], 'data-ei-daterange') && str_contains($r['body'], 'flatpickr') && str_contains($r['body'], 'name="from"') && str_contains($r['body'], 'name="to"'));
check('companies page 2 continues the list', str_contains($p->get('/portal/companies?page=2')['body'], '51–100 of 6,000'));
check('stopword-only and short queries do not error', $p->get('/portal/companies?q=the')['status'] === 200 && $p->get('/portal/companies?q=ab')['status'] === 200);
check('bad date filters are ignored, not an error', $p->get('/portal/companies?from=nope&to=2026-13-45')['status'] === 200);
$r = $p->get('/portal/companies?state=FL');
check('state filter narrows to that state', str_contains($r['body'], ' in Florida') && !str_contains($r['body'], '6,000 companies'));
check('new filings and search pages are gone', $p->get('/portal/new')['status'] === 404 && $p->get('/portal/new/fl')['status'] === 404 && $p->get('/portal/search')['status'] === 404);
check('state links go to companies filtered by state', str_contains($p->get('/portal/states')['body'], 'href="/portal/companies?state=FL"'));
check('coverage table lists Wyoming', str_contains($p->get('/portal/states')['body'], 'Wyoming'));
$r = $p->get('/portal/states');
$side = substr($r['body'], strpos($r['body'], '<aside'), strpos($r['body'], '</aside>') - strpos($r['body'], '<aside'));
check('states page is called States, with a Last 24h column and the filter row', str_contains($side, '>States<') && str_contains($r['body'], '<th class="text-right">Last 24h</th>')
    && !str_contains($r['body'], 'Latest day') && str_contains($r['body'], 'name="source"') && str_contains($r['body'], 'name="lag"') && str_contains($r['body'], 'name="status"') && str_contains($r['body'], 'data-ei-daterange'));
$stateRows = fn(string $qs) => preg_match_all('/<td><a class="font-weight-bold"/', $p->get('/portal/states' . $qs)['body']);
check('states: no filters lists all 51', $stateRows('') === 51);
check('states: source filter', $stateRows('?source=api') === 4 && $stateRows('?source=file') === 1 && $stateRows('?source=website') === 46);
check('states: name filter', $stateRows('?q=new') === 4 && $stateRows('?q=FL') === 1);
check('states: status filter splits the 51', $stateRows('?status=on_time') + $stateRows('?status=delayed') + $stateRows('?status=not_live') === 51);
check('states: median lag filter matches the on-time count', $stateRows('?lag=24') === $stateRows('?status=on_time'));
$r = $p->get('/portal/states?from=2020-01-01&to=2020-01-31');
check('states: a date range replaces the 7-day column', str_contains($r['body'], 'Filed Jan 1, 2020 – Jan 31, 2020') && !str_contains($r['body'], '>Last 7 days<'));
check('states: bad dates are ignored', $p->get('/portal/states?from=nope&to=2026-13-45')['status'] === 200);
$r = $p->get('/portal/company/ny/redline-creative-llc-7045435');
check('company page renders for a logged-in user', $r['status'] === 200 && str_contains($r['body'], 'Company details'));
$q = $pdo->prepare("SELECT p.name FROM people p JOIN entities e ON e.id = p.entity_id WHERE e.url_key = '7045435' AND p.role <> 'agent' ORDER BY p.id LIMIT 1");
$q->execute();
$officer = (string) $q->fetchColumn();
$r = $p->get('/portal/company/new-york/old-name-7045435');
check('old slug / full state name redirects (308)', $r['status'] === 308 && str_ends_with((string) $r['location'], '/portal/company/ny/redline-creative-llc-7045435'), $r['status'] . ' ' . $r['location']);
check('unknown company is 404', $p->get('/portal/company/ny/nothing-0000000')['status'] === 404);
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
check('admin dashboard renders without admin counters', $r['status'] === 200 && str_contains($r['body'], 'Total companies') && !str_contains($r['body'], 'opt-out requests pending'));

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
check('home shows the refreshed numbers', str_contains($home, '>14<') && str_contains($home, 'Companies in the index') && str_contains($home, 'Updated '));
check('home feed shows no company names', !str_contains($home, 'ROW 210 LLC') && !str_contains($home, 'SAVITUR') && str_contains($home, 'LLC, '));
check('stats: entity kinds', app\services\MirrorStats::entityKind('Foreign Limited Liab') === 'Foreign LLC' && app\services\MirrorStats::entityKind('S-Corp') === 'Corporation'
    && app\services\MirrorStats::entityKind('Limited Partnership') === 'Partnership' && app\services\MirrorStats::entityKind('') === 'Business');
proc_terminate($srvS);
echo "Portal companies from the mirror (mirror/backfill, mirror/apply)\n";
$pdo->exec("INSERT INTO manual_companies (id, user_id, data, qualified, date_added) VALUES (300, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'FL', 'name' => 'PHONE SAMPLE LLC',
    'document_number' => 'L26000300', 'type' => 'LLC', 'date_filed' => '10/20/2026', 'status' => '1'], 'registered_agent' => ['fname' => 'Ana', 'lname' => 'Sample',
    'full_address' => '1 Main St, Miami, FL 33101', 'phone' => '305-555-0142'], 'officers' => [['fname' => 'Bo', 'lname' => 'Owner', 'o_title' => 'MGR', 'phone' => '(786) 555-0199']], 'more_info' => []])) . ", 0, NOW()),
    (301, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'FL', 'name' => 'PHONE SAMPLE LLC RENAMED', 'document_number' => 'L26000300', 'type' => 'LLC', 'status' => '1'], 'registered_agent' => [], 'officers' => [], 'more_info' => []])) . ", 0, NOW()),
    (303, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'OH', 'name' => 'FILED AFTER ADDED LLC', 'document_number' => '5000303', 'type' => 'LLC', 'date_filed' => date('m/d/Y'), 'status' => '1'], 'registered_agent' => [], 'officers' => [], 'more_info' => []])) . ", 0, NOW() - INTERVAL 5 DAY),
    (302, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'OH', 'name' => 'FUTURE DATE LLC', 'document_number' => '5000302', 'type' => 'LLC', 'date_filed' => '01/06/2030', 'effective_date' => '1/6/30', 'status' => '1'], 'registered_agent' => [], 'officers' => [], 'more_info' => []])) . ", 0, NOW())");
$offset = (int) $pdo->query('SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW())')->fetchColumn();
$mirrorEnv = ['MIRROR_DB' => $testDb, 'MIRROR_TZ' => sprintf('%s%02d:%02d', $offset < 0 ? '-' : '+', intdiv(abs($offset), 60), abs($offset) % 60)] + $einEnv;
[$code, $out] = $capture('php yii mirror/backfill --batch=4', $mirrorEnv);
check('mirror/backfill converts every source row', $code === 0 && str_contains($out, 'backfill done: 18 rows'), "exit $code: $out");
check('mirror: a filing date in the future is unknown (N/A)', $pdo->query("SELECT COUNT(*) FROM entities WHERE url_key = '5000302' AND filed_at IS NULL AND formation_date IS NULL")->fetchColumn() == 1);
check('mirror: a filing date after the row was added is kept', $pdo->query("SELECT COUNT(*) FROM entities WHERE url_key = '5000303' AND filed_at IS NOT NULL")->fetchColumn() == 1);
$conv = fn(string $sql) => $pdo->query($sql)->fetchColumn();
check('mirror: repeat submissions are one company, newest row wins', $conv("SELECT COUNT(*) FROM entities WHERE state = 'FL' AND url_key = 'l26000300'") == 1
    && $conv("SELECT name FROM entities WHERE url_key = 'l26000300'") === 'PHONE SAMPLE LLC RENAMED');
check('mirror: malformed rows are kept with missing values empty', $conv("SELECT COUNT(*) FROM entities WHERE source_id = 209 AND state = 'NA' AND url_key = 'r209' AND name = ''") == 1);
check('mirror: address parts stored next to the one-line address', $conv("SELECT COUNT(*) FROM people WHERE zip = '60612-4028' AND street <> '' AND city <> '' AND state <> ''") >= 1);
check('mirror: rerunning backfill does nothing new', str_contains($capture('php yii mirror/backfill', $mirrorEnv)[1], 'backfill done: 0 rows'));
check('mirror/backfill --restart converts every row again', str_contains($capture('php yii mirror/backfill --restart', $mirrorEnv)[1], 'backfill done: 18 rows'));
$pdo->exec("DELETE FROM manual_companies WHERE id = 301");
$pdo->exec("INSERT INTO mirror_changes (source_id) VALUES (301), (209)");
$pdo->exec("DELETE FROM manual_companies WHERE id = 209");
[$code, $out] = $capture('php yii mirror/apply', $mirrorEnv);
check('mirror/apply replays a delete: the company falls back to the older submission', $code === 0 && str_contains($out, 'applied 2 changes')
    && $conv("SELECT name FROM entities WHERE url_key = 'l26000300'") === 'PHONE SAMPLE LLC'
    && $conv("SELECT COUNT(*) FROM people p JOIN entities e ON e.id = p.entity_id WHERE e.url_key = 'l26000300'") == 2, $out);
check('mirror/apply: a company with no rows left is removed', $conv("SELECT COUNT(*) FROM entities WHERE url_key = 'r209'") == 0 && $conv('SELECT COUNT(*) FROM mirror_changes') == 0);
$pdo->exec("INSERT INTO manual_companies (id, user_id, data, qualified, date_added) VALUES
    (310, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'OH', 'name' => 'PUB ZERO LLC', 'document_number' => 'P310', 'type' => 'LLC', 'status' => '1'], 'registered_agent' => [], 'officers' => [], 'more_info' => []])) . ", 0, NOW()),
    (311, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'OH', 'name' => 'PUB HELD LLC', 'document_number' => 'P311', 'type' => 'LLC', 'status' => '1'], 'registered_agent' => [], 'officers' => [], 'more_info' => []])) . ", 1, NOW()),
    (312, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'OH', 'name' => 'PUB UNDECIDED LLC', 'document_number' => 'P312', 'type' => 'LLC', 'status' => '1'], 'registered_agent' => [], 'officers' => [], 'more_info' => []])) . ", NULL, NOW())");
$capture('php yii mirror/backfill', $mirrorEnv);
$pubRow = fn(string $key) => $pdo->query("SELECT TIMESTAMPDIFF(HOUR, indexed_at, publish_at) FROM entities WHERE url_key = '$key'")->fetchColumn();
check('publish_at: qualified 0 is published on arrival', (string) $pubRow('p310') === '0');
check('publish_at: qualified 1 is held for the configured hours', (string) $pubRow('p311') === '48');
check('publish_at: qualified NULL is held until the source decides', $pdo->query("SELECT publish_at IS NULL FROM entities WHERE url_key = 'p312'")->fetchColumn() == 1);
$srvM = startServer(18087, $env, $root);
$m = new Http('http://127.0.0.1:18087');
$m->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'pro@example.com', 'password' => 'Pro-pass-1']]);
$page = $m->get('/portal/company/fl/phone-sample-llc-l26000300')['body'];
check('pro: phones are masked with a reveal button', str_contains($page, '(305) •••-••42') && str_contains($page, 'data-phone="(305) 555-0142"') && str_contains($page, 'Ana Sample'));
check('pro: missing values show N/A', str_contains($page, 'N/A'));
$pdo->exec("UPDATE `user` SET plan = 'free' WHERE email = 'pro@example.com'");
$page = $m->get('/portal/company/fl/phone-sample-llc-l26000300')['body'];
check('free: no phone numbers in the page at all', !str_contains($page, '555-0142') && !str_contains($page, '••42') && !str_contains($page, '555-0199') && str_contains($page, 'Upgrade to Pro'));
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'pro@example.com'");
check('publish_at: a published company opens', $m->get('/portal/company/oh/pub-zero-llc-p310')['status'] === 200);
check('publish_at: a held company is a 404 in the portal', $m->get('/portal/company/oh/pub-held-llc-p311')['status'] === 404 && $m->get('/portal/company/oh/pub-undecided-llc-p312')['status'] === 404);
$r = $m->get('/portal/companies?q=PUB+HELD');
check('publish_at: held companies are not in search results', str_contains($r['body'], '0 companies') && !str_contains($r['body'], 'PUB HELD LLC'));
check('publish_at: a published company is in search results', str_contains($m->get('/portal/companies?q=PUB+ZERO')['body'], 'PUB ZERO LLC'));
$pdo->exec("UPDATE entities SET publish_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE url_key = 'p311'");
check('publish_at: a held company appears once its time has passed', $m->get('/portal/company/oh/pub-held-llc-p311')['status'] === 200);
$pdo->exec("DELETE FROM entities WHERE url_key IN ('p310', 'p311', 'p312')");
proc_terminate($srvM);
$pdo->exec('DROP TABLE manual_companies');

$srvA = startServer(18088, $env, $root);
$a = new Http('http://127.0.0.1:18088');
$api = fn(string $path, ?string $token = null) => $a->req('GET', $path, [], $token ? ["Authorization: Bearer $token"] : []);
$a->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'pro@example.com', 'password' => 'Pro-pass-1']]);
$page = $a->get('/portal/api-keys')['body'];
check('pro: API keys page shows the upgrade notice and no form', str_contains($page, 'API access is not part of your plan') && !str_contains($page, 'Create key'));
check('pro: creating a key is forbidden', $a->postForm('/portal/api-keys/create', ['name' => 'x'], '/portal/api-keys')['status'] === 403);
$pdo->exec("UPDATE `user` SET plan = 'business' WHERE email = 'pro@example.com'");
check('business: API keys page has the create form', str_contains($a->get('/portal/api-keys')['body'], 'Create key'));
$a->postForm('/portal/api-keys/create', ['name' => 'Test key'], '/portal/api-keys');
$page = $a->get('/portal/api-keys')['body'];
preg_match('/value="(eik_[0-9a-f]{48})"/', $page, $km);
$token = $km[1] ?? '';
check('creating a key shows the token once', $token !== '');
check('the token is not stored, only its hash', $token !== '' && $pdo->query("SELECT COUNT(*) FROM api_key WHERE key_hash = " . $pdo->quote(hash('sha256', $token)) . " AND key_prefix = " . $pdo->quote(substr($token, 0, 12)))->fetchColumn() == 1
    && $pdo->query("SELECT COUNT(*) FROM api_key WHERE key_hash = " . $pdo->quote($token))->fetchColumn() == 0);
check('the token is not shown on a second page view', !str_contains($a->get('/portal/api-keys')['body'], $token));
$r = $api('/api/v1/me');
check('API without a key is 401 JSON', $r['status'] === 401 && str_contains($r['headers'], 'application/json') && stripos($r['headers'], 'WWW-Authenticate') !== false, $r['status']);
check('API with a wrong key is 401', $api('/api/v1/me', 'eik_' . str_repeat('0', 48))['status'] === 401);
$r = $api('/api/v1/me', $token);
check('API with a valid key returns the account', $r['status'] === 200 && (json_decode($r['body'], true)['plan'] ?? '') === 'business', $r['status'] . ' ' . $r['body']);
check('API does not set a session cookie', !str_contains($r['headers'], 'Set-Cookie'));
check('API unknown endpoint is 404 JSON', ($x = $api('/api/v1/nope', $token))['status'] === 404 && str_contains($x['headers'], 'application/json'));
$docs = $a->get('/portal/api-docs');
check('API docs page renders for a business user', $docs['status'] === 200 && str_contains($docs['body'], 'Reading many companies') && str_contains($docs['body'], 'next_cursor'));
$r = $api('/api/v1/entities?limit=5', $token);
$j = json_decode($r['body'], true);
check('entities: a page of 5 with cursor and full record shape', $r['status'] === 200 && count($j['data'] ?? []) === 5 && ($j['has_more'] ?? false) === true && !empty($j['next_cursor'])
    && array_key_exists('registered_agent', $j['data'][0]) && array_key_exists('people', $j['data'][0]) && isset($j['data'][0]['source']['pulled_at']), $r['body']);
$seen = array_column($j['data'], 'id');
$cursor = $j['next_cursor'];
$pages = 1;
while ($cursor && $pages < 50) {
    $jj = json_decode($api('/api/v1/entities?limit=1000&cursor=' . $cursor, $token)['body'], true);
    $seen = array_merge($seen, array_column($jj['data'], 'id'));
    $cursor = $jj['next_cursor'];
    $pages++;
}
check('entities: cursor paging walks every company once, newest id first', count($seen) === count(array_unique($seen)) && $seen[0] > $seen[1] && $seen === array_values(array_unique($seen))
    && count($seen) === (int) $pdo->query('SELECT COUNT(*) FROM entities WHERE publish_at <= UTC_TIMESTAMP()')->fetchColumn(), (string) count($seen));
check('entities: limit up to 1000 is allowed, above is 400', count(json_decode($api('/api/v1/entities?limit=1000', $token)['body'], true)['data']) === 1000 && $api('/api/v1/entities?limit=1001', $token)['status'] === 400);
check('entities: bad cursor, state, date and order are 400', $api('/api/v1/entities?cursor=%21%21', $token)['status'] === 400 && $api('/api/v1/entities?state=Florida', $token)['status'] === 400
    && $api('/api/v1/entities?filed_after=2026-13-40', $token)['status'] === 400 && $api('/api/v1/entities?order=sideways', $token)['status'] === 400);
$asc = json_decode($api('/api/v1/entities?order=asc&limit=3', $token)['body'], true)['data'];
check('entities: order=asc returns the lowest ids first', $asc[0]['id'] < $asc[1]['id'] && $asc[1]['id'] < $asc[2]['id']);
$fl = json_decode($api('/api/v1/entities?state=fl&limit=50', $token)['body'], true)['data'];
check('entities: state filter', $fl && count(array_unique(array_column($fl, 'state'))) === 1 && $fl[0]['state'] === 'FL');
$one = $fl[0];
$d = json_decode($api('/api/v1/entities/' . $one['id'], $token)['body'], true)['data'] ?? [];
check('entity by id includes filings', ($d['id'] ?? 0) === $one['id'] && array_key_exists('filings', $d));
$d2 = json_decode($api('/api/v1/entities/FL/' . $one['filing_number'], $token)['body'], true)['data'] ?? [];
check('entity by state and filing number', ($d2['filing_number'] ?? '') === $one['filing_number']);
check('entity not found is 404 JSON', $api('/api/v1/entities/999999999', $token)['status'] === 404);
check('entities: POST is 405', $a->req('POST', '/api/v1/entities', [], ["Authorization: Bearer $token"])['status'] === 405);
check('entities: no key is 401', $api('/api/v1/entities')['status'] === 401);
check('a key use is recorded', $pdo->query("SELECT COUNT(*) FROM api_key WHERE last_used_at IS NOT NULL")->fetchColumn() == 1);
$r = $api('/api/v1/me', $token);
check('API responses carry rate-limit and quota headers', stripos($r['headers'], 'X-RateLimit-Limit: 120') !== false && stripos($r['headers'], 'X-Quota-Limit: 25000') !== false && stripos($r['headers'], 'X-Quota-Remaining:') !== false);
check('API calls are counted for the month', (int) $pdo->query("SELECT calls FROM api_usage WHERE bucket = 'm:" . gmdate('Y-m') . "'")->fetchColumn() >= 3);
$uid = (int) $pdo->query("SELECT id FROM `user` WHERE email = 'pro@example.com'")->fetchColumn();
$pdo->exec("INSERT INTO api_usage (user_id, bucket, calls) VALUES ($uid, 'n:" . gmdate('Y-m-d\\TH:i') . "', 100000) ON DUPLICATE KEY UPDATE calls = 100000");
$r = $api('/api/v1/me', $token);
check('over the per-minute limit is 429 with Retry-After', $r['status'] === 429 && str_contains($r['headers'], 'Retry-After:') && str_contains($r['body'], 'Rate limit'), $r['status'] . ' ' . $r['body']);
$pdo->exec("DELETE FROM api_usage WHERE bucket LIKE 'n:%'");
$pdo->exec("UPDATE api_usage SET calls = 25000 WHERE bucket = 'm:" . gmdate('Y-m') . "'");
$r = $api('/api/v1/me', $token);
check('over the monthly quota is 429 with a reset date', $r['status'] === 429 && str_contains($r['body'], 'Monthly API quota') && str_contains($r['headers'], 'X-Quota-Remaining: 0'), $r['status'] . ' ' . $r['body']);
$keysPage = $a->get('/portal/api-keys')['body'];
check('the usage card shows on the keys page', preg_match('~<strong>25,00[01]</strong> of 25,000 calls used~', $keysPage) === 1);
$pdo->exec("DELETE FROM api_usage");
$pdo->exec("INSERT INTO api_usage (user_id, bucket, calls) VALUES ($uid, 'r:" . gmdate('Y-m') . "', 49998)");
$r = $api('/api/v1/entities?limit=100', $token);
$j = json_decode($r['body'], true);
check('a list is cut short at the remaining row quota', $r['status'] === 200 && count($j['data']) === 2 && $j['has_more'] === true && str_contains($r['headers'], 'X-Rows-Remaining: 0'), $r['status'] . ' ' . substr($r['body'], 0, 200));
$r = $api('/api/v1/entities', $token);
check('with no rows left the next call is 429 with a reset date', $r['status'] === 429 && str_contains($r['body'], 'Monthly row quota'), $r['status'] . ' ' . $r['body']);
$pdo->exec("DELETE FROM api_usage");
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'pro@example.com'");
check('API key of a downgraded plan is 403', $api('/api/v1/me', $token)['status'] === 403);
$pdo->exec("UPDATE `user` SET plan = 'business' WHERE email = 'pro@example.com'");
$keyId = $pdo->query("SELECT id FROM api_key LIMIT 1")->fetchColumn();
$a->postForm('/portal/api-keys/revoke', ['id' => $keyId], '/portal/api-keys');
check('a revoked key is rejected', $api('/api/v1/me', $token)['status'] === 401);
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'pro@example.com'");

echo "CSV export\n";
$exportCap = 5000;
$xb = "x:" . gmdate('Y-m');
$flCount = (int) $pdo->query("SELECT COUNT(*) FROM entities WHERE state = 'FL' AND publish_at <= UTC_TIMESTAMP()")->fetchColumn();
$page = $a->get('/portal/companies?state=FL')['body'];
check('pro: the Companies page has an Export CSV button, a confirmation modal and the usage card', str_contains($page, 'Export CSV') && str_contains($page, 'id="export-modal"') && str_contains($page, 'CSV export this month') && str_contains($page, 'rows will be exported'));
check('export needs POST', $a->get('/portal/export/companies')['status'] === 405);
$r = $a->postForm('/portal/export/companies', ['state' => 'FL'], '/portal/companies?state=FL');
$rows = [];
if ($fh = fopen('php://memory', 'w+')) {
    fwrite($fh, preg_replace('/^\xEF\xBB\xBF/', '', $r['body']));
    rewind($fh);
    while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
}
check('pro: export downloads a CSV of the filtered companies', $r['status'] === 200 && str_contains($r['headers'], 'text/csv') && str_contains($r['headers'], 'attachment')
    && ($rows[0][0] ?? '') === 'Company' && count($rows) - 1 === min($flCount, $exportCap) && !array_diff(array_column(array_slice($rows, 1), 1), ['FL']), $r['status'] . ' rows=' . count($rows));
check('the exported rows are counted against the month', (int) $pdo->query("SELECT calls FROM api_usage WHERE user_id = $uid AND bucket = '$xb'")->fetchColumn() === count($rows) - 1);
$pdo->exec("DELETE FROM api_usage");
$pdo->exec("INSERT INTO api_usage (user_id, bucket, calls) VALUES ($uid, '$xb', " . ($exportCap - 3) . ")");
$r = $a->postForm('/portal/export/companies', ['state' => 'FL'], '/portal/companies?state=FL');
check('a file is cut short at the remaining monthly rows', $r['status'] === 200 && substr_count(trim($r['body']), "\n") === 3, $r['status'] . ' ' . substr_count(trim($r['body']), "\n"));
$r = $a->postForm('/portal/export/companies', ['state' => 'FL'], '/portal/companies?state=FL');
check('with no rows left the export is refused', $r['status'] === 302 && !str_contains($r['headers'], 'text/csv'));
check('the Companies page shows the export usage card, full', str_contains($a->get('/portal/companies?state=FL')['body'], '<strong>5,000</strong> of 5,000 export rows used') && str_contains($a->get('/portal/companies?state=FL')['body'], 'used up until'));
$pdo->exec("DELETE FROM api_usage");
$pdo->exec("UPDATE `user` SET plan = 'free' WHERE email = 'pro@example.com'");
check('free: the button is locked', str_contains($a->get('/portal/companies')['body'], 'Export CSV is a Pro feature'));
check('free: export is forbidden', $a->postForm('/portal/export/companies', ['state' => 'FL'], '/portal/companies')['status'] === 403);
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'pro@example.com'");

echo "Free plan limits\n";
run('php yii seed/user free@example.com Free-pass-1 free', $env, $root);
$flimits = ['historyDays' => 7, 'lookupsPerDay' => 10];
$pdo->exec("UPDATE entities SET filed_at = '2026-11-01 12:00:00' WHERE id IN (SELECT id FROM (SELECT id FROM entities WHERE state <> 'NA' ORDER BY id LIMIT 15) t)");
$pdo->exec("DELETE FROM entities WHERE filed_at > '2026-11-01 23:59:59'");
$recent = $pdo->query("SELECT id, state, slug, url_key, state_filing_number FROM entities WHERE filed_at = '2026-11-01 12:00:00' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$urlOf = fn(array $e) => '/portal/company/' . strtolower($e['state']) . '/' . $e['slug'] . '-' . $e['url_key'];
$old = $pdo->query("SELECT state, slug, url_key FROM entities WHERE filed_at < '2026-10-26' AND state <> 'NA' ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$pdo->exec("UPDATE entities SET registered_agent_id = (SELECT id FROM registered_agents ORDER BY id LIMIT 1) WHERE id = " . (int) $recent[0]['id']);
$agentName = (string) $pdo->query("SELECT name FROM registered_agents ORDER BY id LIMIT 1")->fetchColumn();
run('php yii cache/flush-all', $env, $root);
$f = new Http('http://127.0.0.1:18088');
$f->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'free@example.com', 'password' => 'Free-pass-1']]);
$lookups = fn() => (int) $pdo->query("SELECT COUNT(*) FROM company_lookup cl JOIN `user` u ON u.id = cl.user_id WHERE u.email = 'free@example.com'")->fetchColumn();
$r = $f->get('/portal/companies');
$allCount = number_format((int) $pdo->query('SELECT COUNT(*) FROM entities WHERE publish_at <= UTC_TIMESTAMP()')->fetchColumn());
check('free: Companies searches every filing and shows the plan card', str_contains($r['body'], $allCount . ' companies') && str_contains($r['body'], 'Free plan') && str_contains($r['body'], '<strong>0</strong> of ' . $flimits['lookupsPerDay'] . ' company lookups used today'));
check('free: filings older than the window carry a lock icon in the list', str_contains($r['body'], 'Older than your plan covers'));
check('free: Companies has no County column and an officer-free search hint', !str_contains($r['body'], '>County<') && str_contains($r['body'], 'Name, agent or filing number'));
check('free: the date picker and filters are not limited to the window', !str_contains($r['body'], 'data-min') && str_contains($f->get('/portal/companies?from=2020-01-01&to=2026-12-31')['body'], number_format((int) $pdo->query("SELECT COUNT(*) FROM entities WHERE publish_at <= UTC_TIMESTAMP() AND filed_at >= '2020-01-01' AND filed_at < '2027-01-01'")->fetchColumn()) . ' companies')
    && str_contains($f->get('/portal/states?from=2020-01-01')['body'], 'Filed since Jan 1, 2020'));
$r = $f->get($urlOf($recent[0]));
check('free: a company in the window opens and shows the lookup counter', $r['status'] === 200 && str_contains($r['body'], 'Lookup 1 of ' . $flimits['lookupsPerDay'] . ' today'), (string) $r['status']);
check('free: registered agent is visible, its phone is locked', str_contains($r['body'], htmlspecialchars($agentName, ENT_QUOTES)) && !str_contains($r['body'], 'Registered agent details are a Pro feature') && str_contains($r['body'], 'fa-lock'));
check('free: filing number, effective date and source link are hidden', !str_contains($r['body'], 'Filing number') && !str_contains($r['body'], 'Effective date') && !str_contains($r['body'], 'View official record') && str_contains($r['body'], 'Filed with the state'));
check('free: officers stay locked', str_contains($r['body'], 'Officers, addresses and phone numbers are a Pro feature'));
$f->get($urlOf($recent[0]));
check('free: reopening the same company does not use another lookup', $lookups() === 1, (string) $lookups());
$r = $f->get($urlOf($old));
check('free: a company older than the window is refused and not counted', $r['status'] === 403 && str_contains($r['body'], 'before the last 7 days') && $lookups() === 1, (string) $r['status']);
for ($i = 1; $i < $flimits['lookupsPerDay']; $i++) {
    $f->get($urlOf($recent[$i]));
}
check('free: ten different companies use ten lookups', $lookups() === $flimits['lookupsPerDay'], (string) $lookups());
$r = $f->get($urlOf($recent[10]));
check('free: the eleventh company is refused with 429', $r['status'] === 429 && str_contains($r['body'], 'used all 10 free lookups') && $lookups() === $flimits['lookupsPerDay'], (string) $r['status']);
check('free: an already opened company still opens at the limit', $f->get($urlOf($recent[3]))['status'] === 200);
check('free: the Companies card shows the limit reached', str_contains($f->get('/portal/companies')['body'], '<strong>10</strong> of 10 company lookups used today'));
$pricing = (new Http('http://127.0.0.1:18088'))->get('/pricing')['body'];
check('pricing page reads the free limits from config', str_contains($pricing, 'Last 7 days of new filings') && str_contains($pricing, '10 full lookups per day'));
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'free@example.com'");
check('pro: no window and no lookup limit', $f->get($urlOf($old))['status'] === 200 && !str_contains($f->get('/portal/companies')['body'], 'company lookups used today'));
$pdo->exec("UPDATE `user` SET plan = 'free' WHERE email = 'free@example.com'");
echo "Admin users\n";
$ad = new Http('http://127.0.0.1:18088');
$ad->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'admin@example.com', 'password' => 'Admin-pass-1']]);
$freeId = (int) $pdo->query("SELECT id FROM `user` WHERE email = 'free@example.com'")->fetchColumn();
$adminId = (int) $pdo->query("SELECT id FROM `user` WHERE email = 'admin@example.com'")->fetchColumn();
$r = $ad->get('/admin/users');
check('admin users: the list shows every account and the sidebar link', $r['status'] === 200 && str_contains($r['body'], 'free@example.com') && str_contains($r['body'], 'admin@example.com') && str_contains($r['body'], '/admin/users'));
$r = $ad->get('/admin/users?q=free%40');
check('admin users: search narrows the list', str_contains($r['body'], 'free@example.com') && !str_contains($r['body'], 'pro@example.com') && str_contains($ad->get('/admin/users')['body'], 'pro@example.com'));
check('admin users: the plan filter works', !str_contains($ad->get('/admin/users?plan=bulk')['body'], 'free@example.com'));
$cust = new Http('http://127.0.0.1:18088');
$cust->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'pro@example.com', 'password' => 'Pro-pass-1']]);
check('admin users: a customer gets 403', $cust->get('/admin/users')['status'] === 403 && $cust->get("/admin/users/update?id=$freeId")['status'] === 403);
$r = (new Http('http://127.0.0.1:18088'))->get('/admin/users');
check('admin users: a guest is sent to sign in', $r['status'] === 302 && str_contains((string) $r['location'], '/portal/auth/login'));
check('admin users: an unknown user is 404', $ad->get('/admin/users/update?id=999999')['status'] === 404);

$save = fn(array $fields) => $ad->postForm("/admin/users/update?id=$freeId", ['UserForm' => $fields + ['name' => 'Free Tester', 'plan' => 'free', 'role' => 'customer', 'status' => '10']], "/admin/users/update?id=$freeId");
$r = $save(['limits' => ['free' => ['lookupsPerDay' => '3']]]);
check('admin users: saving custom limits redirects and stores them', $r['status'] === 302 && $pdo->query("SELECT limit_overrides FROM `user` WHERE id = $freeId")->fetchColumn() === '{"free":{"lookupsPerDay":3}}');
$ad->postForm("/admin/users/reset-usage?id=$freeId", [], "/admin/users/update?id=$freeId");
check('admin users: reset usage clears the lookups', $lookups() === 0);
check('admin users: the custom lookup limit shows on the free account\'s own card', str_contains($f->get('/portal/companies')['body'], '<strong>0</strong> of 3 company lookups used today'));
$r = $ad->get("/admin/users/update?id=$freeId");
check('admin users: the form shows the saved override and the plan default', str_contains($r['body'], 'value="3"') && str_contains($r['body'], 'Plan default: 10') && str_contains($r['body'], 'Company lookups today'));
$r = $save(['limits' => ['free' => ['lookupsPerDay' => 'abc']]]);
check('admin users: a bad limit is rejected and nothing changes', $r['status'] === 200 && str_contains($r['body'], 'enter a whole number') && $pdo->query("SELECT limit_overrides FROM `user` WHERE id = $freeId")->fetchColumn() === '{"free":{"lookupsPerDay":3}}');
$r = $save(['limits' => ['free' => ['lookupsPerDay' => '']]]);
check('admin users: clearing a limit goes back to the plan default', $r['status'] === 302 && $pdo->query("SELECT limit_overrides FROM `user` WHERE id = $freeId")->fetchColumn() === null
    && str_contains($f->get('/portal/companies')['body'], 'of 10 company lookups used today'));
$r = $save(['plan' => 'pro', 'limits' => ['export' => ['monthlyRows' => 'unlimited', 'perFile' => '250']]]);
$page = $ad->get("/admin/users/update?id=$freeId")['body'];
check('admin users: changing the plan applies; unlimited export and a file cap are stored', $r['status'] === 302 && $pdo->query("SELECT plan FROM `user` WHERE id = $freeId")->fetchColumn() === 'pro'
    && $pdo->query("SELECT limit_overrides FROM `user` WHERE id = $freeId")->fetchColumn() === '{"export":{"monthlyRows":null,"perFile":250}}' && str_contains($page, 'CSV export rows this month') && str_contains($page, 'Unlimited'));
$r = $save(['status' => '0']);
check('admin users: a disabled account is signed out and cannot sign in', $r['status'] === 302 && $f->get('/portal')['status'] === 302
    && str_contains((new Http('http://127.0.0.1:18088'))->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'free@example.com', 'password' => 'Free-pass-1']])['body'], 'Incorrect email or password'));
$save(['status' => '10', 'plan' => 'free']);
$pdo->exec("UPDATE `user` SET limit_overrides = NULL WHERE id = $freeId");
$r = $ad->postForm("/admin/users/update?id=$adminId", ['UserForm' => ['name' => '', 'plan' => 'free', 'role' => 'customer', 'status' => '10']], "/admin/users/update?id=$adminId");
check('admin users: an admin cannot demote or disable themselves', $r['status'] === 200 && str_contains($r['body'], 'cannot change your own role') && $pdo->query("SELECT CONCAT(role, '/', status) FROM `user` WHERE id = $adminId")->fetchColumn() === 'admin/10');
echo "Admin hold and release\n";
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'pro@example.com'");
$pdo->exec("INSERT INTO entities (state, state_filing_number, url_key, slug, name, entity_type, status, filed_at, indexed_at, publish_at)
    VALUES ('OH', 'H900', 'h900', 'hold-test-llc', 'HOLD TEST LLC', 'LLC', 'Active', '2026-11-01 00:00:00', UTC_TIMESTAMP(), NULL)");
$hid = (int) $pdo->lastInsertId();
$hold = fn() => $pdo->query("SELECT CONCAT(IFNULL(publish_override, '-'), '/', IF(publish_at IS NULL, 'null', IF(publish_at <= UTC_TIMESTAMP(), 'live', 'later'))) FROM entities WHERE id = $hid")->fetchColumn();
check('hold: a held company is a 404 for customers', $hold() === '-/null' && $cust->get('/portal/company/oh/hold-test-llc-h900')['status'] === 404);
check('hold: the Held tab finds it by name and by filing number', str_contains($ad->get('/admin/database?show=held&q=HOLD+TEST')['body'], 'HOLD TEST LLC') && str_contains($ad->get('/admin/database?show=held&q=H900')['body'], 'HOLD TEST LLC')
    && !str_contains($ad->get('/admin/database?show=held&q=nothing-matches-this')['body'], 'HOLD TEST LLC'));
$r = $ad->postForm('/admin/database/publish', ['ids' => [$hid], 'mode' => 'release', 'show' => 'held', 'page' => 1, 'q' => ''], '/admin/database?show=held');
check('release: an admin can release a held company now', $r['status'] === 302 && $hold() === 'release/live' && $cust->get('/portal/company/oh/hold-test-llc-h900')['status'] === 200
    && (int) $pdo->query("SELECT publish_override_by FROM entities WHERE id = $hid")->fetchColumn() === $adminId);
check('release: the Published tab lists it with a manual badge', str_contains($ad->get('/admin/database?show=published&q=HOLD+TEST')['body'], 'Manual release'));
$r = $ad->postForm('/admin/database/publish', ['ids' => [$hid], 'mode' => 'hold', 'show' => 'published', 'page' => 1, 'q' => ''], '/admin/database?show=published');
check('hold: an admin can pull a published company back', $r['status'] === 302 && $hold() === 'hold/null' && $cust->get('/portal/company/oh/hold-test-llc-h900')['status'] === 404);
$pdo->exec('CREATE TABLE IF NOT EXISTS manual_companies (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, data LONGTEXT NOT NULL,
    ignored_validation_count INT DEFAULT 0, qualified INT DEFAULT NULL, processed INT DEFAULT 0, date_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$pdo->exec("INSERT INTO manual_companies (id, user_id, data, qualified, date_added) VALUES (330, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'OH', 'name' => 'HOLD TEST LLC', 'document_number' => 'H900', 'type' => 'LLC', 'status' => '1'], 'registered_agent' => [], 'officers' => [], 'more_info' => []])) . ", 0, NOW())");
$capture('php yii mirror/backfill', $mirrorEnv);
check('hold: a source sync does not undo a manual hold', $hold() === 'hold/null' && (int) $pdo->query("SELECT source_id FROM entities WHERE id = $hid")->fetchColumn() === 330);
$r = $ad->postForm('/admin/database/publish', ['mode' => 'release', 'show' => 'held'], '/admin/database?show=held');
check('release: posting with nothing selected changes nothing', $r['status'] === 302 && $hold() === 'hold/null');
$r = $cust->req('POST', '/admin/database/publish', ['ids' => [$hid], 'mode' => 'release']);
check('release: customers cannot release companies', in_array($r['status'], [400, 403], true) && $hold() === 'hold/null');
$pdo->exec("DELETE FROM entities WHERE id = $hid");
$pdo->exec("DROP TABLE manual_companies");
echo "Admin opt-outs\n";
$pdo->exec("INSERT INTO entities (state, state_filing_number, url_key, slug, name, entity_type, status, filed_at, indexed_at, publish_at)
    VALUES ('OH', 'O700', 'o700', 'opt-test-llc', 'OPT TEST LLC', 'LLC', 'Active', '2026-11-01 00:00:00', UTC_TIMESTAMP(), UTC_TIMESTAMP() - INTERVAL 1 MINUTE)");
$oid = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO people (entity_id, name, role, title, address) VALUES ($oid, 'Owen Officer', 'officer', 'MGR', '1 Home St, Columbus, OH 43004'), ($oid, 'Ava Agent', 'agent', NULL, '2 Agent Way, Columbus, OH 43004')");
$optPage = '/portal/company/oh/opt-test-llc-o700';
$optFirst = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM opt_out_requests')->fetchColumn();
$optMailSince = time() - 1;
$g = new Http('http://127.0.0.1:18088');
$r = $g->ajax('/api/opt-out', ['entity_ref' => 'https://example.com' . $optPage, 'person_name' => 'Owen Officer', 'email' => 'owen@example.com', 'reason' => 'my home address'], '/opt-out');
$rid = (int) $pdo->query("SELECT id FROM opt_out_requests WHERE email = 'owen@example.com' ORDER BY id DESC LIMIT 1")->fetchColumn();
check('opt-out: the public form stores a pending request', $r['status'] === 200 && $rid > $optFirst && $pdo->query("SELECT status FROM opt_out_requests WHERE id = $rid")->fetchColumn() === 'pending');
$got = mailsWith('owen@example.com', $optMailSince);
check('opt-out: the requester is emailed a confirmation with the reference', (bool) array_filter($got, fn($b) => str_contains($b, 'We received your removal request') && str_contains($b, "#$rid")));
check('opt-out: admins are emailed the new request with a link to it', (bool) array_filter(mailsWith("New removal request #$rid", $optMailSince), fn($b) => str_contains($b, "/admin/opt-outs/view?id=$rid") && str_contains($b, 'my home address')));
$page = $cust->get($optPage)['body'];
check('opt-out: before approval the officer and agent are visible', str_contains($page, 'Owen Officer') && str_contains($page, 'Ava Agent'));
check('opt-out: the queue lists the pending request', str_contains($ad->get('/admin/opt-outs')['body'], 'owen@example.com') && str_contains($ad->get('/admin/opt-outs')['body'], 'Review'));
$r = $ad->get("/admin/opt-outs/view?id=$rid");
check('opt-out: the request page finds the company from the pasted URL', $r['status'] === 200 && str_contains($r['body'], 'OPT TEST LLC') && str_contains($r['body'], 'Hide all people'));
check('opt-out: a state and filing number also finds it', str_contains($ad->get("/admin/opt-outs/view?id=$rid&ref=" . urlencode('OH O700'))['body'], 'OPT TEST LLC') && !str_contains($ad->get("/admin/opt-outs/view?id=$rid&ref=" . urlencode('OH 9999999'))['body'], 'OPT TEST LLC'));
check('opt-out: a customer gets 403 on the queue', $cust->get('/admin/opt-outs')['status'] === 403 && $cust->get("/admin/opt-outs/view?id=$rid")['status'] === 403);
$r = $ad->postForm("/admin/opt-outs/resolve?id=$rid", ['note' => 'Confirmed by email'], "/admin/opt-outs/view?id=$rid");
check('opt-out: approving without choosing a company changes nothing', $r['status'] === 302 && $pdo->query("SELECT status FROM opt_out_requests WHERE id = $rid")->fetchColumn() === 'pending' && (int) $pdo->query("SELECT people_hidden FROM entities WHERE id = $oid")->fetchColumn() === 0);
$r = $ad->postForm("/admin/opt-outs/resolve?id=$rid", ['entity_id' => $oid, 'note' => 'Confirmed by email'], "/admin/opt-outs/view?id=$rid");
$row = $pdo->query("SELECT status, entity_id, entity_state, entity_url_key, resolved_by, note FROM opt_out_requests WHERE id = $rid")->fetch(PDO::FETCH_ASSOC);
check('opt-out: approving hides the people and records who and what', $r['status'] === 302 && (int) $pdo->query("SELECT people_hidden FROM entities WHERE id = $oid")->fetchColumn() === 1
    && $row['status'] === 'done' && (int) $row['entity_id'] === $oid && $row['entity_state'] === 'OH' && $row['entity_url_key'] === 'o700' && (int) $row['resolved_by'] === $adminId && $row['note'] === 'Confirmed by email');
check('opt-out: approving emails the requester which company was cleared, without the internal note', (bool) array_filter(mailsWith('owen@example.com', $optMailSince), fn($b) => str_contains($b, 'Your details have been removed') && str_contains($b, 'OPT TEST LLC') && !str_contains($b, 'Confirmed by email')));
$page = $cust->get($optPage)['body'];
check('opt-out: the company page shows no people, agent or address and says why', $page !== '' && !str_contains($page, 'Owen Officer') && !str_contains($page, 'Ava Agent') && !str_contains($page, '1 Home St') && str_contains($page, 'removed from this company at the owner'));
check('opt-out: searching the officer\'s name no longer finds the company', str_contains($cust->get('/portal/companies?q=Owen+Officer')['body'], '0 companies') && str_contains($cust->get('/portal/companies?q=OPT+TEST')['body'], 'OPT TEST LLC'));
$r = $cust->postForm('/portal/export/companies', ['q' => 'OPT TEST'], '/portal/companies');
check('opt-out: the CSV export keeps the company but not the people', str_contains($r['body'], 'OPT TEST LLC') && !str_contains($r['body'], 'Owen Officer') && !str_contains($r['body'], 'Ava Agent'));
require_once "$root/helpers/Fmt.php";
require_once "$root/modules/apiv1/serializers/EntitySerializer.php";
$row = ['id' => 1, 'state' => 'OH', 'state_filing_number' => 'O700', 'name' => 'OPT TEST LLC', 'entity_type' => 'LLC', 'status' => 'Active', 'filed_at' => '2026-11-01 00:00:00', 'formation_date' => null,
    'indexed_at' => '2026-11-01 00:00:00', 'county' => null, 'principal_address' => '1 Home St', 'mailing_address' => '1 Home St', 'naics_guess' => null, 'naics_label' => null, 'source_url' => null, 'agent' => null,
    'people' => [['role' => 'officer', 'name' => 'Owen Officer', 'title' => 'MGR', 'address' => '1 Home St', 'street' => null, 'city' => null, 'state' => null, 'zip' => null, 'phone' => '6145550100']]];
$shown = \app\modules\apiv1\serializers\EntitySerializer::entity($row);
$gone = \app\modules\apiv1\serializers\EntitySerializer::entity($row + ['people_hidden' => 1]);
check('opt-out: the API shape drops people, agent and addresses only when hidden', count($shown['people']) === 1 && $shown['principal_address'] === '1 Home St'
    && $gone['people'] === [] && $gone['registered_agent'] === null && $gone['principal_address'] === null && $gone['mailing_address'] === null && $gone['name'] === 'OPT TEST LLC');
$pdo->exec("DELETE FROM entities WHERE id = $oid");
$pdo->exec('CREATE TABLE IF NOT EXISTS manual_companies (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, data LONGTEXT NOT NULL,
    ignored_validation_count INT DEFAULT 0, qualified INT DEFAULT NULL, processed INT DEFAULT 0, date_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$pdo->exec("INSERT INTO manual_companies (id, user_id, data, qualified, date_added) VALUES (340, 4, " . $pdo->quote(json_encode(['company' => ['state' => 'OH', 'name' => 'OPT TEST LLC', 'document_number' => 'O700', 'type' => 'LLC', 'status' => '1'],
    'registered_agent' => ['fname' => 'Ava', 'lname' => 'Agent', 'full_address' => '2 Agent Way, Columbus, OH 43004'], 'officers' => [['fname' => 'Owen', 'lname' => 'Officer', 'o_title' => 'MGR']], 'more_info' => []])) . ", 0, NOW())");
$capture('php yii mirror/backfill', $mirrorEnv);
$newId = (int) $pdo->query("SELECT id FROM entities WHERE state = 'OH' AND url_key = 'o700'")->fetchColumn();
check('opt-out: a company that comes back from the source is hidden again', $newId > 0 && $newId !== $oid && (int) $pdo->query("SELECT people_hidden FROM entities WHERE id = $newId")->fetchColumn() === 1
    && (int) $pdo->query("SELECT COUNT(*) FROM people WHERE entity_id = $newId")->fetchColumn() === 2 && !str_contains($cust->get($optPage)['body'], 'Owen Officer'));
$r = $ad->postForm("/admin/opt-outs/reopen?id=$rid", [], "/admin/opt-outs/view?id=$rid");
check('opt-out: reopening shows the people again and the request is pending', $r['status'] === 302 && $pdo->query("SELECT status FROM opt_out_requests WHERE id = $rid")->fetchColumn() === 'pending'
    && (int) $pdo->query("SELECT people_hidden FROM entities WHERE id = $newId")->fetchColumn() === 0 && str_contains($cust->get($optPage)['body'], 'Owen Officer'));
$r = $ad->postForm("/admin/opt-outs/reject?id=$rid", ['note' => 'Not the owner'], "/admin/opt-outs/view?id=$rid");
check('opt-out: rejecting closes the request and changes nothing', $r['status'] === 302 && $pdo->query("SELECT status FROM opt_out_requests WHERE id = $rid")->fetchColumn() === 'rejected'
    && (int) $pdo->query("SELECT people_hidden FROM entities WHERE id = $newId")->fetchColumn() === 0);
check('opt-out: rejecting emails the requester, without the internal note', (bool) array_filter(mailsWith('owen@example.com', $optMailSince), fn($b) => str_contains($b, 'About your removal request') && !str_contains($b, 'Not the owner')));
check('opt-out: a closed request cannot be approved again', $ad->postForm("/admin/opt-outs/resolve?id=$rid", ['entity_id' => $newId], "/admin/opt-outs/view?id=$rid")['status'] === 302 && $pdo->query("SELECT status FROM opt_out_requests WHERE id = $rid")->fetchColumn() === 'rejected'
    && (int) $pdo->query("SELECT people_hidden FROM entities WHERE id = $newId")->fetchColumn() === 0);
$pdo->exec("DELETE FROM entities WHERE id = $newId");
$pdo->exec('DROP TABLE manual_companies');
proc_terminate($srvA);

echo "Password reset\n";
run('php yii cache/flush-all --interactive=0', $env, $root);
$srvR = startServer(18087, $env, $root);
$rbase = 'http://127.0.0.1:18087';
$owner = new Http($rbase);
$owner->postForm('/portal/auth/register', ['RegisterForm' => ['name' => 'Rita Reset', 'email' => 'reset@example.com', 'password' => 'oldpassword1', 'password_repeat' => 'oldpassword1']]);
check('reset: the signed-in session works before the reset', $owner->get('/portal')['status'] === 200);
$g = new Http($rbase);
check('reset: login page links to the forgot-password page', str_contains($g->get('/portal/auth/login')['body'], '/portal/auth/forgot'));
$t0 = time() - 1;
$r = $g->postForm('/portal/auth/forgot', ['ForgotPasswordForm' => ['email' => 'nobody@example.com']]);
check('reset: an unknown email gets the same answer and no mail', $r['status'] === 200 && str_contains($r['body'], 'Check your email') && mailsWith('nobody@example.com', $t0) === []);
$r = $g->postForm('/portal/auth/forgot', ['ForgotPasswordForm' => ['email' => 'reset@example.com']]);
$mails = mailsWith('reset@example.com', $t0);
$token = $mails && preg_match('/token=([A-Za-z0-9_-]{43})/', $mails[0], $mm) ? $mm[1] : '';
check('reset: a known email gets one mail with a reset link', $r['status'] === 200 && str_contains($r['body'], 'Check your email') && count($mails) === 1 && $token !== '' && str_contains($mails[0], 'Reset your password'));
check('reset: only a hash of the token is stored', $token !== '' && $pdo->query("SELECT password_reset_hash FROM `user` WHERE email = 'reset@example.com'")->fetchColumn() === hash('sha256', $token));
$g->postForm('/portal/auth/forgot', ['ForgotPasswordForm' => ['email' => 'reset@example.com']]);
check('reset: a second request within a minute sends no new mail', count(mailsWith('reset@example.com', $t0)) === 1);
check('reset: a wrong token shows the expired page', str_contains($g->get('/portal/auth/reset?token=' . str_repeat('x', 43))['body'], 'expired or was already used'));
$path = '/portal/auth/reset?token=' . $token;
$r = $g->get($path);
check('reset: the link opens the new-password form with no-referrer', $r['status'] === 200 && str_contains($r['body'], 'Choose a new password') && str_contains($r['body'], 'content="no-referrer"'));
$r = $g->postForm($path, ['ResetPasswordForm' => ['password' => 'newpassword1', 'password_repeat' => 'different1']]);
check('reset: mismatched passwords are rejected', $r['status'] === 200 && str_contains($r['body'], 'Passwords do not match'));
$t1 = time() - 1;
$r = $g->postForm($path, ['ResetPasswordForm' => ['password' => 'newpassword1', 'password_repeat' => 'newpassword1']]);
check('reset: a valid new password redirects to sign in', $r['status'] === 302 && str_contains((string) $r['location'], '/portal/auth/login'), $r['status'] . ' ' . $r['location']);
check('reset: a password-changed notice is mailed', (bool) array_filter(mailsWith('reset@example.com', $t1), fn($b) => str_contains($b, 'Your password was changed')));
check('reset: the token works only once', str_contains($g->get($path)['body'], 'expired or was already used'));
check('reset: other sessions are signed out', $owner->get('/portal')['status'] === 302);
$r = $g->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'reset@example.com', 'password' => 'oldpassword1']]);
check('reset: the old password no longer works', $r['status'] === 200 && str_contains($r['body'], 'Incorrect email or password'));
$r = $g->postForm('/portal/auth/login', ['LoginForm' => ['email' => 'reset@example.com', 'password' => 'newpassword1']]);
check('reset: the new password signs in', $r['status'] === 302);
$pdo->exec("UPDATE `user` SET password_reset_hash = " . $pdo->quote(hash('sha256', 'expired-token-0123456789-0123456789-0123456')) . ", password_reset_expires_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE email = 'reset@example.com'");
check('reset: an expired token is refused', str_contains((new Http($rbase))->get('/portal/auth/reset?token=expired-token-0123456789-0123456789-0123456')['body'], 'expired or was already used'));
proc_terminate($srvR);

echo "Webhooks\n";
$whEnv = ['WEBHOOKS_ALLOW_PRIVATE' => 'true'] + $env;
$recvDir = sys_get_temp_dir() . '/ei-webhook-recv-' . getmypid();
@mkdir($recvDir, 0700, true);
file_put_contents("$recvDir/router.php", '<?php $d = __DIR__; $s = (int) (@file_get_contents("$d/status") ?: 200);'
    . ' file_put_contents("$d/hits.jsonl", json_encode(["headers" => array_change_key_case(getallheaders()), "body" => file_get_contents("php://input")]) . "\n", FILE_APPEND);'
    . ' http_response_code($s); echo "ok";');
file_put_contents("$recvDir/status", '200');
$recv = proc_open(['php', '-S', '127.0.0.1:18090', "$recvDir/router.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $recvDir);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', 18090); $i++) {
    usleep(100000);
}
$hits = function () use ($recvDir): array {
    $out = [];
    foreach (file("$recvDir/hits.jsonl", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $out[] = json_decode($line, true);
    }
    return $out;
};
$signed = function (array $hit, string $secret): bool {
    $parts = [];
    foreach (explode(',', (string) ($hit['headers']['x-entityindex-signature'] ?? '')) as $kv) {
        [$k, $v] = array_pad(explode('=', $kv, 2), 2, '');
        $parts[$k] = $v;
    }
    return isset($parts['t'], $parts['v1']) && hash_equals(hash_hmac('sha256', $parts['t'] . '.' . $hit['body'], $secret), $parts['v1']);
};
$whRun = fn() => $capture('php yii webhooks/run', $whEnv);
$srvW = startServer(18089, $whEnv, $root);
$srvW2 = startServer(18091, $env, $root);
[$code, $out] = $whRun();
check('webhooks: run works with no endpoints', $code === 0 && str_contains($out, 'new events'), $out);

$free = new Http('http://127.0.0.1:18089');
$free->postForm('/portal/auth/register', ['RegisterForm' => ['name' => 'Free Hooks', 'email' => 'free-hooks@example.com', 'password' => 'password1', 'password_repeat' => 'password1']]);
check('webhooks: a free account sees the upgrade message', str_contains($free->get('/portal/webhooks')['body'], 'Webhooks are not part of your plan'));
check('webhooks: a free account cannot add an endpoint', $free->postForm('/portal/webhooks/create', ['WebhookEndpoint' => ['url' => 'https://example.com/x']], '/portal/webhooks')['status'] === 403);

$w = new Http('http://127.0.0.1:18089');
$w->postForm('/portal/auth/register', ['RegisterForm' => ['name' => 'Hook Owner', 'email' => 'hooks@example.com', 'password' => 'password1', 'password_repeat' => 'password1']]);
$pdo->exec("UPDATE `user` SET plan = 'business' WHERE email = 'hooks@example.com'");
$r = $w->get('/portal/webhooks');
check('webhooks: the page renders for a Business account and is in the sidebar', $r['status'] === 200 && str_contains($r['body'], 'Add an endpoint') && str_contains($r['body'], '/portal/webhooks'));
$strict = new Http('http://127.0.0.1:18091');
$strict->jar = $w->jar;
foreach (['http://example.com/hook' => 'Use an https:// URL', 'https://10.0.0.5/hook' => 'public server', 'https://hooks.localhost/hook' => 'public server', 'https://user:pw@example.com/hook' => 'not a valid URL'] as $bad => $msg) {
    $r = $strict->postForm('/portal/webhooks/create', ['WebhookEndpoint' => ['url' => $bad]], '/portal/webhooks');
    check("webhooks: $bad is refused", $r['status'] === 200 && str_contains($r['body'], $msg));
}
$r = $w->postForm('/portal/webhooks/create', ['WebhookEndpoint' => ['url' => 'http://127.0.0.1:18090/hook', 'description' => 'Test receiver', 'stateList' => ['OH'], 'typeList' => ['LLC']]], '/portal/webhooks');
$ep = $pdo->query("SELECT w.* FROM webhook_endpoint w JOIN `user` u ON u.id = w.user_id WHERE u.email = 'hooks@example.com'")->fetch(PDO::FETCH_ASSOC);
check('webhooks: an endpoint is created with its filters and a secret', $r['status'] === 302 && $ep && $ep['states'] === 'OH' && json_decode($ep['types'], true) === ['LLC'] && str_starts_with($ep['secret'], 'whsec_'));
$epId = (int) ($ep['id'] ?? 0);
$secret = (string) ($ep['secret'] ?? '');
check('webhooks: another account cannot open it', $free->get("/portal/webhooks/view?id=$epId")['status'] === 403);

$r = $w->postForm("/portal/webhooks/test?id=$epId", [], "/portal/webhooks/view?id=$epId");
$h = $hits();
check('webhooks: a test event is delivered and signed', $r['status'] === 302 && count($h) === 1 && ($h[0]['headers']['x-entityindex-event'] ?? '') === 'test' && $signed($h[0], $secret)
    && json_decode($h[0]['body'], true)['data'][0]['name'] === 'Example Roofing LLC');
check('webhooks: a wrong secret does not verify', !$signed($h[0], 'whsec_wrong'));

$ins = fn(string $state, string $no, string $name, string $type, string $publish) => $pdo->exec("INSERT INTO entities (state, state_filing_number, url_key, slug, name, entity_type, status, filed_at, indexed_at, publish_at)
    VALUES ('$state', '$no', '" . strtolower($no) . "', '" . strtolower($no) . "-hook', '$name', '$type', 'Active', '2026-11-01 00:00:00', UTC_TIMESTAMP(), $publish)") ? (int) $pdo->lastInsertId() : 0;
$made = [
    $ins('OH', 'W1', 'HOOK OHIO LLC', 'LLC', 'UTC_TIMESTAMP() - INTERVAL 1 MINUTE'),
    $ins('FL', 'W2', 'HOOK FLORIDA LLC', 'LLC', 'UTC_TIMESTAMP() - INTERVAL 1 MINUTE'),
    $ins('OH', 'W3', 'HOOK OHIO CORP', 'Corporation', 'UTC_TIMESTAMP() - INTERVAL 1 MINUTE'),
    $held = $ins('OH', 'W4', 'HOOK HELD LLC', 'LLC', 'UTC_TIMESTAMP() + INTERVAL 1 DAY'),
];
[$code, $out] = $whRun();
$h = $hits();
$last = $h ? json_decode(end($h)['body'], true) : [];
$names = array_column($last['data'] ?? [], 'name');
check('webhooks: new public filings that match are sent once, signed, in the API shape', $code === 0 && count($h) === 2 && ($last['event'] ?? '') === 'entities.published' && $names === ['HOOK OHIO LLC'] && $signed(end($h), $secret)
    && isset($last['data'][0]['filing_number'], $last['data'][0]['people']) && str_starts_with((string) (end($h)['headers']['x-entityindex-delivery'] ?? ''), 'dlv_'), $out);
check('webhooks: other states, other types and held companies are not sent', !in_array('HOOK FLORIDA LLC', $names, true) && !in_array('HOOK OHIO CORP', $names, true) && !in_array('HOOK HELD LLC', $names, true));
$whRun();
check('webhooks: a second run sends nothing new', count($hits()) === 2);

$pdo->exec("UPDATE entities SET publish_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = $held");
$whRun();
$h = $hits();
check('webhooks: a held company is sent when it goes public', count($h) === 3 && array_column(json_decode(end($h)['body'], true)['data'], 'name') === ['HOOK HELD LLC']);

file_put_contents("$recvDir/status", '500');
$made[] = $ins('OH', 'W5', 'HOOK RETRY LLC', 'LLC', 'UTC_TIMESTAMP() - INTERVAL 1 MINUTE');
$whRun();
$dl = $pdo->query("SELECT * FROM webhook_delivery WHERE endpoint_id = $epId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('webhooks: a failed delivery is kept for a retry later', $dl['status'] === 'pending' && (int) $dl['attempts'] === 1 && (int) $dl['response_code'] === 500 && $dl['next_attempt_at'] > gmdate('Y-m-d H:i:s')
    && (int) $pdo->query("SELECT failure_count FROM webhook_endpoint WHERE id = $epId")->fetchColumn() === 1);
file_put_contents("$recvDir/status", '200');
$pdo->exec("UPDATE webhook_delivery SET next_attempt_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE id = {$dl['id']}");
$whRun();
$h = $hits();
check('webhooks: the retry succeeds with the same delivery id and resets the failure count', $pdo->query("SELECT status FROM webhook_delivery WHERE id = {$dl['id']}")->fetchColumn() === 'success'
    && (end($h)['headers']['x-entityindex-delivery'] ?? '') === 'dlv_' . $dl['id'] && (int) $pdo->query("SELECT failure_count FROM webhook_endpoint WHERE id = $epId")->fetchColumn() === 0);

$w->postForm("/portal/webhooks/pause?id=$epId", [], "/portal/webhooks/view?id=$epId");
$made[] = $ins('OH', 'W6', 'HOOK PAUSED LLC', 'LLC', 'UTC_TIMESTAMP() - INTERVAL 1 MINUTE');
$before = count($hits());
$whRun();
check('webhooks: a paused endpoint gets nothing', count($hits()) === $before);
$w->postForm("/portal/webhooks/resume?id=$epId", [], "/portal/webhooks/view?id=$epId");
$whRun();
$h = $hits();
check('webhooks: resuming catches up on what was missed', count($h) === $before + 1 && array_column(json_decode(end($h)['body'], true)['data'], 'name') === ['HOOK PAUSED LLC']);

$r = $w->get("/portal/webhooks/view?id=$epId");
check('webhooks: the endpoint page lists deliveries and the secret', $r['status'] === 200 && str_contains($r['body'], 'dlv_' . $dl['id']) && str_contains($r['body'], $secret));
$r = $w->postForm("/portal/webhooks/delete?id=$epId", [], "/portal/webhooks/view?id=$epId");
check('webhooks: deleting removes the endpoint and its deliveries', $r['status'] === 302 && (int) $pdo->query("SELECT COUNT(*) FROM webhook_delivery WHERE endpoint_id = $epId")->fetchColumn() === 0);

$pdo->exec('DELETE FROM entities WHERE id IN (' . implode(',', array_filter($made)) . ')');
proc_terminate($srvW);
proc_terminate($srvW2);
proc_terminate($recv);
array_map('unlink', glob("$recvDir/*") ?: []);
@rmdir($recvDir);


echo "Saved-search alerts\n";
$srvA = startServer(18092, $env, $root);
$alertRun = fn(string $extra = '') => $capture('php yii alerts/send' . $extra, $env);
$af = new Http('http://127.0.0.1:18092');
$af->postForm('/portal/auth/register', ['RegisterForm' => ['name' => 'Free Alerts', 'email' => 'free-alerts@example.com', 'password' => 'password1', 'password_repeat' => 'password1']]);
check('alerts: a free account sees the upgrade message', str_contains($af->get('/portal/alerts')['body'], 'Alerts are not part of your plan'));
check('alerts: a free account cannot save a search', $af->postForm('/portal/alerts/create', ['filters' => ['state' => 'OH']], '/portal/alerts')['status'] === 403);

$a = new Http('http://127.0.0.1:18092');
$a->postForm('/portal/auth/register', ['RegisterForm' => ['name' => 'Alice Alerts', 'email' => 'alerts@example.com', 'password' => 'password1', 'password_repeat' => 'password1']]);
$pdo->exec("UPDATE `user` SET plan = 'pro' WHERE email = 'alerts@example.com'");
$r = $a->get('/portal/companies?state=OH&type=LLC');
check('alerts: the Companies page offers Save search and the sidebar links to Alerts', str_contains($r['body'], 'save-search-modal') && str_contains($r['body'], '/portal/alerts'));
$r = $a->postForm('/portal/alerts/create', ['name' => 'Ohio LLCs', 'email' => '1', 'filters' => ['state' => 'oh', 'type' => 'LLC', 'from' => '2026-01-01']], '/portal/companies');
$ss = $pdo->query("SELECT s.* FROM saved_search s JOIN `user` u ON u.id = s.user_id WHERE u.email = 'alerts@example.com'")->fetch(PDO::FETCH_ASSOC);
check('alerts: a search is saved with its filters and without the date range', $r['status'] === 302 && $ss && $ss['name'] === 'Ohio LLCs' && json_decode($ss['params'], true) === ['state' => 'OH', 'type' => 'LLC'] && (int) $ss['email_enabled'] === 1);
$ssId = (int) ($ss['id'] ?? 0);
$a->postForm('/portal/alerts/create', ['name' => 'Again', 'filters' => ['type' => 'LLC', 'state' => 'OH']], '/portal/companies');
check('alerts: the same search is not saved twice', (int) $pdo->query("SELECT COUNT(*) FROM saved_search WHERE user_id = {$ss['user_id']}")->fetchColumn() === 1);
check('alerts: another account cannot change it', $af->postForm("/portal/alerts/toggle?id=$ssId", [], '/portal/alerts')['status'] === 403);

$pdo->exec("UPDATE saved_search SET cursor_at = UTC_TIMESTAMP() - INTERVAL 1 HOUR WHERE id = $ssId");
$ains = fn(string $state, string $no, string $name, string $type, string $publish) => $pdo->exec("INSERT INTO entities (state, state_filing_number, url_key, slug, name, entity_type, status, filed_at, indexed_at, publish_at)
    VALUES ('$state', '$no', '" . strtolower($no) . "', '" . strtolower($no) . "-alert', '$name', '$type', 'Active', '2026-11-01 00:00:00', UTC_TIMESTAMP(), $publish)") ? (int) $pdo->lastInsertId() : 0;
$amade = [
    $ains('OH', 'A1', 'ALERT OHIO LLC', 'LLC', 'UTC_TIMESTAMP() - INTERVAL 10 MINUTE'),
    $ains('FL', 'A2', 'ALERT FLORIDA LLC', 'LLC', 'UTC_TIMESTAMP() - INTERVAL 10 MINUTE'),
    $ains('OH', 'A3', 'ALERT OHIO CORP', 'Corporation', 'UTC_TIMESTAMP() - INTERVAL 10 MINUTE'),
    $ains('OH', 'A4', 'ALERT HELD LLC', 'LLC', 'UTC_TIMESTAMP() + INTERVAL 1 DAY'),
    $ains('OH', 'A5', 'ALERT OLD LLC', 'LLC', 'UTC_TIMESTAMP() - INTERVAL 2 DAY'),
];
$alertSince = time() - 1;
[$code, $out] = $alertRun();
$mail = mailsWith('alerts@example.com', $alertSince);
$body = $mail[0] ?? '';
check('alerts: the morning run emails the new matches once', $code === 0 && count($mail) === 1 && str_contains($body, '1 new company: Ohio LLCs') && str_contains($body, 'ALERT OHIO LLC'), $out);
check('alerts: other states, other types, held and older companies are left out', !str_contains($body, 'ALERT FLORIDA LLC') && !str_contains($body, 'ALERT OHIO CORP') && !str_contains($body, 'ALERT HELD LLC') && !str_contains($body, 'ALERT OLD LLC'));
check('alerts: the email links to the search and has an unsubscribe link and header', str_contains($body, '/portal/companies?') && str_contains($body, '/portal/alerts/unsubscribe?token=' . $ss['unsubscribe_token']) && stripos($body, 'List-Unsubscribe:') !== false);
$alertRun();
check('alerts: a second run the same day sends nothing', count(mailsWith('alerts@example.com', $alertSince)) === 1);

$pdo->exec("UPDATE entities SET publish_at = UTC_TIMESTAMP() - INTERVAL 1 SECOND WHERE id = {$amade[3]}");
$alertRun(' --force');
$mail = mailsWith('ALERT HELD LLC', $alertSince);
check('alerts: a held company is mailed after it goes public, and only it', count($mail) === 1 && !str_contains($mail[0], 'ALERT OHIO LLC'));

$guest = new Http('http://127.0.0.1:18092');
$r = $guest->get('/portal/alerts/unsubscribe?token=' . $ss['unsubscribe_token']);
check('alerts: the unsubscribe link asks first and changes nothing on GET', $r['status'] === 200 && str_contains($r['body'], 'Stop alert emails?') && (int) $pdo->query("SELECT email_enabled FROM saved_search WHERE id = $ssId")->fetchColumn() === 1);
$r = $guest->postForm('/portal/alerts/unsubscribe?token=' . $ss['unsubscribe_token'], []);
check('alerts: confirming stops emails for that search without signing in', $r['status'] === 200 && str_contains($r['body'], 'Emails stopped') && (int) $pdo->query("SELECT email_enabled FROM saved_search WHERE id = $ssId")->fetchColumn() === 0);
check('alerts: an unknown token is refused politely', str_contains($guest->get('/portal/alerts/unsubscribe?token=' . str_repeat('0', 32))['body'], 'Link not recognised'));
$amade[] = $ains('OH', 'A6', 'ALERT MUTED LLC', 'LLC', 'UTC_TIMESTAMP() - INTERVAL 1 SECOND');
$alertRun(' --force');
check('alerts: a search with emails off gets nothing', !mailsWith('ALERT MUTED LLC', $alertSince));

$r = $a->postForm("/portal/alerts/delete?id=$ssId", [], '/portal/alerts');
check('alerts: deleting removes the saved search', $r['status'] === 302 && (int) $pdo->query("SELECT COUNT(*) FROM saved_search WHERE id = $ssId")->fetchColumn() === 0);

$pdo->exec('DELETE FROM entities WHERE id IN (' . implode(',', array_filter($amade)) . ')');
proc_terminate($srvA);

echo "\n" . ($checks - $failures) . "/$checks checks passed" . ($failures ? ", $failures FAILED" : '') . ".\n";
exit($failures ? 1 : 0);
