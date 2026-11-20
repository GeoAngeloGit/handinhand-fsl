<?php
/**
 * ============================================================================
 * BACKEND INTEGRATION GUIDE: db.php
 * ============================================================================
 * - Database Name : `handinhand_fsl` (import `handinhand_fsl.sql` in phpMyAdmin)
 * - Charset       : `utf8mb4` (required for Albay barangay names with ñ/accents)
 * - Usage         : Included via `require_once 'db.php';` across all pages.
 *                   Exposes `$conn` (MySQLi instance) or `null` if DB is offline.
 * - Team Note     : Update `$user` / `$pass` only if your local MySQL differs.
 * ============================================================================
 */

$host = 'localhost';$db   = 'handinhand_fsl';
$user = 'root';$pass = ''; // Default XAMPP MySQL password is blank

$conn = null;

try {
    $conn = new mysqli($host, $user,$pass, $db);$conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Keeps pages from crashing with a fatal error if the DB isn't imported yet
    $conn = null;
}
?>