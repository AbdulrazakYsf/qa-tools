<?php
require_once 'auth_session.php';

try {
    echo "Connecting to DB...\n";
    $db = get_db_auth();
    
    echo "Checking qa_tools table...\n";
    $checkTable = $db->query("SHOW TABLES LIKE 'qa_tools'")->fetchAll();
    if (count($checkTable) === 0) {
        echo "ERROR: Table 'qa_tools' does NOT exist!\n";
        exit;
    }
    echo "Table 'qa_tools' exists.\n";

    $count = $db->query("SELECT COUNT(*) FROM qa_tools")->fetchColumn();
    echo "Row count: $count\n";

    if ($count == 0) {
        echo "Table is EMPTY.\n";
    } else {
        echo "Rows found:\n";
        $rows = $db->query("SELECT id, code, name, visible_tester, api_enabled FROM qa_tools")->fetchAll(PDO::FETCH_ASSOC);
        print_r($rows);
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
