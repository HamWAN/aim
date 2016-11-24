<?php
snmp_set_quick_print(TRUE);

$hostname = $_GET['host'];
//$hostname = '44.24.241.126';
$community = 'hamwan';
$oidrssi   = '1.3.6.1.4.1.14988.1.1.1.1.1.4.2';
$oidrssiac = '1.3.6.1.4.1.14988.1.1.1.1.1.4.3';
$oidregtable = '1.3.6.1.4.1.14988.1.1.1.2.1';
$oidneighbormac = 'iso.3.6.1.4.1.14988.1.1.11.1.1.3';
$oidneighborid  = 'iso.3.6.1.4.1.14988.1.1.11.1.1.6.';
//$output = array(
//    'host' => $hostname,
//    'rssi' => NULL,
//);

function snmpint($snmpstr) {
    return (int) $snmpstr;
}

function snmpstr($snmpstr) {
    return (string) trim($snmpstr, '"');
}

function calculate_median($arr) {
    $count = count($arr); //total numbers in array
    $middleval = floor(($count-1)/2); // find the middle value, or the lowest middle value
    if($count % 2) { // odd number, middle is the median
        $median = $arr[$middleval];
    } else { // even number, calculate avg of 2 medians
        $low = $arr[$middleval];
        $high = $arr[$middleval+1];
        $median = (($low+$high)/2);
    }
    return $median;
}

function get_neighbor_identity($neighbors, $mac) {
    global $hostname, $community, $oidneighborid;
    foreach ($neighbors as $oid => $val) {
        if ($mac == $val) {
            $index = end(explode('.',$oid));
            $identity = snmpget($hostname, $community, $oidneighborid . $index);
            return snmpstr($identity);
        }
    }
}

$macs = snmpwalk($hostname, $community, $oidregtable.'.1');
$txss0 = snmpwalk($hostname, $community, $oidregtable.'.13');
$rxss0 = snmpwalk($hostname, $community, $oidregtable.'.14');
$txss1 = snmpwalk($hostname, $community, $oidregtable.'.15');
$rxss1 = snmpwalk($hostname, $community, $oidregtable.'.16');
$neighbors = snmprealwalk($hostname, $community, $oidneighbormac);
foreach ($macs as $i => $mac) {
    $output[] = [
        "identity" => get_neighbor_identity($neighbors, $mac),
        "rx-signal-strength-ch0" => snmpint($rxss0[$i]),
        "rx-signal-strength-ch1" => snmpint($rxss1[$i]),
        "tx-signal-strength-ch0" => snmpint($txss0[$i]),
        "tx-signal-strength-ch1" => snmpint($txss1[$i])
    ];
}

header('Content-Type: application/json');
echo json_encode($output);

