<?php
/**
 * Smoke test for the Vehicle Owner and Mechanic roles.
 *
 * Usage: php tests/smoke.php [base_url]
 *   base_url defaults to http://localhost/autocare-new/public_html
 *
 * Logs in through the team login (api/login-api.php?action=login), requests every .php page and every GET endpoint
 * for each role, prints a PASS/FAIL table, lists hrefs pointing to .html or "#",
 * then shows Apache error.log lines written during the run. Read-only: only GET
 * requests are made after login.
 */

$base = rtrim($argv[1] ?? 'http://localhost/autocare-new/public_html', '/');
$root = dirname(__DIR__) . '/public_html';
$errorLog = 'C:/xampp/apache/logs/error.log';
$phpErrorLog = 'C:/xampp/php/logs/php_error_log';

$roles = [
    'owner' => [
        'email' => 'owner@autocare.com', 'password' => 'password123',
        'pages' => 'pages/vehicleowner', 'api' => 'api/vehicleowner',
    ],
    'mechanic' => [
        'email' => 'mechanic@autocare.com', 'password' => 'password123',
        'pages' => 'pages/mechanic', 'api' => 'api/mechanic',
    ],
];

// Endpoints whose GET needs a job id (documented as "GET ?job_id=ID", not optional).
$needsJobId = ['api/mechanic/parts-labor.php', 'api/mechanic/photos.php'];

$logOffsets = [];
foreach ([$errorLog, $phpErrorLog] as $log) {
    $logOffsets[$log] = is_file($log) ? filesize($log) : 0;
}
$startedAt = date('Y-m-d H:i:s');

/** Perform one request without following redirects. */
function http(string $url, string $cookieJar, ?string $jsonBody = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_TIMEOUT => 30,
    ];
    if ($jsonBody !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $jsonBody;
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => 0, 'location' => null, 'body' => '', 'error' => $err];
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $headerSize);
    $location = preg_match('/^Location:\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
    return ['status' => $status, 'location' => $location, 'body' => substr($raw, $headerSize), 'error' => null];
}

/** First PHP warning/notice/fatal error rendered into the output, if any. */
function php_error_in(string $body): ?string
{
    if (preg_match('/(?:<b>)?(Warning|Notice|Fatal error|Parse error|Deprecated|Uncaught)(?:<\/b>)?:\s*([^\n]{0,200}?)\s+in\s+(?:<b>)?[^\n]+?(?:<\/b>)?\s+on line\s+(?:<b>)?(\d+)/i', $body, $m)) {
        return 'PHP ' . $m[1] . ': ' . strip_tags($m[2]) . ' (line ' . $m[3] . ')';
    }
    return null;
}

function check_page(array $res): array
{
    if ($res['error']) return ['FAIL', 'request error: ' . $res['error']];
    if ($res['location'] !== null && stripos($res['location'], 'login') !== false) {
        return ['FAIL', "HTTP {$res['status']} redirect to login ({$res['location']})"];
    }
    if ($res['status'] !== 200) {
        return ['FAIL', "HTTP {$res['status']}" . ($res['location'] ? " -> {$res['location']}" : '')];
    }
    if (stripos($res['body'], 'Something went wrong') !== false) return ['FAIL', 'contains "Something went wrong"'];
    if ($e = php_error_in($res['body'])) return ['FAIL', $e];
    return ['PASS', ''];
}

function check_endpoint(array $res): array
{
    if ($res['error']) return ['FAIL', 'request error: ' . $res['error']];
    $json = json_decode($res['body'], true);
    $msg = is_array($json) ? ($json['error'] ?? $json['message'] ?? '') : '';
    if ($res['status'] !== 200) {
        return ['FAIL', "HTTP {$res['status']}" . ($msg ? ": $msg" : '') . ($res['location'] ? " -> {$res['location']}" : '')];
    }
    if (!is_array($json)) {
        $e = php_error_in($res['body']);
        return ['FAIL', 'response is not JSON' . ($e ? " ($e)" : '')];
    }
    if (($json['success'] ?? null) !== true) return ['FAIL', 'success is not true' . ($msg ? ": $msg" : '')];
    return ['PASS', ''];
}

/** hrefs (and JS location assignments) pointing to a .html file or to "#". */
function bad_links(string $html): array
{
    $found = [];
    preg_match_all('/<(a|button|link|area|form)\b[^>]*?\b(href|formaction|data-href|action)\s*=\s*("([^"]*)"|\'([^\']*)\')[^>]*>/i', $html, $m, PREG_SET_ORDER);
    foreach ($m as $tag) {
        $found[] = [strtolower($tag[1]), $tag[4] !== '' ? $tag[4] : ($tag[5] ?? ''), $tag[0]];
    }
    preg_match_all('/(?:location(?:\.href)?\s*=|window\.open\()\s*["\']([^"\']*)["\']/i', $html, $js, PREG_SET_ORDER);
    foreach ($js as $j) {
        $found[] = ['js', $j[1], $j[0]];
    }
    $out = [];
    foreach ($found as [$tag, $href, $src]) {
        $href = html_entity_decode(trim($href));
        $path = preg_replace('/[?#].*$/', '', $href);
        if ($href === '#' || preg_match('/\.html$/i', $path)) {
            $out[] = [$tag, $href];
        }
    }
    return $out;
}

/** Visible label of the element owning each bad href, for the report. */
function link_labels(string $html, string $href): array
{
    $labels = [];
    $q = preg_quote(htmlspecialchars($href, ENT_QUOTES), '/') . '|' . preg_quote($href, '/');
    if (preg_match_all('/<(a|button)\b[^>]*\bhref\s*=\s*["\'](?:' . $q . ')["\'][^>]*>(.*?)<\/\1>/is', $html, $m)) {
        foreach ($m[2] as $inner) {
            $t = trim(preg_replace('/\s+/', ' ', strip_tags($inner)));
            $labels[] = $t === '' ? '(icon only)' : $t;
        }
    }
    return $labels;
}

$results = [];
$links = [];

foreach ($roles as $role => $cfg) {
    $jar = tempnam(sys_get_temp_dir(), 'smoke');

    $login = http("$base/api/login-api.php?action=login", $jar, json_encode(['email' => $cfg['email'], 'password' => $cfg['password']]));
    $lj = json_decode($login['body'], true);
    if (($lj['success'] ?? false) !== true) {
        $results[] = [$role, 'api/login-api.php', 'FAIL', 'login failed: ' . ($lj['error'] ?? "HTTP {$login['status']}")];
        @unlink($jar);
        continue;
    }
    $results[] = [$role, 'api/login-api.php', 'PASS', 'role=' . ($lj['user']['role'] ?? '?') . ' redirect=' . ($lj['redirect_url'] ?? '?')];

    // The team login redirects to the old .html page, which must forward to the .php page
    $redirectPage = 'pages/' . ($lj['redirect_url'] ?? '');
    $res = http("$base/$redirectPage", $jar);
    $phpTarget = basename(preg_replace('/\.html$/', '.php', $redirectPage));
    $results[] = [$role, $redirectPage, ($res['status'] === 200 && str_contains($res['body'], $phpTarget)) ? 'PASS' : 'FAIL',
        "HTTP {$res['status']}, forwards to $phpTarget"];

    $pages = glob("$root/{$cfg['pages']}/*.php");
    sort($pages);
    foreach ($pages as $file) {
        $rel = $cfg['pages'] . '/' . basename($file);
        $res = http("$base/$rel", $jar);
        [$verdict, $reason] = check_page($res);
        $results[] = [$role, $rel, $verdict, $reason];
        if ($res['status'] === 200) {
            foreach (bad_links($res['body']) as [$tag, $href]) {
                $key = "$role|$rel|$href";
                if (!isset($links[$key])) {
                    $links[$key] = [$role, $rel, $tag, $href, 0, link_labels($res['body'], $href)];
                }
                $links[$key][4]++;
            }
        }
    }

    // Endpoints: files that answer GET. Shared include files (no role check of their
    // own, 404 when hit directly) are not endpoints and are skipped.
    $jobId = null;
    if ($role === 'mechanic') {
        $jobs = json_decode(http("$base/api/mechanic/jobs.php", $jar)['body'], true);
        $list = $jobs['data']['jobs'] ?? $jobs['data'] ?? [];
        if (is_array($list) && $list) {
            $first = reset($list);
            $jobId = $first['id'] ?? $first['job_id'] ?? null;
        }
    }
    $endpoints = glob("$root/{$cfg['api']}/*.php");
    sort($endpoints);
    foreach ($endpoints as $file) {
        $rel = $cfg['api'] . '/' . basename($file);
        $src = file_get_contents($file);
        if (!str_contains($src, 'require_api_role')) {
            continue; // shared data include, not an endpoint
        }
        if (preg_match('/require_method\(\[([^\]]*)\]\)/', $src, $m) && !str_contains($m[1], "'GET'")) {
            continue; // no GET handler
        }
        $urls = [$rel];
        if (in_array($rel, $needsJobId, true)) {
            $urls[] = $jobId ? "$rel?job_id=$jobId" : "$rel?job_id=(none found)";
        }
        foreach ($urls as $u) {
            if (str_contains($u, '(none found)')) {
                $results[] = [$role, $u, 'FAIL', 'no assigned job id available to test with'];
                continue;
            }
            $res = http("$base/$u", $jar);
            if ($u === $rel && in_array($rel, $needsJobId, true)) {
                // job_id is required: the bare call must be a clean JSON 404, not a crash
                $json = json_decode($res['body'], true);
                $ok = $res['status'] === 404 && is_array($json) && ($json['success'] ?? null) === false;
                $results[] = [$role, $u, $ok ? 'PASS' : 'FAIL', ($ok ? 'expected ' : 'unexpected ') . "HTTP {$res['status']} without job_id"];
                continue;
            }
            [$verdict, $reason] = check_endpoint($res);
            $results[] = [$role, $u, $verdict, $reason];
        }
    }

    @unlink($jar);
}

// ---- Report ----
$w = [8, 0, 6];
foreach ($results as $r) $w[1] = max($w[1], strlen($r[1]));
$line = fn($a, $b, $c, $d) => str_pad($a, $w[0]) . ' | ' . str_pad($b, $w[1]) . ' | ' . str_pad($c, $w[2]) . ' | ' . $d . PHP_EOL;

echo PHP_EOL, "SMOKE TEST  base=$base  started=$startedAt", PHP_EOL, PHP_EOL;
echo $line('ROLE', 'PAGE / ENDPOINT', 'RESULT', 'REASON');
echo str_repeat('-', $w[0] + $w[1] + $w[2] + 40), PHP_EOL;
foreach ($results as $r) echo $line(...$r);
$fails = count(array_filter($results, fn($r) => $r[2] === 'FAIL'));
echo PHP_EOL, count($results), ' checks, ', count($results) - $fails, ' passed, ', $fails, ' failed', PHP_EOL;

echo PHP_EOL, 'LINKS TO .html OR "#"', PHP_EOL, str_repeat('-', 60), PHP_EOL;
if (!$links) echo '(none)', PHP_EOL;
foreach ($links as [$role, $page, $tag, $href, $count, $labels]) {
    $labels = array_unique($labels);
    echo str_pad($role, 8), ' | ', str_pad($page, 48), ' | <', $tag, '> ', $href,
        $count > 1 ? " (x$count)" : '', $labels ? '  [' . implode(', ', array_slice($labels, 0, 6)) . (count($labels) > 6 ? ', ...' : '') . ']' : '', PHP_EOL;
}

echo PHP_EOL, 'NEW SERVER LOG ENTRIES DURING RUN', PHP_EOL, str_repeat('-', 60), PHP_EOL;
foreach ($logOffsets as $log => $offset) {
    if (!is_file($log)) {
        echo "$log: (file does not exist)", PHP_EOL;
        continue;
    }
    clearstatcache(true, $log);
    $new = (string) file_get_contents($log, false, null, $offset);
    $new = trim($new);
    echo "$log: ", $new === '' ? '(no new entries)' : PHP_EOL . $new, PHP_EOL;
}

exit($fails ? 1 : 0);
