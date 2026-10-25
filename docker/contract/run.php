<?php
// Contract test: calls every function on the FiveDays web service once with the fivedays_ws token,
// through the web server, so a function missing from $services or a missing capability fails here
// instead of in registry-web. Cases live in cases/*.php (run in name order, sharing $ctx).
// Run in a one-off `app` container: php /contract/run.php [base-url] [host-header]

define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');

$base = $argv[1] ?? 'http://web:8081';
$host = $argv[2] ?? parse_url($CFG->wwwroot, PHP_URL_HOST) . (parse_url($CFG->wwwroot, PHP_URL_PORT) ? ':' . parse_url($CFG->wwwroot, PHP_URL_PORT) : '');

$token = $DB->get_field_sql(
    "SELECT t.token FROM {external_tokens} t
       JOIN {user} u ON u.id = t.userid
       JOIN {external_services} s ON s.id = t.externalserviceid
      WHERE u.username = 'fivedays_ws' AND s.shortname = 'fivedays' AND t.tokentype = ?",
    [EXTERNAL_TOKEN_PERMANENT], MUST_EXIST);

// Errors that mean the service or the role is wrong. A case can never allow them.
const ACCESS_ERRORS = ['accessexception', 'nopermissions', 'servicenotavailable', 'invalidtoken',
    'requireloginerror', 'webservicefunctionnotfound', 'forbiddenwsuser', 'usernotfullysetup'];

/** POSTs one REST call; returns the decoded JSON (an array with 'exception' on Moodle errors). */
function contract_call(string $function, array $params): array {
    global $base, $host, $token;

    $curl = curl_init("{$base}/webservice/rest/server.php");
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(
            ['wstoken' => $token, 'wsfunction' => $function, 'moodlewsrestformat' => 'json'] + $params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Host: {$host}"],
        CURLOPT_TIMEOUT => 120,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($body === false || $status !== 200) {
        return ['exception' => 'http', 'errorcode' => "http{$status}", 'message' => substr((string) $body, 0, 300)];
    }
    $decoded = json_decode($body, true);
    if ($decoded === null && trim($body) !== 'null') {
        return ['exception' => 'json', 'errorcode' => 'badjson', 'message' => substr($body, 0, 300)];
    }
    return is_array($decoded) ? $decoded : ['value' => $decoded];
}

// CONTRACT_ONLY=10-assignments.php runs the fixtures plus that file and skips the coverage check.
$only = getenv('CONTRACT_ONLY') ?: '';
$cases = [];
foreach (glob(__DIR__ . '/cases/*.php') as $file) {
    if ($only !== '' && basename($file) !== '00-fixtures.php' && basename($file) !== $only) {
        continue;
    }
    foreach (require($file) as $case) {
        $case['file'] = basename($file);
        $cases[] = $case;
    }
}

$servicefunctions = $DB->get_fieldset_sql(
    "SELECT f.functionname FROM {external_services_functions} f
       JOIN {external_services} s ON s.id = f.externalserviceid
      WHERE s.shortname = 'fivedays'");

$failed = 0;
$covered = [];
$ctx = new stdClass();
foreach ($cases as $case) {
    $function = $case['fn'];
    $covered[$function] = true;
    $allow = $case['allow'] ?? [];
    if (array_intersect($allow, ACCESS_ERRORS)) {
        throw new coding_exception("{$case['file']}: {$function} may not allow access errors");
    }
    if (!in_array($function, $servicefunctions, true)) {
        printf("FAIL  %-55s not on the FiveDays service\n", $function);
        $failed++;
        continue;
    }
    try {
        $params = isset($case['params']) ? ($case['params'])($ctx) : [];
    } catch (Throwable $e) {
        printf("FAIL  %-55s could not build params: %s\n", $function, $e->getMessage());
        $failed++;
        continue;
    }
    $result = contract_call($function, $params);
    if (isset($result['exception'])) {
        $code = $result['errorcode'] ?? $result['exception'];
        if (in_array($code, $allow, true)) {
            printf("ok    %-55s expected error %s\n", $function, $code);
        } else {
            printf("FAIL  %-55s %s: %s\n", $function, $code, $result['message'] ?? '');
            $failed++;
        }
        continue;
    }
    if (isset($case['check']) && ($problem = ($case['check'])($result, $ctx)) !== null) {
        printf("FAIL  %-55s %s\n", $function, $problem);
        $failed++;
        continue;
    }
    if (isset($case['save'])) {
        ($case['save'])($result, $ctx);
    }
    printf("ok    %s\n", $function);
}

foreach ($only === '' ? array_diff($servicefunctions, array_keys($covered)) : [] as $missing) {
    printf("FAIL  %-55s no contract case\n", $missing);
    $failed++;
}

// CONTRACT_CLEANUP=1 (bin/cutover on prod, bin/rehearse): delete the fixture courses, the CONTRACT category
// and the users the cases created, so nothing stays visible to students or lecturers.
if (getenv('CONTRACT_CLEANUP') === '1') {
    require_once(__DIR__ . '/cleanup-lib.php');
    $failed += contract_cleanup(
        [$ctx->courseid ?? 0, $ctx->sparecourseid ?? 0],
        array_merge([$ctx->lecturerid ?? 0, $ctx->studentid ?? 0], $ctx->extraids ?? []));
}

printf("\n%d functions, %d cases, %d failed\n", count($servicefunctions), count($cases), $failed);
exit($failed ? 1 : 0);
