<?php
// Hardened drop-in replacement for original HamWAN/aim rssi.php
// - reports errors as JSON instead of dying silently
// - works regardless of net-snmp "quick print" settings
// - discovers the wireless interface index instead of hard-coding .2/.3
// - add ?debug=1 to see everything it tried

header('Content-Type: application/json');

$community = getenv('AIM_COMMUNITY') ?: 'hamwan';   // change if your network uses another community
$timeout_us = 500000;                                // 0.5 s per request
$retries    = 1;

$out = ['host' => $_GET['host'] ?? null, 'rssi' => null];
$debug = isset($_GET['debug']);
$log = [];

function fail($msg, $code = 200) {
    global $out, $log, $debug;
    $out['error'] = $msg;
    if ($debug) $out['debug'] = $log;
    http_response_code($code);
    echo json_encode($out);
    exit;
}

// tolerate what phones/URL fields add: spaces, "http://", trailing "/", capitals
$host = strtolower(trim((string)$out['host']));
$host = preg_replace('#^[a-z]+://#', '', $host);
$host = rtrim(explode('/', $host)[0], '.');
$out['host'] = $host;
if ($host === '' || !preg_match('/^[A-Za-z0-9.\-:]+$/', $host)) {
    fail('missing or invalid host parameter', 400);
}

if (!extension_loaded('snmp')) {
    fail('PHP snmp extension is not loaded (install php-snmp and restart the web server / php-fpm)', 500);
}

// Return plain values ("-65") no matter how net-snmp is configured
snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
snmp_set_quick_print(true);

// mtxrWlStatStrength (station mode), indexed by ifIndex
$stat_base = '1.3.6.1.4.1.14988.1.1.1.1.1.4';
// mtxrWlRtabStrength (registration table, AP side), indexed by mac+ifIndex
$rtab_base = '1.3.6.1.4.1.14988.1.1.1.2.1.3';

function walk($host, $community, $oid) {
    global $timeout_us, $retries, $log;
    $r = @snmprealwalk($host, $community, $oid, $timeout_us, $retries);
    $log[] = ['walk' => $oid, 'result' => $r === false ? (error_get_last()['message'] ?? 'no response') : $r];
    if (!is_array($r)) return [];
    // keep only non-zero integers
    return array_filter(array_map('intval', $r), fn($v) => $v !== 0);
}

function sample($host, $community, $oid) {
    global $timeout_us, $retries;
    $vals = [];
    for ($i = 0; $i < 3; $i++) {
        if ($i) usleep(150000);
        $v = @snmpget($host, $community, $oid, $timeout_us, $retries);
        if ($v !== false && (int)$v !== 0) $vals[] = (int)$v;
    }
    if (!$vals) return null;
    sort($vals);
    return $vals[intdiv(count($vals), 2)];  // median
}

$found = walk($host, $community, $stat_base);
if (!$found) $found = walk($host, $community, $rtab_base);

if (!$found) {
    fail('no wireless signal OIDs answered: check SNMP is enabled on the radio, the community name, ' .
         'that this server\'s IP is allowed in /snmp community, and that the radio uses the legacy ' .
         '"wireless" package (RouterOS v7 "wifi"/wifiwave2 interfaces do not populate these OIDs)');
}

// use the strongest (least negative) interface/peer
arsort($found);
$oid = array_key_first($found);
$out['oid']  = $oid;
$out['rssi'] = sample($host, $community, $oid) ?? reset($found);
if ($debug) $out['debug'] = $log;

echo json_encode($out);
