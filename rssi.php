<?php

$hostname = $_GET['host'];
$community = 'hamwan';
$oidrssi   = '1.3.6.1.4.1.14988.1.1.1.1.1.4.2';
$oidrssiac = '1.3.6.1.4.1.14988.1.1.1.1.1.4.3';

$output = array(
    'host' => $hostname,
    'rssi' => NULL,
);

function snmpint($snmpstr) {
    return (int) explode(' ', $snmpstr)[1];
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

// try each oid
foreach (array($oidrssiac, $oidrssi) as $oid) {
    $rssi = snmpget($hostname, $community, $oid);
    if ($rssi) {
        // smoothing
        $avg[0] = snmpint($rssi);
        for ($i=1; $i<3; $i++) {
            usleep(200000);
            $avg[$i] = snmpint(snmpget($hostname, $community, $oid));
        }
        $output['rssi'] = calculate_median($avg);
        break;
    }
}

header('Content-Type: application/json');
echo json_encode($output);

