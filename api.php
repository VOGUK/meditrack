<?php
// api.php
require 'db.php';
session_start();
header('Content-Type: application/json');

// --- Security Helpers ---

// 1. CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'Security token invalid (CSRF). Please refresh the page.']);
        exit;
    }
}

// 2. Secure Share Code Generator
function generateSecureCode($length = 10) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}

function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function getShareCode() { return $_SESSION['share_code'] ?? 'DEFAULT123'; }

$SC = getShareCode();
$action = $_POST['action'] ?? '';

// --- Auth ---
if ($action === 'login') {
    $username = $_POST['username']; 
    $password = $_POST['password'];
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($res && password_verify($password, $res['password'])) {
        $_SESSION['user_id'] = $res['id']; 
        $_SESSION['role'] = $res['role']; 
        $_SESSION['username'] = $res['username'];
        
        if(empty($res['share_code'])){
            $newCode = generateSecureCode();
            $upd = $db->prepare("UPDATE users SET share_code = :sc WHERE id = :id");
            $upd->bindValue(':sc', $newCode);
            $upd->bindValue(':id', $res['id']);
            $upd->execute();
            
            $_SESSION['share_code'] = $newCode;
            
            // Seed defaults
            $s1 = $db->prepare("INSERT INTO locations (name, is_default, share_code) VALUES ('Medicine Cupboard', 1, :sc)");
            $s1->bindValue(':sc', $newCode); $s1->execute();
            
            $s2 = $db->prepare("INSERT INTO family_members (name, is_default, share_code) VALUES ('Me', 1, :sc)");
            $s2->bindValue(':sc', $newCode); $s2->execute();
        } else {
            $_SESSION['share_code'] = $res['share_code'];
        }

        echo json_encode(['status' => 'success']);
    } else { 
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']); 
    }
    exit;
}

if ($action === 'logout') { session_destroy(); echo json_encode(['status' => 'success']); exit; }
if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }

// --- Share Logic ---
if ($action === 'get_share_code') { 
    echo json_encode(['code' => $SC]); exit; 
}

if ($action === 'regenerate_share_code') {
    $newCode = generateSecureCode();
    $oldCode = $SC;

    // Transaction for safety
    $db->exec('BEGIN');
    try {
        $tables = ['inventory', 'my_meds', 'locations', 'family_members'];
        foreach($tables as $t){
            $stmt = $db->prepare("UPDATE $t SET share_code = :new WHERE share_code = :old");
            $stmt->bindValue(':new', $newCode);
            $stmt->bindValue(':old', $oldCode);
            $stmt->execute();
        }

        $stmt = $db->prepare("UPDATE users SET share_code = :new WHERE id = :id");
        $stmt->bindValue(':new', $newCode);
        $stmt->bindValue(':id', $_SESSION['user_id']);
        $stmt->execute();
        
        $db->exec('COMMIT');
        $_SESSION['share_code'] = $newCode;
        echo json_encode(['status' => 'success', 'code' => $newCode]); 
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        echo json_encode(['status' => 'error']);
    }
    exit;
}

if ($action === 'join_share_code') {
    $code = strtoupper(trim($_POST['code']));
    if(strlen($code) !== 10) { echo json_encode(['status'=>'error', 'message'=>'Invalid code length']); exit; }
    
    // Note: In a real app, you might check if the code exists in a specific "Households" table, 
    // but here we trust the code format and allow joining.
    
    $stmt = $db->prepare("UPDATE users SET share_code = :code WHERE id = :id");
    $stmt->bindValue(':code', $code);
    $stmt->bindValue(':id', $_SESSION['user_id']);
    $stmt->execute();
    
    $_SESSION['share_code'] = $code;
    echo json_encode(['status' => 'success']); exit;
}

// --- Dashboard ---
if ($action === 'get_dashboard_stats') {
    $today = date('Y-m-d');
    $soon = date('Y-m-d', strtotime('+30 days'));
    
    $s1 = $db->prepare("SELECT count(*) FROM inventory WHERE share_code=:sc AND status='in_stock' AND expiry_date < :today");
    $s1->bindValue(':sc', $SC); $s1->bindValue(':today', $today);
    $expired = $s1->execute()->fetchArray()[0];

    $s2 = $db->prepare("SELECT count(*) FROM inventory WHERE share_code=:sc AND status='in_stock' AND expiry_date >= :today AND expiry_date <= :soon");
    $s2->bindValue(':sc', $SC); $s2->bindValue(':today', $today); $s2->bindValue(':soon', $soon);
    $expiring = $s2->execute()->fetchArray()[0];

    $low_stock = 0;
    $s3 = $db->prepare("SELECT name FROM my_meds WHERE share_code=:sc AND status='active'");
    $s3->bindValue(':sc', $SC);
    $res = $s3->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $s4 = $db->prepare("SELECT count(*) FROM inventory WHERE share_code=:sc AND status='in_stock' AND name=:name");
        $s4->bindValue(':sc', $SC); $s4->bindValue(':name', $row['name']);
        if ($s4->execute()->fetchArray()[0] < 1) $low_stock++;
    }
    echo json_encode(['expired' => $expired, 'expiring_soon' => $expiring, 'low_stock' => $low_stock]); exit;
}

if ($action === 'get_needs_attention') {
    $today = date('Y-m-d'); $soon = date('Y-m-d', strtotime('+30 days'));
    $list = [];
    
    $s1 = $db->prepare("SELECT *, 'Expired' as reason, 'red' as color FROM inventory WHERE share_code=:sc AND status='in_stock' AND expiry_date < :today");
    $s1->bindValue(':sc', $SC); $s1->bindValue(':today', $today);
    $res = $s1->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) $list[] = $row;
    
    $s2 = $db->prepare("SELECT *, 'Expiring Soon' as reason, 'orange' as color FROM inventory WHERE share_code=:sc AND status='in_stock' AND expiry_date >= :today AND expiry_date <= :soon");
    $s2->bindValue(':sc', $SC); $s2->bindValue(':today', $today); $s2->bindValue(':soon', $soon);
    $res = $s2->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) $list[] = $row;
    
    $s3 = $db->prepare("SELECT * FROM my_meds WHERE share_code=:sc AND status='active'");
    $s3->bindValue(':sc', $SC);
    $res = $s3->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $s4 = $db->prepare("SELECT count(*) FROM inventory WHERE share_code=:sc AND status='in_stock' AND name=:name");
        $s4->bindValue(':sc', $SC); $s4->bindValue(':name', $row['name']);
        if ($s4->execute()->fetchArray()[0] < 1) { 
            $list[] = ['id' => $row['id'], 'name' => $row['name'], 'reason' => 'Low Stock', 'color' => 'blue', 'strength' => "For: " . ($row['owner']?:'Unknown'), 'is_mymed' => true]; 
        }
    }
    echo json_encode($list); exit;
}

// --- Inventory ---
if ($action === 'get_inventory') {
    $sort = $_POST['sort'] ?? 'name';
    $filter = $_POST['filter'] ?? 'all';
    $owner = $_POST['owner'] ?? '';
    $search = $_POST['search'] ?? '';
    
    $sql = "SELECT * FROM inventory WHERE share_code = :sc AND status != 'trash'";
    $params = [':sc' => $SC];
    
    if ($filter === 'in_stock') $sql .= " AND status = 'in_stock'";
    elseif ($filter === 'to_discard') { $sql .= " AND expiry_date < :today"; $params[':today'] = date('Y-m-d'); }
    elseif ($filter === 'expiring_soon') { 
        $sql .= " AND expiry_date <= :soon AND expiry_date >= :today"; 
        $params[':soon'] = date('Y-m-d', strtotime('+30 days')); 
        $params[':today'] = date('Y-m-d'); 
    }
    
    if ($owner) {
        $sql .= " AND owner = :owner";
        $params[':owner'] = $owner;
    }

    if ($search) {
        $sql .= " AND (name LIKE :search OR code LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $sql .= " ORDER BY " . ($sort === 'expiry' ? 'expiry_date' : 'name ASC');
    
    $stmt = $db->prepare($sql);
    foreach($params as $k => $v) $stmt->bindValue($k, $v);
    
    $res = $stmt->execute();
    $data = []; while ($row = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $row;
    echo json_encode($data); exit;
}

if ($action === 'add_inventory') {
    $name = $_POST['name'];
    $stmt = $db->prepare("SELECT count(*) FROM master_meds WHERE name = :name");
    $stmt->bindValue(':name', $name);
    if (!$stmt->execute()->fetchArray()[0]) { echo json_encode(['status' => 'error', 'message' => 'Please add this item to the master list first.']); exit; }
    
    $code = random_int(10000, 99999);
    $stmt = $db->prepare("INSERT INTO inventory (code, name, strength, location, owner, expiry_date, notes, share_code) VALUES (:c, :n, :s, :l, :o, :e, :note, :sc)");
    $stmt->bindValue(':c', $code); $stmt->bindValue(':n', $name); $stmt->bindValue(':s', $_POST['strength']);
    $stmt->bindValue(':l', $_POST['location']); $stmt->bindValue(':o', $_POST['owner']); 
    $stmt->bindValue(':e', $_POST['expiry']); $stmt->bindValue(':note', $_POST['notes']); $stmt->bindValue(':sc', $SC);
    $stmt->execute();
    echo json_encode(['status' => 'success', 'code' => $code]); exit;
}

if ($action === 'update_status') {
    $id = intval($_POST['id']);
    $status = $_POST['status'];
    $extra = ($status === 'trash') ? ", deleted_date = CURRENT_TIMESTAMP" : "";
    
    $stmt = $db->prepare("UPDATE inventory SET status = :st $extra WHERE id = :id AND share_code = :sc");
    $stmt->bindValue(':st', $status);
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':sc', $SC);
    $stmt->execute();
    echo json_encode(['status' => 'success']); exit;
}

// --- My Meds ---
if ($action === 'get_mymeds') {
    $active = []; 
    $stmt = $db->prepare("SELECT * FROM my_meds WHERE share_code=:sc AND status = 'active' ORDER BY name ASC");
    $stmt->bindValue(':sc', $SC);
    $res = $stmt->execute();
    
    while($row = $res->fetchArray(SQLITE3_ASSOC)){
        $s2 = $db->prepare("SELECT count(*) FROM inventory WHERE share_code=:sc AND status='in_stock' AND name=:name");
        $s2->bindValue(':sc', $SC); $s2->bindValue(':name', $row['name']);
        $row['stock_count'] = $s2->execute()->fetchArray()[0];
        $active[] = $row;
    }
    
    $ordered = []; 
    $stmt = $db->prepare("SELECT * FROM my_meds WHERE share_code=:sc AND status = 'ordered' ORDER BY last_ordered_date DESC");
    $stmt->bindValue(':sc', $SC);
    $res = $stmt->execute();
    while($row = $res->fetchArray(SQLITE3_ASSOC)) $ordered[] = $row;
    
    echo json_encode(['active' => $active, 'ordered' => $ordered]); exit;
}

if ($action === 'add_mymed') {
    $stmt = $db->prepare("INSERT OR IGNORE INTO my_meds (name, owner, share_code) VALUES (:n, :o, :sc)");
    $stmt->bindValue(':n', $_POST['name']); $stmt->bindValue(':o', $_POST['owner']); $stmt->bindValue(':sc', $SC); 
    $stmt->execute();
    echo json_encode(['status' => 'success']); exit;
}

if ($action === 'mark_ordered') { 
    $stmt = $db->prepare("UPDATE my_meds SET status = 'ordered', last_ordered_date = :date WHERE id = :id AND share_code = :sc");
    $stmt->bindValue(':date', $_POST['date']); $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC);
    $stmt->execute();
    echo json_encode(['status' => 'success']); exit; 
}

if ($action === 'mymed_received') { 
    $stmt = $db->prepare("UPDATE my_meds SET status = 'active' WHERE id = :id AND share_code = :sc");
    $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC);
    $stmt->execute();
    echo json_encode(['status' => 'success']); exit; 
}

if ($action === 'delete_mymed') { 
    $stmt = $db->prepare("DELETE FROM my_meds WHERE id = :id AND share_code = :sc");
    $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC);
    $stmt->execute();
    echo json_encode(['status' => 'success']); exit; 
}

// --- Master List ---
if ($action === 'get_master') {
    $letter = $_POST['letter'] ?? 'A';
    $stmt = $db->prepare("SELECT * FROM master_meds WHERE name LIKE :l ORDER BY name ASC");
    $stmt->bindValue(':l', $letter . '%');
    $res = $stmt->execute();
    $data = []; while($row = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $row;
    echo json_encode($data); exit;
}
if ($action === 'add_master') { 
    $stmt = $db->prepare("INSERT OR IGNORE INTO master_meds (name) VALUES (:n)"); 
    $stmt->bindValue(':n', $_POST['name']); $stmt->execute(); 
    echo json_encode(['status' => 'success']); exit; 
}
if ($action === 'delete_master') { 
    // Master meds are global, but let's delete safely
    $stmt = $db->prepare("DELETE FROM master_meds WHERE id = :id");
    $stmt->bindValue(':id', $_POST['id']); $stmt->execute(); 
    echo json_encode(['status' => 'success']); exit; 
}
if ($action === 'reset_master') { populateMaster($db); echo json_encode(['status' => 'success']); exit; }

if ($action === 'master_search') {
    $stmt = $db->prepare("SELECT name FROM master_meds WHERE name LIKE :term LIMIT 10");
    $stmt->bindValue(':term', $_POST['term'] . '%');
    $res = $stmt->execute();
    $data = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $s2 = $db->prepare("SELECT count(*) FROM inventory WHERE share_code=:sc AND status = 'in_stock' AND name = :name");
        $s2->bindValue(':sc', $SC); $s2->bindValue(':name', $row['name']);
        $count = $s2->execute()->fetchArray()[0];
        $data[] = ['name' => $row['name'], 'count' => $count];
    }
    echo json_encode($data); exit;
}

// --- System ---
if ($action === 'get_user_info') { 
    $stmt = $db->prepare("SELECT fullname FROM users WHERE id = :id");
    $stmt->bindValue(':id', $_SESSION['user_id']);
    echo json_encode($stmt->execute()->fetchArray(SQLITE3_ASSOC)); exit; 
}
if ($action === 'update_fullname') { 
    $stmt = $db->prepare("UPDATE users SET fullname = :f WHERE id = :id"); 
    $stmt->bindValue(':f', $_POST['fullname']); $stmt->bindValue(':id', $_SESSION['user_id']); 
    $stmt->execute(); echo json_encode(['status'=>'success']); exit; 
}

if ($action === 'get_family') { 
    $stmt = $db->prepare("SELECT * FROM family_members WHERE share_code = :sc");
    $stmt->bindValue(':sc', $SC);
    $res = $stmt->execute();
    $data = []; while($row = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $row; echo json_encode($data); exit; 
}
if ($action === 'add_family') { 
    $stmt = $db->prepare("INSERT OR IGNORE INTO family_members (name, share_code) VALUES (:n, :sc)"); 
    $stmt->bindValue(':n', $_POST['name']); $stmt->bindValue(':sc', $SC); $stmt->execute(); echo json_encode(['status'=>'success']); exit; 
}
if ($action === 'delete_family') { 
    $stmt = $db->prepare("DELETE FROM family_members WHERE id = :id AND share_code = :sc");
    $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC); $stmt->execute(); echo json_encode(['status'=>'success']); exit; 
}
if ($action === 'set_default_family') { 
    $db->exec('BEGIN');
    $s1 = $db->prepare("UPDATE family_members SET is_default = 0 WHERE share_code = :sc"); $s1->bindValue(':sc', $SC); $s1->execute();
    $s2 = $db->prepare("UPDATE family_members SET is_default = 1 WHERE id = :id AND share_code = :sc"); $s2->bindValue(':id', $_POST['id']); $s2->bindValue(':sc', $SC); $s2->execute(); 
    $db->exec('COMMIT');
    echo json_encode(['status'=>'success']); exit; 
}

if ($action === 'get_locations') { 
    $stmt = $db->prepare("SELECT * FROM locations WHERE share_code = :sc");
    $stmt->bindValue(':sc', $SC);
    $res = $stmt->execute();
    $data = []; while($row = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $row; echo json_encode($data); exit; 
}
if ($action === 'add_location') { 
    $stmt = $db->prepare("INSERT OR IGNORE INTO locations (name, share_code) VALUES (:n, :sc)"); 
    $stmt->bindValue(':n', $_POST['name']); $stmt->bindValue(':sc', $SC); $stmt->execute(); echo json_encode(['status'=>'success']); exit; 
}
if ($action === 'delete_location') { 
    $stmt = $db->prepare("DELETE FROM locations WHERE id = :id AND share_code = :sc");
    $stmt->bindValue(':id', $_POST['id']); $stmt->bindValue(':sc', $SC); $stmt->execute(); echo json_encode(['status'=>'success']); exit; 
}
if ($action === 'set_default_location') { 
    $db->exec('BEGIN');
    $s1 = $db->prepare("UPDATE locations SET is_default = 0 WHERE share_code = :sc"); $s1->bindValue(':sc', $SC); $s1->execute();
    $s2 = $db->prepare("UPDATE locations SET is_default = 1 WHERE id = :id AND share_code = :sc"); $s2->bindValue(':id', $_POST['id']); $s2->bindValue(':sc', $SC); $s2->execute(); 
    $db->exec('COMMIT');
    echo json_encode(['status'=>'success']); exit; 
}

if ($action === 'get_users') { if(!isAdmin()){ echo json_encode([]); exit; } $res = $db->query("SELECT id, username, role, fullname FROM users"); $data = []; while($row = $res->fetchArray(SQLITE3_ASSOC)) $data[] = $row; echo json_encode($data); exit; }

if ($action === 'add_user') {
    if(!isAdmin()) exit;
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $newSC = generateSecureCode();
    
    $stmt = $db->prepare("INSERT INTO users (username, password, role, share_code) VALUES (:u, :p, :r, :sc)");
    $stmt->bindValue(':u', $_POST['username']); $stmt->bindValue(':p', $pass); 
    $stmt->bindValue(':r', $_POST['role']); $stmt->bindValue(':sc', $newSC);
    $stmt->execute();
    
    $s1 = $db->prepare("INSERT INTO locations (name, is_default, share_code) VALUES ('Medicine Cupboard', 1, :sc)");
    $s1->bindValue(':sc', $newSC); $s1->execute();
    $s2 = $db->prepare("INSERT INTO family_members (name, is_default, share_code) VALUES ('Me', 1, :sc)");
    $s2->bindValue(':sc', $newSC); $s2->execute();
    
    echo json_encode(['status'=>'success']); exit;
}
if ($action === 'delete_user') { 
    if(isAdmin()){
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindValue(':id', $_POST['id']); $stmt->execute();
    }
    echo json_encode(['status'=>'success']); exit; 
}
if ($action === 'admin_reset_password') { 
    if(!isAdmin()) exit; 
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $stmt = $db->prepare("UPDATE users SET password = :p WHERE id = :id"); 
    $stmt->bindValue(':p', $pass); $stmt->bindValue(':id', $_POST['id']); 
    $stmt->execute(); echo json_encode(['status'=>'success']); exit; 
}

if ($action === 'update_my_password') { 
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $stmt = $db->prepare("UPDATE users SET password = :p WHERE id = :id"); 
    $stmt->bindValue(':p', $pass); $stmt->bindValue(':id', $_SESSION['user_id']); 
    $stmt->execute(); echo json_encode(['status'=>'success']); exit; 
}

if ($action === 'backup_json') {
    $tables = ['locations', 'family_members', 'inventory', 'my_meds'];
    $backup = [];
    foreach ($tables as $t) { 
        $stmt = $db->prepare("SELECT * FROM $t WHERE share_code=:sc");
        $stmt->bindValue(':sc', $SC);
        $res = $stmt->execute(); 
        $backup[$t] = []; while ($row = $res->fetchArray(SQLITE3_ASSOC)) $backup[$t][] = $row; 
    }
    $res = $db->query("SELECT * FROM master_meds"); $backup['master_meds'] = []; while ($row = $res->fetchArray(SQLITE3_ASSOC)) $backup['master_meds'][] = $row;
    echo json_encode($backup); exit;
}

if ($action === 'restore_json') {
    if (!isset($_FILES['file'])) { echo json_encode(['status'=>'error']); exit; }
    $json = file_get_contents($_FILES['file']['tmp_name']); $data = json_decode($json, true);
    if(!$data) { echo json_encode(['status'=>'error']); exit; }
    
    $db->exec('BEGIN');
    try {
        foreach ($data as $table => $rows) {
            if($table === 'master_meds') $db->exec("DELETE FROM master_meds"); 
            else {
                $del = $db->prepare("DELETE FROM $table WHERE share_code = :sc");
                $del->bindValue(':sc', $SC); $del->execute();
            }
            if (empty($rows)) continue;
            foreach ($rows as $row) {
                if($table !== 'master_meds') $row['share_code'] = $SC; unset($row['id']);
                
                $cols = implode(",", array_keys($row));
                $placeholders = implode(",", array_fill(0, count($row), "?"));
                
                $stmt = $db->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
                $i = 1;
                foreach ($row as $v) $stmt->bindValue($i++, $v);
                $stmt->execute();
            }
        }
        $db->exec('COMMIT');
        echo json_encode(['status' => 'success']); 
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        echo json_encode(['status' => 'error']);
    }
    exit;
}
?>