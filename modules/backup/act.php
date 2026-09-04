<?php
// ============================================================
//  KAHFINET - Modul Backup Database (Action Handler)
// ============================================================
require_once __DIR__ . '/../../system/init.php';
require_once __DIR__ . '/../../system/backup.php';
auth_check();
auth_role([ROLE_ADMIN]);

$action   = get('action') ?: post('action');
$back_url = BASE_URL . 'modules/backup/views.php';

switch ($action) {

    case 'download':
        if (!csrf_verify()) {
            flash('danger', 'Token tidak valid, coba lagi.');
            redirect($back_url);
        }

        $filename = 'backup-' . DB_NAME . '-' . date('Y-m-d-His') . '.sql';

        // Buang buffer output apa pun yang mungkin nyala, biar header
        // download nggak kecampur teks lain.
        while (ob_get_level() > 0) ob_end_clean();

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        backup_stream_sql();

        db_query(
            "INSERT INTO app_settings (setting_key, setting_val)
             VALUES ('backup_terakhir', ?)
             ON DUPLICATE KEY UPDATE setting_val = VALUES(setting_val), updated_at = NOW()",
            [date('Y-m-d H:i:s')]
        );
        exit;

    default:
        redirect($back_url);
}
