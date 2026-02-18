<?php 
session_start(); 
// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediTrack</title>
    <link rel="icon" type="image/png" href="Pill%20Icon.png">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
</head>
<body>

<svg style="display: none;">
    <symbol id="icon-sun" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0 .39-.39.39-1.03 0-1.41l-1.06-1.06zm1.06-10.96c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06zM7.05 18.36c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06z"/></symbol>
    <symbol id="icon-menu" viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></symbol>
    <symbol id="icon-plus" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></symbol>
    <symbol id="icon-minus" viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2z"/></symbol>
    <symbol id="icon-copy" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></symbol>
</svg>

<?php if (!isset($_SESSION['user_id'])): ?>
    <div class="login-screen">
        <div class="card" style="width: 300px;">
            <div style="text-align:center; margin-bottom: 20px;">
                <img src="MediTrack%20Logo.png" alt="MediTrack" style="width: 200px; height: auto; max-width: 100%;">
            </div>
            <form id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="btn" style="width: 100%;">Login</button>
            </form>
            <p id="loginError" style="color:red; text-align:center;"></p>
        </div>
    </div>
<?php else: ?>

    <nav>
        <div class="logo">
            <img src="MediTrack%20Logo.png" alt="MediTrack">
        </div>
        <div class="nav-controls">
            <button class="btn-outline btn-sm" id="textDecBtn"><svg class="icon"><use href="#icon-minus"></use></svg></button>
            <button class="btn-outline btn-sm" id="textIncBtn"><svg class="icon"><use href="#icon-plus"></use></svg></button>
            <button class="btn-outline btn-sm" id="themeBtn"><svg class="icon"><use href="#icon-sun"></use></svg></button>
            <button class="hamburger" id="hamburgerBtn"><svg class="icon"><use href="#icon-menu"></use></svg></button>
        </div>
        <div class="nav-links" id="navLinks">
            <button data-page="dashboard" class="active">Dashboard</button>
            <button data-page="inventory">Inventory</button>
            <button data-page="master">Master List</button>
            <button data-page="mymeds">My Meds</button>
            <button data-page="reports">Reports</button>
            <button data-page="system">System</button>
            <button onclick="logout()">Logout</button>
        </div>
    </nav>
    <div class="container" id="appContent"></div>

    <div class="modal" id="addItemModal">
        <div class="modal-content">
            <h3>Add Stock</h3>
            <form id="addStockForm">
                <div class="autocomplete-wrapper">
                    <input type="text" id="addName" name="name" placeholder="Medication Name (Search)" required autocomplete="off">
                    <div class="autocomplete-list" id="addNameList"></div>
                </div>
                <input type="text" name="strength" placeholder="Strength (e.g. 500mg)">
                <label>Family Member (Owner)</label>
                <select name="owner" id="addOwnerSelect" required></select>
                <label>Location</label>
                <select name="location" id="locationSelect" required></select>
                <label>Expiry Date</label>
                <input type="date" name="expiry" required>
                <textarea name="notes" placeholder="Notes"></textarea>
                <div class="flex-between">
                    <button type="button" class="btn btn-danger" onclick="closeModal('addItemModal')">Cancel</button>
                    <button type="submit" class="btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="orderModal">
        <div class="modal-content">
            <h3>Mark as Ordered</h3>
            <input type="hidden" id="orderId">
            <label>Date Ordered</label>
            <input type="date" id="orderDate" required>
            <div class="flex-between">
                <button type="button" class="btn-outline" onclick="closeModal('orderModal')">Cancel</button>
                <button class="btn" onclick="confirmOrder()">Save</button>
            </div>
        </div>
    </div>

    <div class="modal" id="resetPassModal">
        <div class="modal-content">
            <h3>Reset Password</h3>
            <p>Enter the new password for this user:</p>
            <input type="password" id="resetPassInput" placeholder="New Password">
            <div class="flex-between">
                <button class="btn-outline" onclick="closeModal('resetPassModal')">Cancel</button>
                <button class="btn" onclick="confirmResetPass()">Save</button>
            </div>
        </div>
    </div>

    <div class="modal" id="customAlertModal">
        <div class="modal-content custom-alert-box">
            <h3 id="customAlertTitle">Message</h3>
            <p id="customAlertText"></p>
            <div class="custom-alert-buttons"><button class="btn" onclick="closeModal('customAlertModal')">OK</button></div>
        </div>
    </div>
    <div class="modal" id="customConfirmModal">
        <div class="modal-content custom-alert-box">
            <h3 id="customConfirmTitle">Confirm</h3>
            <p id="customConfirmText"></p>
            <div class="custom-alert-buttons">
                <button class="btn-outline" onclick="closeModal('customConfirmModal')">Cancel</button>
                <button class="btn" id="customConfirmYesBtn">Yes</button>
            </div>
        </div>
    </div>

<?php endif; ?>

<script>
const API = 'api.php';
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
const currentUserRole = '<?php echo $_SESSION['role'] ?? 'user'; ?>';

// --- XSS Protection Function ---
function e(str) {
    if(!str) return '';
    return str.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// --- SECURE API CALL WRAPPER ---
// Automatically appends CSRF token to every POST request
async function callApi(action, data = {}) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', CSRF_TOKEN); // Inject Token
    
    for (const key in data) {
        fd.append(key, data[key]);
    }
    
    const res = await fetch(API, { method: 'POST', body: fd }).then(r => r.json());
    return res;
}

document.getElementById('hamburgerBtn')?.addEventListener('click', () => { document.getElementById('navLinks').classList.toggle('show'); });
const navBtns = document.querySelectorAll('[data-page]');
navBtns.forEach(btn => btn.addEventListener('click', () => {
    document.querySelector('.nav-links button.active')?.classList.remove('active');
    btn.classList.add('active');
    document.getElementById('navLinks').classList.remove('show');
    loadPage(btn.dataset.page);
}));
if(document.getElementById('appContent')) loadPage('dashboard');

function showAlert(msg, title="Notification") { document.getElementById('customAlertTitle').innerText = title; document.getElementById('customAlertText').innerText = msg; document.getElementById('customAlertModal').classList.add('open'); }
let confirmCallback = null;
function showConfirm(msg, callback, title="Are you sure?") { document.getElementById('customConfirmTitle').innerText = title; document.getElementById('customConfirmText').innerText = msg; confirmCallback = callback; document.getElementById('customConfirmModal').classList.add('open'); }
document.getElementById('customConfirmYesBtn')?.addEventListener('click', () => { if(confirmCallback) confirmCallback(); closeModal('customConfirmModal'); });

const themeBtn = document.getElementById('themeBtn');
if(themeBtn){
    let isDark = localStorage.getItem('theme') === 'dark';
    if(isDark) document.documentElement.setAttribute('data-theme', 'dark');
    themeBtn.innerHTML = `<svg class="icon"><use href="#icon-${isDark ? 'moon' : 'sun'}"></use></svg>`;
    themeBtn.addEventListener('click', () => {
        isDark = !isDark;
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        themeBtn.innerHTML = `<svg class="icon"><use href="#icon-${isDark ? 'moon' : 'sun'}"></use></svg>`;
    });
}
function updateTextSize(c) { let s = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--font-size')) || 16; s += c; if(s<12)s=12; if(s>24)s=24; document.documentElement.style.setProperty('--font-size', s + 'px'); }
document.getElementById('textIncBtn')?.addEventListener('click', () => updateTextSize(2));
document.getElementById('textDecBtn')?.addEventListener('click', () => updateTextSize(-2));

document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
    e.preventDefault(); 
    const fd = new FormData(e.target); 
    fd.append('action', 'login');
    // Note: Form already has csrf_token input field
    const res = await fetch(API, {method:'POST', body:fd}).then(r=>r.json());
    if(res.status === 'success') location.reload(); else document.getElementById('loginError').innerText = res.message;
});
function logout(){ callApi('logout').then(()=>location.reload()); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }

// --- RENDERERS ---

async function loadPage(p){
    const c = document.getElementById('appContent'); c.innerHTML = '<p>Loading...</p>';
    if(p === 'dashboard') renderDashboard(c);
    if(p === 'inventory') renderInventory(c);
    if(p === 'master') renderMaster(c);
    if(p === 'mymeds') renderMyMeds(c);
    if(p === 'reports') renderReports(c);
    if(p === 'system') renderSystem(c);
}

// 1. DASHBOARD
async function renderDashboard(c){
    const stats = await callApi('get_dashboard_stats');
    const attn = await callApi('get_needs_attention');
    c.innerHTML = `
        <div class="stats-grid">
            <div class="card red"><h3>Expired</h3><div class="number">${stats.expired}</div></div>
            <div class="card orange"><h3>Expiring Soon</h3><div class="number">${stats.expiring_soon}</div></div>
            <div class="card blue"><h3>Low Stock</h3><div class="number">${stats.low_stock}</div></div>
        </div>
        <div class="card">
            <h2>Needs Attention</h2>
            ${attn.length===0?'<p>All good.</p>':attn.map(i => `
                <div class="list-item" style="border-left-color: var(--${i.color})">
                    <div><strong>${e(i.name)}</strong><br><small>${e(i.reason)} ${i.is_mymed?`(${e(i.strength)})`:''}</small></div>
                    ${i.is_mymed ? `<button class="btn-sm btn-outline" onclick="openOrderModal(${i.id})">Order</button>` : ''}
                </div>`).join('')}
        </div>`;
}

// 2. INVENTORY
async function renderInventory(c){
    const fam = await callApi('get_family');
    c.innerHTML = `
        <div class="flex-between"><h2>Inventory</h2><button class="btn" onclick="openAddModal()">+ Add</button></div>
        <div class="flex-between" style="gap:10px; margin-bottom:20px;">
            <input type="text" id="invSearch" placeholder="Search..." onkeyup="loadInv()">
            <select id="invSort" onchange="loadInv()">
                <option value="name">A-Z</option>
                <option value="expiry">Expiration</option>
                <optgroup label="Filter by Family Member">
                    ${fam.map(f => `<option value="${e(f.name)}">${e(f.name)}</option>`).join('')}
                </optgroup>
            </select>
            <select id="invFilter" onchange="loadInv()">
                <option value="all">All Items</option>
                <option value="in_stock">In Stock</option>
                <option value="expiring_soon">Expiring Soon</option>
                <option value="to_discard">To Discard</option>
            </select>
        </div>
        <div id="invList"></div>`;
    loadInv();
}
async function loadInv(){
    const sortVal = document.getElementById('invSort').value;
    const list = await callApi('get_inventory', {
        search: document.getElementById('invSearch').value,
        filter: document.getElementById('invFilter').value,
        sort: (sortVal === 'name' || sortVal === 'expiry') ? sortVal : 'name',
        owner: (sortVal !== 'name' && sortVal !== 'expiry') ? sortVal : ''
    });
    
    document.getElementById('invList').innerHTML = list.map(i => `
        <div class="list-item">
            <div><strong>${e(i.name)}</strong> <small>(${e(i.code)})</small><br><small>For: ${e(i.owner)||'?'} | ${e(i.location)} | ${e(i.expiry_date)}</small></div>
            <div><button class="btn-sm btn-outline" onclick="updateStatus(${i.id},'in_use')">Use</button> <button class="btn-sm btn-outline" onclick="updateStatus(${i.id},'trash')">🗑</button></div>
        </div>`).join('');
}
async function updateStatus(id, s){ await callApi('update_status', {id:id, status:s}); loadInv(); }

// 3. MASTER LIST
async function renderMaster(c){
    const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split('');
    c.innerHTML = `<h2>Master List</h2><div class="alphabet">${alphabet.map(l => `<span onclick="loadMaster('${l}')">${l}</span>`).join('')}</div>
        <div style="margin-top:20px; border-top:1px solid var(--border); padding-top:20px;">
             <div class="flex-between"><input type="text" id="newMasterName" placeholder="New Medication Name" style="width:70%"><button class="btn" onclick="addMaster()">Add New</button></div>
             <button class="btn-sm btn-outline" style="margin-top:10px;" onclick="resetMaster()">Reset Default List</button>
        </div>
        <div id="masterList" style="margin-top:20px;"></div>`;
    loadMaster('A');
}
async function loadMaster(l){
    const list = await callApi('get_master', {letter: l});
    document.querySelectorAll('.alphabet span').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.alphabet span').forEach(s => { if(s.innerText === l) s.classList.add('active'); });
    document.getElementById('masterList').innerHTML = list.length ? list.map(i => `<div class="list-item"><strong>${e(i.name)}</strong><button class="btn-sm btn-danger" onclick="delMaster(${i.id}, '${l}')">Del</button></div>`).join('') : '<p>No items found.</p>';
}
async function addMaster(){ const n = document.getElementById('newMasterName').value; if(!n)return; await callApi('add_master', {name:n}); loadMaster(n[0].toUpperCase()); document.getElementById('newMasterName').value=''; }
async function delMaster(id, l){ showConfirm('Delete?', async () => { await callApi('delete_master', {id:id}); loadMaster(l); }); }
async function resetMaster(){ showConfirm('Reset?', async () => { await callApi('reset_master'); loadMaster('A'); }); }

// 4. MY MEDS
async function renderMyMeds(c){
    const fam = await callApi('get_family');
    c.innerHTML = `<h2>My Medications</h2>
        <div class="card">
            <div class="autocomplete-wrapper"><input type="text" id="myMedName" placeholder="Type to search and add" autocomplete="off"><div class="autocomplete-list" id="myMedList"></div></div>
            <div style="margin-top:10px;">
                <label>For:</label>
                <select id="myMedOwner">${fam.map(f => `<option value="${e(f.name)}" ${f.is_default?'selected':''}>${e(f.name)}</option>`).join('')}</select>
            </div>
            <button class="btn" style="margin-top:10px;" onclick="addMyMed()">Add to List</button>
        </div>
        <h3>Active List</h3><div id="activeMyMeds"></div><h3>Ordered Items</h3><div id="orderedMyMeds"></div>`;
    setupAutocomplete(document.getElementById('myMedName'), document.getElementById('myMedList'));
    loadMyMeds();
}
async function loadMyMeds(){
    const res = await callApi('get_mymeds');
    document.getElementById('activeMyMeds').innerHTML = res.active.map(i => `
        <div class="list-item ${i.stock_count < 1 ? 'low' : ''}">
            <div><strong>${e(i.name)}</strong> (${e(i.owner)})<br><small>Stock: ${i.stock_count}</small></div>
            <div>${i.stock_count < 1 ? `<button class="btn-sm btn" onclick="openOrderModal(${i.id})">Order</button>` : ''} <button class="btn-sm btn-danger" onclick="delMyMed(${i.id})">Del</button></div>
        </div>`).join('');
    document.getElementById('orderedMyMeds').innerHTML = res.ordered.map(i => `
        <div class="list-item"><div><strong>${e(i.name)}</strong> (${e(i.owner)})<br><small>Ordered: ${e(i.last_ordered_date)}</small></div>
        <div><button class="btn-sm btn-outline" onclick="receiveMyMed(${i.id}, '${e(i.name).replace(/'/g, "\\'")}')">Received</button></div></div>`).join('');
}
async function addMyMed(){ await callApi('add_mymed', {name: document.getElementById('myMedName').value, owner: document.getElementById('myMedOwner').value}); loadMyMeds(); document.getElementById('myMedName').value=''; }
function openOrderModal(id){ document.getElementById('orderId').value = id; document.getElementById('orderDate').valueAsDate = new Date(); document.getElementById('orderModal').classList.add('open'); }
async function confirmOrder(){ await callApi('mark_ordered', {id: document.getElementById('orderId').value, date: document.getElementById('orderDate').value}); closeModal('orderModal'); loadMyMeds(); }
async function receiveMyMed(id, name){ await callApi('mymed_received', {id:id}); openAddModal(); document.getElementById('addName').value = name; loadMyMeds(); }
async function delMyMed(id){ showConfirm('Delete?', async () => { await callApi('delete_mymed', {id:id}); loadMyMeds(); }); }

// 5. REPORTS
function renderReports(c){
    c.innerHTML = `<h2>Reports</h2>
        <div class="card"><p>PDF Reports</p>
            <button class="btn" onclick="genReport('stock')">All Stock</button>
            <button class="btn" onclick="genReport('reorder')">Reorder List</button>
            <button class="btn" onclick="genReport('discard')">Discard List</button>
        </div>`;
}
async function genReport(type){
    const { jsPDF } = window.jspdf; const doc = new jsPDF();
    const data = await callApi('get_inventory', {filter: type==='discard' ? 'to_discard' : 'all'});
    doc.text(`MediTrack Report: ${type.toUpperCase()}`, 10, 10); doc.setFontSize(10); doc.text(`Date: ${new Date().toLocaleDateString()}`, 10, 15);
    doc.autoTable({ head: [['Code', 'Name', 'For', 'Expiry']], body: data.map(r => [r.code, r.name, r.owner, r.expiry_date]), startY: 20, theme: 'plain', styles: { textColor: [0,0,0], lineColor: [0,0,0], lineWidth: 0.1 }, headStyles: { fillColor: [255, 255, 255], textColor: [0,0,0], fontStyle: 'bold', lineWidth: 0.1 } });
    doc.save(`meditrack_${type}.pdf`);
}

// 6. SYSTEM
async function renderSystem(c){
    const user = await callApi('get_user_info');
    const sc = await callApi('get_share_code');
    
    c.innerHTML = `<h2>System</h2>
        <div class="card"><h3>Share Access</h3>
            <p>Share this code to allow others to view/edit this data.</p>
            <div class="copy-group">
                <input type="text" value="${e(sc.code)}" readonly id="shareCodeInput">
                <button class="btn" onclick="copyShareCode()"><svg class="icon"><use href="#icon-copy"></use></svg></button>
            </div>
            <p style="margin-top:15px;"><button class="btn-outline" onclick="regenerateCode()">Generate New Code</button></p>
            <p><small>Warning: Generating a new code will revoke access for anyone using the old code. Your data will move to the new code.</small></p>
        </div>
        
        <div class="card"><h3>Join Household</h3>
            <p>Enter a share code to switch your view to another household.</p>
            <div class="flex-between">
                <input type="text" id="joinCodeInput" placeholder="Enter 10-char Code" maxlength="10" style="text-transform:uppercase;">
                <button class="btn" onclick="joinHousehold()">Join</button>
            </div>
            <p><small>Note: This will hide your current data unless you save your current code first.</small></p>
        </div>

        <div class="card"><h3>My Password</h3><input type="password" id="myNewPass" placeholder="New Password"><button class="btn" onclick="updateMyPass()">Update Password</button></div>
        <div class="card"><h3>My Full Name</h3><input type="text" id="myFullName" value="${e(user.fullname||'')}"><button class="btn" onclick="updateFullName()">Save Name</button></div>
        <div class="card"><h3>Family Members</h3>
            <div class="flex-between"><input type="text" id="newFamName" placeholder="Name"><button class="btn" onclick="addFamily()">Add</button></div>
            <div id="famList" style="margin-top:10px;"></div>
        </div>
        <div class="card"><h3>Locations</h3>
            <div class="flex-between"><input type="text" id="newLocName" placeholder="Location Name"><button class="btn" onclick="addLoc()">Add</button></div>
            <div id="locList" style="margin-top:10px;"></div>
        </div>`;
    
    if(currentUserRole === 'admin'){
        c.innerHTML += `<div class="card"><h3>User Management (Admin)</h3>
            <div class="flex-between">
                <input type="text" id="newUsername" placeholder="Username" style="width:30%">
                <input type="password" id="newUserPass" placeholder="Password" style="width:30%">
                <select id="newUserRole" style="width:20%"><option value="user">User</option><option value="admin">Admin</option></select>
                <button class="btn" onclick="addUser()">Add</button>
            </div>
            <div id="userList" style="margin-top:10px;"></div>
        </div>`;
        setTimeout(loadUsers, 100);
    }
    c.innerHTML += `<div class="card"><h3>Data Management</h3><button class="btn" onclick="downloadBackup()">Download JSON Backup</button><hr><div class="flex-between"><input type="file" id="restoreFile"><button class="btn" onclick="restoreBackup()">Restore JSON</button></div></div>`;
    
    setTimeout(loadFamily, 100); setTimeout(loadLocations, 100);
}

function copyShareCode() {
    const copyText = document.getElementById("shareCodeInput");
    copyText.select(); copyText.setSelectionRange(0, 99999); 
    navigator.clipboard.writeText(copyText.value);
    showAlert("Code copied to clipboard!");
}
async function regenerateCode() {
    showConfirm("Generate a new share code? This will update all your current data to the new code and revoke access for anyone using the old code.", async () => {
        const res = await callApi('regenerate_share_code');
        if(res.status === 'success') { showAlert("New code generated!"); loadPage('system'); } else { showAlert(res.message); }
    });
}
async function joinHousehold() {
    const code = document.getElementById('joinCodeInput').value;
    if(code.length !== 10) return showAlert("Code must be 10 characters.");
    showConfirm("Join this household? Your view will switch to their data.", async () => {
        const res = await callApi('join_share_code', {code: code});
        if(res.status === 'success') { showAlert("Joined successfully!"); location.reload(); } else { showAlert(res.message); }
    });
}
async function updateMyPass(){ const p = document.getElementById('myNewPass').value; if(!p)return; await callApi('update_my_password', {password:p}); showAlert('Updated'); document.getElementById('myNewPass').value=''; }
async function updateFullName(){ await callApi('update_fullname', {fullname: document.getElementById('myFullName').value}); showAlert('Saved'); }

async function loadFamily(){
    const list = await callApi('get_family');
    document.getElementById('famList').innerHTML = list.map(f => `
        <div class="list-item">
            <div>${e(f.name)} ${f.is_default?'(Default)':''}</div>
            <div>${!f.is_default ? `<button class="btn-sm btn-outline" onclick="setDefaultFam(${f.id})">Set Default</button>` : ''} <button class="btn-sm btn-danger" onclick="delFam(${f.id})">Del</button></div>
        </div>`).join('');
}
async function addFamily(){ await callApi('add_family', {name: document.getElementById('newFamName').value}); loadFamily(); }
async function delFam(id){ showConfirm('Delete?', async () => { await callApi('delete_family', {id:id}); loadFamily(); }); }
async function setDefaultFam(id){ await callApi('set_default_family', {id:id}); loadFamily(); }

async function loadLocations(){
    const list = await callApi('get_locations');
    document.getElementById('locList').innerHTML = list.map(l => `
        <div class="list-item">
            <div>${e(l.name)} ${l.is_default?'(Default)':''}</div>
            <div>${!l.is_default ? `<button class="btn-sm btn-outline" onclick="setDefaultLoc(${l.id})">Set Default</button>` : ''} <button class="btn-sm btn-danger" onclick="delLoc(${l.id})">Del</button></div>
        </div>`).join('');
}
async function addLoc(){ await callApi('add_location', {name: document.getElementById('newLocName').value}); loadLocations(); }
async function delLoc(id){ await callApi('delete_location', {id:id}); loadLocations(); }
async function setDefaultLoc(id){ await callApi('set_default_location', {id:id}); loadLocations(); }

async function loadUsers(){
    const users = await callApi('get_users');
    document.getElementById('userList').innerHTML = users.map(u => `
        <div class="list-item">
            <div><strong>${e(u.username)}</strong> (${e(u.role)}) - ${e(u.fullname||'')}</div>
            <div style="display:flex; gap:5px;">
                <button class="btn-sm btn-outline" onclick="openResetPassModal(${u.id})">Reset Password</button>
                ${u.username!=='admin' ? `<button class="btn-sm btn-danger" onclick="delUser(${u.id})">Del</button>` : ''}
            </div>
        </div>`).join('');
}
async function addUser(){ await callApi('add_user', {username: document.getElementById('newUsername').value, password: document.getElementById('newUserPass').value, role: document.getElementById('newUserRole').value}); loadUsers(); }
async function delUser(id){ showConfirm('Delete?', async () => { await callApi('delete_user', {id:id}); loadUsers(); }); }

let resetUserId = null;
function openResetPassModal(id) { resetUserId = id; document.getElementById('resetPassInput').value = ''; document.getElementById('resetPassModal').classList.add('open'); }
async function confirmResetPass() { const p = document.getElementById('resetPassInput').value; if(!p) return showAlert("Please enter a new password"); await callApi('admin_reset_password', {id: resetUserId, password: p}); closeModal('resetPassModal'); showAlert("Password reset successfully."); }

async function downloadBackup(){ const data = await callApi('backup_json'); const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([JSON.stringify(data)], {type: 'application/json'})); a.download = 'meditrack_backup.json'; a.click(); }
async function restoreBackup(){ const f = document.getElementById('restoreFile').files[0]; if(!f) return; const fd = new FormData(); fd.append('action', 'restore_json'); fd.append('csrf_token', CSRF_TOKEN); fd.append('file', f); await fetch(API, {method:'POST', body:fd}); showAlert('Restored!'); location.reload(); }

function setupAutocomplete(input, list) {
    input.addEventListener('input', async (e) => {
        const val = e.target.value;
        if(val.length < 2) { list.style.display = 'none'; return; }
        const res = await callApi('master_search', {term: val});
        if(res.length > 0) {
            list.innerHTML = res.map(i => `<div><strong>${e(i.name)}</strong> <small>(Stock: ${i.count})</small></div>`).join('');
            list.style.display = 'block';
            list.querySelectorAll('div').forEach(div => {
                div.addEventListener('click', () => { input.value = div.querySelector('strong').innerText; list.style.display = 'none'; });
            });
        } else { list.style.display = 'none'; }
    });
    document.addEventListener('click', (e) => { if(e.target !== input) list.style.display = 'none'; });
}

function openAddModal(){
    document.getElementById('addItemModal').classList.add('open');
    setupAutocomplete(document.getElementById('addName'), document.getElementById('addNameList'));
    callApi('get_locations').then(locs => { document.getElementById('locationSelect').innerHTML = locs.map(l => `<option value="${e(l.name)}" ${l.is_default?'selected':''}>${e(l.name)}</option>`).join(''); });
    callApi('get_family').then(fam => { document.getElementById('addOwnerSelect').innerHTML = fam.map(f => `<option value="${e(f.name)}" ${f.is_default?'selected':''}>${e(f.name)}</option>`).join(''); });
}
document.getElementById('addStockForm')?.addEventListener('submit', async (e) => {
    e.preventDefault(); 
    // We construct the object manually to pass to callApi for CSRF injection
    const data = {};
    new FormData(e.target).forEach((value, key) => data[key] = value);
    const res = await callApi('add_inventory', data);
    if(res.status === 'success'){ showAlert('Added! Code: ' + res.code); closeModal('addItemModal'); loadPage('inventory'); } else { showAlert(res.message); }
});
</script>
</body>
</html>