<?php
/**
 * FineCalcTest.php — standalone assert test for the overdue-fine formula.
 *
 * Replicates the pure calculation in lms-backend/api/issues.php:67-68
 *   $days = (int)ceil((strtotime($return_date) - strtotime($due_date)) / 86400);
 *   $fine = $days > 0 ? $days * 10 : 0;   // 10 baht/day overdue
 *
 * Deterministic: uses fixed date strings, no DB, no clock dependency.
 * Run:  /Applications/XAMPP/xamppfiles/bin/php lms-backend/tests/FineCalcTest.php
 *
 * NOTE: this file re-implements the same expression as the source so the test
 * documents & locks the expected behavior. It does NOT import source (source
 * couples the formula inside issues_return()); this mirror must stay identical.
 */

// Mirror of source lines 67-68.
function calc_fine(string $return_date, string $due_date): int {
    $days = (int)ceil((strtotime($return_date) - strtotime($due_date)) / 86400);
    return $days > 0 ? $days * 10 : 0;
}

$RATE = 10; // baht per overdue day, per issues.php:50 comment + line 68

$cases = [
    // name, return_date, due_date, expected_fine
    ['คืนตรงกำหนด (days = 0) -> fine 0',        '2026-07-06', '2026-07-06', 0],
    ['คืนก่อนกำหนด (days ติดลบ) -> fine 0',      '2026-07-01', '2026-07-06', 0],
    ['เกิน 1 วัน -> fine 10',                     '2026-07-07', '2026-07-06', 10],
    ['เกินหลายวัน (5 วัน) -> fine 50',            '2026-07-11', '2026-07-06', 50],
];

$pass = 0;
$fail = 0;
foreach ($cases as [$name, $ret, $due, $expected]) {
    $got = calc_fine($ret, $due);
    $ok  = ($got === $expected);
    printf("[%s] %s | ret=%s due=%s expected=%d got=%d\n",
        $ok ? 'PASS' : 'FAIL', $name, $ret, $due, $expected, $got);
    $ok ? $pass++ : $fail++;
}

echo "----------------------------------------\n";
printf("Total: %d | Pass: %d | Fail: %d\n", count($cases), $pass, $fail);
exit($fail === 0 ? 0 : 1);
