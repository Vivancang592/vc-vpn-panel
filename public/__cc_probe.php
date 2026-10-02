<?php
// TEMP QA — concurrency probe (xoá sau test).
if (($_GET['s'] ?? '') === '1') {
    sleep(5);
    echo 'slow-done';
} else {
    echo 'fast';
}
