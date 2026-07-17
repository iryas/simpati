<?php
// ============================================================
//  Migrasi 012 — Setting nama profile isolir Mikrotik
// ============================================================

db_query(
    "INSERT INTO app_settings (setting_key, setting_val)
     VALUES ('mikrotik_profile_isolir', 'profile-Isolir2')
     ON DUPLICATE KEY UPDATE setting_val = setting_val"
);
