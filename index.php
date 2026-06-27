<?php
// ============================================================
//  KAHFINET - index.php (Entry Point → Dashboard)
// ============================================================
require_once __DIR__ . '/system/init.php';
auth_check();
require_once __DIR__ . '/modules/dashboard/views.php';
