<?php
// api.php
header('Content-Type: application/json');
error_reporting(0); 
ini_set('display_errors', 0);

require 'db.php';
session_start();

// --- 0. Stay Signed In (Persistent Cookie Logic) ---
if (!isset($_SESSION['user_id']) && isset($_COOKIE['meditrack_user'])) {
    $uid = (int)$_COOKIE['meditrack_user'];
    $res = $db->querySingle("SELECT * FROM users WHERE id = $uid", true);
    if ($res) {
        $_SESSION['user_id'] = $res['id'];
        $_SESSION['role'] = $res['role'];
        $_SESSION['share_code'] = $res['share_code'] ?: 'HOUSE123';
    }
}

// --- 1. Database Setup ---
function ensureColumn($db, $table, $column) {
    $res = @$db->query("PRAGMA table_info($table)");
    $exists = false;
    if ($res) {
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === $column) $exists = true;
        }
    }
    if (!$exists) @$db->exec("ALTER TABLE $table ADD COLUMN $column TEXT");
}
ensureColumn($db, 'my_meds', 'strength');
ensureColumn($db, 'my_meds', 'share_code');
ensureColumn($db, 'inventory', 'share_code');
ensureColumn($db, 'inventory', 'code');
ensureColumn($db, 'users', 'share_code');
ensureColumn($db, 'family_members', 'is_default');
ensureColumn($db, 'locations', 'is_default');
ensureColumn($db, 'master_meds', 'prefix'); // New Column for 2-Letter Code

// Auto-fill existing master meds that don't have a prefix yet with 2 random uppercase letters
@$db->exec("UPDATE master_meds SET prefix = char(abs(random()) % 26 + 65) || char(abs(random()) % 26 + 65) WHERE prefix IS NULL OR prefix = ''");

$SC = $_SESSION['share_code'] ?? 'DEFAULT123';
$action = $_POST['action'] ?? '';

// --- 2. Auth ---
if ($action === 'login') {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->bindValue(':u', $_POST['username']);
    $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($res && password_verify($_POST['password'], $res['password'])) {
        $_SESSION['user_id'] = $res['id'];
        $_SESSION['role'] = $res['role'];
        $_SESSION['share_code'] = $res['share_code'] ?: 'HOUSE123';
        
        if (!empty($_POST['remember']) && $_POST['remember'] === 'true') {
            setcookie('meditrack_user', $res['id'], time() + (86400 * 30), "/"); 
        }
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    }
    exit;
}

if ($action === 'logout') { 
    session_destroy(); 
    setcookie('meditrack_user', '', time() - 3600, "/"); 
    echo json_encode(['status' => 'success']); 
    exit; 
}

if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error']); exit; }

function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }

// --- 3. Actions ---
switch ($action) {
    // DASHBOARD
    case 'get_dashboard_stats':
        $today = date('Y-m-d'); $soon = date('Y-m-d', strtotime('+30 days'));
        $exp = $db->querySingle("SELECT count(*) FROM inventory WHERE share_code='$SC' AND status='in_stock' AND expiry_date < '$today'");
        $soon = $db->querySingle("SELECT count(*) FROM inventory WHERE share_code='$SC' AND status='in_stock' AND expiry_date BETWEEN '$today' AND '$soon'");
        echo json_encode(['expired' => $exp ?: 0, 'expiring_soon' => $soon ?: 0, 'low_stock' => 0]);
        break;

    case 'get_needs_attention':
        $today = date('Y-m-d');
        $soon = date('Y-m-d', strtotime('+30 days'));
        $list = [];
        
        // 1. Reorder
        $resMeds = $db->query("SELECT * FROM my_meds WHERE share_code='$SC' AND status='active'");
        while($row = $resMeds->fetchArray(SQLITE3_ASSOC)) {
            $name = $row['name']; $owner = $row['owner'];
            $count = $db->querySingle("SELECT count(*) FROM inventory WHERE share_code='$SC' AND (status='in_stock' OR status='in_use') AND name='$name' AND owner='$owner'") ?: 0;
            if ($count < 1) {
                $list[] = [
                    'id' => $row['id'], 
                    'name' => $name . ' ' . $row['strength'] . ' (' . $owner . ')',
                    'code' => null, 
                    'reason' => 'Reorder',
                    'color' => '#d9534f', 
                    'type' => 'reorder'
                ];
            }
        }
        
        // 2. Expired
        $resExp = $db->query("SELECT * FROM inventory WHERE share_code='$SC' AND (status='in_stock' OR status='in_use') AND expiry_date < '$today'");
        while($r = $resExp->fetchArray(SQLITE3_ASSOC)) { 
            $r['reason'] = 'Expired - Discard'; 
            $r['color'] = '#f0ad4e'; 
            $r['type'] = 'expired';
            $list[] = $r; 
        }
        
        // 3. Expiring Soon
        $resSoon = $db->query("SELECT * FROM inventory WHERE share_code='$SC' AND (status='in_stock' OR status='in_use') AND expiry_date BETWEEN '$today' AND '$soon'");
        while($r = $resSoon->fetchArray(SQLITE3_ASSOC)) { 
            $r['reason'] = 'Expiring Soon'; 
            $r['color'] = '#f0ad4e'; 
            $r['type'] = 'soon';
            $list[] = $r; 
        }
        
        echo json_encode($list);
        break;

    // INVENTORY
    case 'get_inventory':
        $res = $db->query("SELECT * FROM inventory WHERE share_code='$SC' AND status != 'trash' ORDER BY name ASC");
        $data = []; while($r = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $r;
        echo json_encode($data);
        break;
        
    case 'add_inventory':
        $name = $_POST['name'];
        
        // Fetch the 2-letter prefix from master_meds
        $stmtP = $db->prepare("SELECT prefix FROM master_meds WHERE name = :n");
        $stmtP->bindValue(':n', $name);
        $resP = $stmtP->execute()->fetchArray(SQLITE3_ASSOC);
        
        // If it exists in master list, use it. Otherwise derive a 2-letter fallback.
        $prefix = ($resP && !empty($resP['prefix'])) ? $resP['prefix'] : strtoupper(substr($name, 0, 2));
        if (strlen($prefix) < 2) $prefix = str_pad($prefix, 2, 'X');
        
        // Generate the combined Code (e.g., AB-12345)
        $code = $prefix . '-' . (string)random_int(10000, 99999);
        
        $stmt = $db->prepare("INSERT INTO inventory (code, name, strength, owner, location, expiry_date, status, share_code) VALUES (:c, :n, :s, :o, :l, :e, 'in_stock', :sc)");
        $stmt->bindValue(':c', $code);
        $stmt->bindValue(':n', $name);
        $stmt->bindValue(':s', $_POST['strength'] ?? '');
        $stmt->bindValue(':o', $_POST['owner']);
        $stmt->bindValue(':l', $_POST['location']);
        $stmt->bindValue(':e', $_POST['expiry']);
        $stmt->bindValue(':sc', $SC);
        $stmt->execute();
        
        $stmtReset = $db->prepare("UPDATE my_meds SET status='active' WHERE name=:n AND owner=:o AND share_code=:sc");
        $stmtReset->bindValue(':n', $_POST['name']);
        $stmtReset->bindValue(':o', $_POST['owner']);
        $stmtReset->bindValue(':sc', $SC);
        $stmtReset->execute();
        
        echo json_encode(['status' => 'success']);
        break;

    case 'update_status':
        if (isset($_POST['location']) && trim($_POST['location']) !== '') {
            $stmt = $db->prepare("UPDATE inventory SET status = :st, location = :loc WHERE id = :id AND share_code = :sc");
            $stmt->bindValue(':loc', trim($_POST['location']));
        } else {
            $stmt = $db->prepare("UPDATE inventory SET status = :st WHERE id = :id AND share_code = :sc");
        }
        $stmt->bindValue(':st', $_POST['status']); 
        $stmt->bindValue(':id', $_POST['id']); 
        $stmt->bindValue(':sc', $SC);
        $stmt->execute(); 
        echo json_encode(['status' => 'success']);
        break;

    // MY MEDS
    case 'get_mymeds':
        $active = []; $ordered = [];
        $res = $db->query("SELECT * FROM my_meds WHERE share_code='$SC' ORDER BY owner ASC, name ASC");
        while($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $name = $row['name']; $owner = $row['owner'];
            $row['stock_count'] = $db->querySingle("SELECT count(*) FROM inventory WHERE share_code='$SC' AND (status='in_stock' OR status='in_use') AND name='$name' AND owner='$owner'") ?: 0;
            if($row['status'] === 'ordered') $ordered[] = $row; else $active[] = $row;
        }
        echo json_encode(['active' => $active, 'ordered' => $ordered]);
        break;

    case 'add_mymed':
        $stmt = $db->prepare("INSERT INTO my_meds (name, owner, strength, share_code, status) VALUES (:n, :o, :s, :sc, 'active')");
        $stmt->bindValue(':n', $_POST['name']); $stmt->bindValue(':o', $_POST['owner']);
        $stmt->bindValue(':s', $_POST['strength']); $stmt->bindValue(':sc', $SC);
        $stmt->execute(); echo json_encode(['status' => 'success']);
        break;
        
    case 'delete_mymed':
        $stmt = $db->prepare("DELETE FROM my_meds WHERE id = :id AND share_code = :sc");
        $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC);
        $stmt->execute(); echo json_encode(['status' => 'success']); 
        break;
        
    case 'mark_mymed_ordered':
        $stmt = $db->prepare("UPDATE my_meds SET status = 'ordered' WHERE id = :id AND share_code = :sc");
        $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC);
        $stmt->execute(); echo json_encode(['status' => 'success']); 
        break;

    // MASTER LIST & SUGGESTIONS
    case 'get_master':
        $letter = $_POST['letter'] ?? 'A';
        $res = $db->query("SELECT * FROM master_meds WHERE name LIKE '$letter%' ORDER BY name ASC");
        $data = []; while($r = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $r;
        echo json_encode($data);
        break;

    case 'search_master':
        $term = $_POST['term'] ?? '';
        $stmt = $db->prepare("SELECT name FROM master_meds WHERE name LIKE :t ORDER BY name ASC LIMIT 10");
        $stmt->bindValue(':t', '%' . $term . '%');
        $res = $stmt->execute();
        $data = []; while($r = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $r['name'];
        echo json_encode($data);
        break;

    case 'add_master':
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $prefix = $chars[random_int(0,25)] . $chars[random_int(0,25)];
        
        // Ensure 2-letter prefix is unique in the database
        while($db->querySingle("SELECT count(*) FROM master_meds WHERE prefix='$prefix'") > 0) {
            $prefix = $chars[random_int(0,25)] . $chars[random_int(0,25)];
        }
        
        $stmt = $db->prepare("INSERT INTO master_meds (name, prefix) VALUES (:n, :p)");
        $stmt->bindValue(':n', trim($_POST['name']));
        $stmt->bindValue(':p', $prefix);
        $stmt->execute(); 
        echo json_encode(['status'=>'success']);
        break;

    case 'delete_master':
        $stmt = $db->prepare("DELETE FROM master_meds WHERE id = :id");
        $stmt->bindValue(':id', $_POST['id']);
        $stmt->execute(); echo json_encode(['status'=>'success']);
        break;

    // LOCATIONS & FAMILY
    case 'get_family':
        $res = $db->query("SELECT * FROM family_members WHERE share_code='$SC' ORDER BY is_default DESC, name ASC");
        $data = []; while($r = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $r;
        echo json_encode($data); break;
        
    case 'add_family':
        $stmt = $db->prepare("INSERT INTO family_members (name, share_code, is_default) VALUES (:n, :sc, 0)");
        $stmt->bindValue(':n', trim($_POST['name'])); $stmt->bindValue(':sc', $SC);
        $stmt->execute(); echo json_encode(['status'=>'success']); break;
        
    case 'edit_family':
        $newName = trim($_POST['name']);
        $oldName = trim($_POST['old_name']);
        
        $stmt = $db->prepare("UPDATE family_members SET name = :n WHERE id = :id AND share_code = :sc");
        $stmt->bindValue(':n', $newName); $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC);
        $stmt->execute();
        
        if($oldName && $newName) {
            $s1 = $db->prepare("UPDATE inventory SET owner = :nn WHERE owner = :on AND share_code = :sc");
            $s1->bindValue(':nn', $newName); $s1->bindValue(':on', $oldName); $s1->bindValue(':sc', $SC); $s1->execute();
            $s2 = $db->prepare("UPDATE my_meds SET owner = :nn WHERE owner = :on AND share_code = :sc");
            $s2->bindValue(':nn', $newName); $s2->bindValue(':on', $oldName); $s2->bindValue(':sc', $SC); $s2->execute();
        }
        echo json_encode(['status'=>'success']); break;

    case 'delete_family':
        $stmt = $db->prepare("DELETE FROM family_members WHERE id = :id AND share_code = :sc");
        $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC);
        $stmt->execute(); echo json_encode(['status'=>'success']); break;

    case 'set_default_family':
        @$db->exec("BEGIN");
        $s1 = $db->prepare("UPDATE family_members SET is_default = 0 WHERE share_code = :sc"); $s1->bindValue(':sc', $SC); $s1->execute();
        $s2 = $db->prepare("UPDATE family_members SET is_default = 1 WHERE id = :id AND share_code = :sc"); $s2->bindValue(':id', $_POST['id']); $s2->bindValue(':sc', $SC); $s2->execute();
        @$db->exec("COMMIT");
        echo json_encode(['status'=>'success']); break;

    case 'get_locations':
        $res = $db->query("SELECT * FROM locations WHERE share_code='$SC' ORDER BY is_default DESC, name ASC");
        $data = []; while($r = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $r;
        echo json_encode($data); break;
        
    case 'add_location':
        $stmt = $db->prepare("INSERT INTO locations (name, share_code, is_default) VALUES (:n, :sc, 0)");
        $stmt->bindValue(':n', trim($_POST['name'])); $stmt->bindValue(':sc', $SC);
        $stmt->execute(); echo json_encode(['status'=>'success']); break;
        
    case 'delete_location':
        $stmt = $db->prepare("DELETE FROM locations WHERE id = :id AND share_code = :sc");
        $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC);
        $stmt->execute(); echo json_encode(['status'=>'success']); break;

    case 'set_default_location':
        @$db->exec("BEGIN");
        $s1 = $db->prepare("UPDATE locations SET is_default = 0 WHERE share_code = :sc"); $s1->bindValue(':sc', $SC); $s1->execute();
        $s2 = $db->prepare("UPDATE locations SET is_default = 1 WHERE id = :id AND share_code = :sc"); $s2->bindValue(':id', $_POST['id']); $s2->bindValue(':sc', $SC); $s2->execute();
        @$db->exec("COMMIT");
        echo json_encode(['status'=>'success']); break;

    // SHARE CODE SYSTEM
    case 'get_share_code':
        echo json_encode(['code' => $SC]); break;
        
    case 'join_share_code':
        $code = strtoupper(trim($_POST['code']));
        if(strlen($code) !== 10) { echo json_encode(['status'=>'error', 'message'=>'Invalid code length']); exit; }
        $stmt = $db->prepare("UPDATE users SET share_code = :code WHERE id = :id");
        $stmt->bindValue(':code', $code); $stmt->bindValue(':id', $_SESSION['user_id']); $stmt->execute();
        $_SESSION['share_code'] = $code; 
        echo json_encode(['status' => 'success']); 
        break;
        
    case 'regenerate_share_code':
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $new_code = '';
        for ($i = 0; $i < 10; $i++) { $new_code .= $chars[random_int(0, strlen($chars) - 1)]; }
        
        @$db->exec("BEGIN");
        $stmt = $db->prepare("UPDATE users SET share_code = :nc WHERE id = :id");
        $stmt->bindValue(':nc', $new_code); $stmt->bindValue(':id', $_SESSION['user_id']); $stmt->execute();
        
        $tables = ['inventory', 'my_meds', 'family_members', 'locations'];
        foreach($tables as $t) {
            $s = $db->prepare("UPDATE $t SET share_code = :nc WHERE share_code = :oc");
            $s->bindValue(':nc', $new_code); $s->bindValue(':oc', $SC); $s->execute();
        }
        @$db->exec("COMMIT");
        
        $_SESSION['share_code'] = $new_code;
        echo json_encode(['status'=>'success', 'code'=>$new_code]); break;

    // SYSTEM - USER MANAGEMENT
    case 'get_users':
        if(!isAdmin()) exit;
        $res = $db->query("SELECT id, username, role FROM users");
        $data = []; while($r = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $r;
        echo json_encode($data); break;

    case 'add_user':
        if(!isAdmin()) exit;
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, role, share_code) VALUES (:u, :p, :r, :sc)");
        $stmt->bindValue(':u', $_POST['username']); $stmt->bindValue(':p', $pass); 
        $stmt->bindValue(':r', $_POST['role']); $stmt->bindValue(':sc', $SC);
        $stmt->execute(); echo json_encode(['status'=>'success']); break;

    case 'delete_user':
        if(!isAdmin()) exit;
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindValue(':id', $_POST['id']); $stmt->execute(); echo json_encode(['status'=>'success']); break;

    case 'admin_reset_password':
        if(!isAdmin()) exit;
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = :p WHERE id = :id");
        $stmt->bindValue(':p', $pass); $stmt->bindValue(':id', $_POST['id']);
        $stmt->execute(); echo json_encode(['status'=>'success']); break;

    case 'update_my_password':
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = :p WHERE id = :id");
        $stmt->bindValue(':p', $pass); $stmt->bindValue(':id', $_SESSION['user_id']);
        $stmt->execute(); echo json_encode(['status'=>'success']); break;

    // SYSTEM - DATA MANAGEMENT
    case 'backup_json':
        $tables = ['locations', 'family_members', 'inventory', 'my_meds']; $backup = [];
        foreach ($tables as $t) { 
            $res = $db->query("SELECT * FROM $t WHERE share_code='$SC'"); 
            $backup[$t] = []; while ($row = $res->fetchArray(SQLITE3_ASSOC)) $backup[$t][] = $row; 
        }
        echo json_encode($backup); break;

    case 'export_csv':
        $csv = "Code,Name,Strength,Owner,Location,Expiry,Status\n";
        $res = $db->query("SELECT * FROM inventory WHERE share_code='$SC'");
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $csv .= "{$r['code']},\"{$r['name']}\",\"{$r['strength']}\",\"{$r['owner']}\",\"{$r['location']}\",{$r['expiry_date']},{$r['status']}\n";
        }
        echo json_encode(['csv' => $csv]); break;

    case 'restore_json':
        if (!isset($_FILES['file'])) { echo json_encode(['status'=>'error']); exit; }
        $json = file_get_contents($_FILES['file']['tmp_name']); $data = json_decode($json, true); 
        if(!$data) { echo json_encode(['status'=>'error']); exit; }
        foreach ($data as $table => $rows) {
            @$db->exec("DELETE FROM $table WHERE share_code = '$SC'");
            foreach ($rows as $row) {
                $row['share_code'] = $SC; unset($row['id']);
                $cols = implode(",", array_keys($row)); $pl = implode(",", array_fill(0, count($row), "?"));
                $stmt = $db->prepare("INSERT INTO $table ($cols) VALUES ($pl)");
                $i = 1; foreach ($row as $v) $stmt->bindValue($i++, $v); $stmt->execute();
            }
        }
        echo json_encode(['status' => 'success']); break;
}
?>
