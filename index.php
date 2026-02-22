<?php 
session_start(); 
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$userRole = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediTrack</title>
    
    <link rel="icon" type="image/png" href="Pill%20Icon.png">
    <link rel="apple-touch-icon" href="Pill%20Icon.png">
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#4a90e2">

    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        /* Modernized Navigation Layout */
        nav { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 10px 20px; background: #fff; border-bottom: 1px solid #ddd; flex-wrap: wrap;
        }
        .logo-box { flex: 1; display: flex; align-items: center; justify-content: flex-start; gap: 10px; font-family: sans-serif; min-width: max-content; }
        .logo-box img { width: 35px; height: 35px; }
        .logo-box h2 { margin: 0; color: var(--primary, #4a90e2); font-weight: 700; font-size: 1.2rem; }
        
        .nav-links { flex: 2; display: flex; justify-content: center; gap: 8px; }
        
        /* Premium Menu Styling */
        .nav-links button { 
            border: none; 
            background: transparent; 
            cursor: pointer; 
            padding: 8px 14px; 
            border-radius: 20px; 
            font-size: 1rem;
            transition: all 0.2s ease;
            text-decoration: none !important;
        }
        .nav-links button.active { 
            background-color: #e3f2fd; 
            color: var(--primary, #4a90e2); 
            font-weight: bold; 
        }

        .nav-controls { flex: 1; display: flex; justify-content: flex-end; gap: 8px; align-items: center; }
        .nav-controls button { padding: 6px; display: flex; align-items: center; justify-content: center; }
        .action-group { display: flex; gap: 5px; flex-wrap: wrap; }
        
        /* STRICT Mobile Layout for Perfect Horizontal Scrolling */
        @media (max-width: 850px) {
            nav { padding: 10px; flex-wrap: wrap; }
            .logo-box { flex: 1 1 auto; }
            .nav-controls { flex: 1 1 auto; justify-content: flex-end; }
            .nav-links { 
                flex: 0 0 100%; /* Forces the menu to its own full-width row */
                order: 3; 
                display: flex;
                flex-direction: row; /* Strictly horizontal */
                flex-wrap: nowrap; /* Prevents vertical stacking */
                justify-content: flex-start; 
                overflow-x: auto; /* Enables swiping left/right */
                padding-top: 12px; 
                padding-bottom: 5px;
                margin-top: 10px;
                border-top: 1px solid #eee; /* Clean separator line */
                -webkit-overflow-scrolling: touch; 
                scrollbar-width: none; 
                gap: 8px;
            }
            .nav-links::-webkit-scrollbar { display: none; }
            .nav-links button { white-space: nowrap; flex-shrink: 0; /* Prevents buttons from squishing */ }
            body.dark-mode .nav-links { border-top-color: #333; }
        }

        /* Autocomplete Styles */
        .autocomplete-wrapper { position: relative; width: 100%; }
        .autocomplete-list { position: absolute; z-index: 1000; background: #fff; border: 1px solid #ccc; width: 100%; max-height: 150px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; border-top: none; }
        .autocomplete-list:empty { border: none; }
        .autocomplete-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .autocomplete-item:hover { background: #f0f0f0; }

        /* Robust Dark Mode Fallbacks */
        body.dark-mode { background-color: #121212; color: #e0e0e0; }
        body.dark-mode nav, body.dark-mode .card, body.dark-mode .modal-content { background-color: #1e1e1e; color: #e0e0e0; border-color: #333; }
        body.dark-mode input, body.dark-mode select, body.dark-mode textarea { background-color: #333; color: #fff; border-color: #555; }
        body.dark-mode .list-item { border-bottom-color: #333; }
        body.dark-mode .autocomplete-list { background-color: #333; border-color: #555; }
        body.dark-mode .autocomplete-item:hover { background-color: #444; border-bottom-color: #555; }
        body.dark-mode .nav-links button.active { background-color: #3a3a3a; color: #6fb3ff; }
        body.dark-mode .icon { fill: #e0e0e0; }
    </style>
</head>
<body>

<svg style="display: none;">
    <symbol id="icon-pill" viewBox="0 0 24 24"><path d="M4.22 15.54l4.24 4.24a4.5 4.5 0 0 0 6.36 0l4.95-4.95a4.5 4.5 0 0 0 0-6.36l-4.24-4.24a4.5 4.5 0 0 0-6.36 0l-4.95 4.95a4.5 4.5 0 0 0 0 6.36zm1.42-4.95l2.83-2.83 5.65 5.65-2.83 2.83a2.5 2.5 0 0 1-3.53 0l-2.12-2.12a2.5 2.5 0 0 1 0-3.53zm9.19-2.12l-2.83 2.83-5.65-5.65 2.83-2.83a2.5 2.5 0 0 1 3.53 0l2.12 2.12a2.5 2.5 0 0 1 0 3.53z"/></symbol>
    <symbol id="icon-play" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></symbol>
    <symbol id="icon-lock" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></symbol>
    <symbol id="icon-download" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></symbol>
    <symbol id="icon-upload" viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></symbol>
    <symbol id="icon-trash" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></symbol>
    <symbol id="icon-edit" viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 0 0 0-1.41l-2.34-2.34a.996.996 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></symbol>
    <symbol id="icon-refresh" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></symbol>
    <symbol id="icon-sun" viewBox="0 0 24 24"><path d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37c-.39-.39-1.03-.39-1.41 0-.39.39-.39 1.03 0 1.41l1.06 1.06c.39.39 1.03.39 1.41 0 .39-.39.39-1.03 0-1.41l-1.06-1.06zm1.06-10.96c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06zM7.05 18.36c.39-.39.39-1.03 0-1.41-.39-.39-1.03-.39-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06z"/></symbol>
    <symbol id="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></symbol>
    <symbol id="icon-copy" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></symbol>
    <symbol id="icon-star" viewBox="0 0 24 24"><path d="M22 9.24l-7.19-.62L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.63-7.03L22 9.24zM12 15.4l-3.76 2.27 1-4.28-3.32-2.88 4.38-.38L12 6.1l1.71 4.04 4.38.38-3.32 2.88 1 4.28L12 15.4z"/></symbol>
    <symbol id="icon-star-filled" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></symbol>
    <symbol id="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></symbol>
    <symbol id="icon-eye-off" viewBox="0 0 24 24"><path d="M11.83 9L15 12.16V12a3 3 0 0 0-3-3h-.17zm-4.3.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm14.33 8.35L2.1 3.3 1.27 4.13l2.81 2.81C2.42 8.36 1.46 10.05 1 12c1.73 4.39 6 7.5 11 7.5 1.35 0 2.64-.26 3.82-.72l2.91 2.91.84-.84zM12 4.5c-1.35 0-2.64.26-3.82.72l2.06 2.06c.55-.18 1.14-.28 1.76-.28 2.76 0 5 2.24 5 5 0 .62-.1 1.21-.28 1.76l2.06 2.06c.46-1.18.72-2.47.72-3.82-1.73-4.39-6-7.5-11-7.5z"/></symbol>
    <symbol id="icon-text-up" viewBox="0 0 24 24"><path d="M2.5 19h2l1.2-3.2h4.6l1.2 3.2h2L8.5 5h-2L2.5 19zm4-5.8l1.6-4.3 1.6 4.3h-3.2zm11.5-2.2v-3h-2v3h-3v2h3v3h2v-3h3v-2h-3z"/></symbol>
    <symbol id="icon-text-down" viewBox="0 0 24 24"><path d="M2.5 19h2l1.2-3.2h4.6l1.2 3.2h2L8.5 5h-2L2.5 19zm4-5.8l1.6-4.3 1.6 4.3h-3.2zm14.5.8v-2h-8v2h8z"/></symbol>
    <symbol id="icon-logout" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></symbol>
</svg>

<?php if (!isset($_SESSION['user_id'])): ?>
    <div class="login-screen">
        <div class="card" style="width: 320px;">
            <div class="logo-box" style="margin-bottom: 20px;">
                <img src="Pill%20Icon.png" alt="Logo">
                <h2>MediTrack</h2>
            </div>
            <form id="loginForm">
                <input type="text" id="loginUsername" name="username" placeholder="Username" required>
                
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="password" id="loginPassword" name="password" placeholder="Password" required style="width: 100%; padding-right: 40px; margin-bottom: 0;">
                    <button type="button" tabindex="-1" onclick="togglePassword()" style="position: absolute; right: 5px; background: none; border: none; cursor: pointer; padding: 5px; display:flex; align-items:center; justify-content:center;">
                        <svg class="icon" id="eyeIcon" style="fill: #888; width: 20px; height: 20px;"><use href="#icon-eye"></use></svg>
                    </button>
                </div>
                
                <label style="display:flex; align-items:center; gap:8px; margin-top:10px; cursor:pointer;">
                    <input type="checkbox" id="loginRemember" style="width:auto; margin:0;"> Stay signed in
                </label>
                
                <button type="button" class="btn" style="width: 100%; margin-top: 15px;" onclick="login()">Login</button>
            </form>
            <p id="loginError" style="color:red; text-align:center;"></p>
        </div>
    </div>
<?php else: ?>

    <nav>
        <div class="logo-box">
            <img src="Pill%20Icon.png" alt="Logo">
            <h2>MediTrack</h2>
        </div>
        
        <div class="nav-links" id="navLinks">
            <button data-page="dashboard" class="active">Dashboard</button>
            <button data-page="inventory">Inventory</button>
            <button data-page="master">Master List</button>
            <button data-page="mymeds">My Meds</button>
            <button data-page="reports">Reports</button>
            <button data-page="system">System</button>
        </div>

        <div class="nav-controls">
            <button class="btn-outline btn-sm" onclick="changeTextSize(-1)" title="Decrease Text Size"><svg class="icon"><use href="#icon-text-down"></use></svg></button>
            <button class="btn-outline btn-sm" onclick="changeTextSize(1)" title="Increase Text Size"><svg class="icon"><use href="#icon-text-up"></use></svg></button>
            <button class="btn-outline btn-sm" id="themeBtn" title="Toggle Theme"><svg class="icon"><use href="#icon-sun"></use></svg></button>
            <button class="btn-outline btn-sm" onclick="logout()" title="Logout"><svg class="icon"><use href="#icon-logout"></use></svg></button>
        </div>
    </nav>

    <div class="container" id="appContent"></div>

    <div class="modal" id="addItemModal">
        <div class="modal-content" style="overflow: visible;">
            <h3>Add Stock</h3>
            <form id="addStockForm">
                <label>Medication (From My Meds)</label>
                <select name="name" id="addNameSelect" required onchange="handleModalMedSelect(this)" style="width: 100%; box-sizing: border-box; margin-bottom: 10px;"></select>
                
                <input type="text" id="addStrengthInput" name="strength" placeholder="Strength (e.g. 500mg)">
                <label>Family Member</label>
                <select name="owner" id="addOwnerSelect" required></select>
                <label>Location</label>
                <select name="location" id="locationSelect" required></select>
                <label>Expiry Date</label>
                <input type="date" name="expiry" required>
                <div class="flex-between">
                    <button type="button" class="btn btn-danger" onclick="closeModal('addItemModal')">Cancel</button>
                    <button type="submit" class="btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="itemUsedModal">
        <div class="modal-content">
            <h3>Confirm Action</h3>
            <p>Are you sure you want to mark this item as used and delete it from your current inventory?</p>
            <div class="flex-between" style="margin-top: 20px;">
                <button class="btn btn-danger" onclick="closeModal('itemUsedModal')">Cancel</button>
                <button class="btn" onclick="executeItemUsed()">Mark as Used</button>
            </div>
        </div>
    </div>

    <div class="modal" id="markInUseModal">
        <div class="modal-content" style="overflow: visible;">
            <h3>Mark In Use</h3>
            <p>Select the new location for this item:</p>
            <select id="inUseLocationSelect" style="width: 100%; box-sizing: border-box; margin-bottom: 20px;"></select>
            <div class="flex-between">
                <button class="btn btn-danger" onclick="closeModal('markInUseModal')">Cancel</button>
                <button class="btn" onclick="executeInUse()">Save</button>
            </div>
        </div>
    </div>

    <div class="modal" id="resetPassModal">
        <div class="modal-content">
            <h3>Reset User Password</h3>
            <p>Enter the new password for this user:</p>
            <input type="password" id="resetPassInput" placeholder="New Password">
            <div class="flex-between">
                <button class="btn btn-danger" onclick="closeModal('resetPassModal')">Cancel</button>
                <button class="btn" onclick="confirmResetPass()">Save</button>
            </div>
        </div>
    </div>

    <div class="modal" id="customAlertModal">
        <div class="modal-content custom-alert-box">
            <p id="customAlertText"></p>
            <div class="custom-alert-buttons"><button class="btn" onclick="closeModal('customAlertModal')">OK</button></div>
        </div>
    </div>
<?php endif; ?>

<script>
// Service Worker Registration for PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(err => console.log('SW Registration failed: ', err));
    });
}

const API = 'api.php';
const currentUserRole = '<?php echo $userRole; ?>';
let currentActionId = null; 

// Theme Logic
const themeBtn = document.getElementById('themeBtn');
if (themeBtn) {
    const currentTheme = localStorage.getItem('theme') || 'light';
    if (currentTheme === 'dark') {
        document.body.classList.add('dark-mode');
        themeBtn.innerHTML = '<svg class="icon"><use href="#icon-moon"></use></svg>';
    }
    themeBtn.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        themeBtn.innerHTML = isDark ? '<svg class="icon"><use href="#icon-moon"></use></svg>' : '<svg class="icon"><use href="#icon-sun"></use></svg>';
    });
}

// Text Scaling Logic
let baseTextSize = 16;
function changeTextSize(delta) {
    baseTextSize += (delta * 2); 
    if (baseTextSize < 12) baseTextSize = 12;
    if (baseTextSize > 24) baseTextSize = 24;
    document.documentElement.style.fontSize = baseTextSize + 'px';
}

function e(s) { return s ? s.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") : ''; }

async function callApi(action, data = {}) {
    const fd = new FormData(); fd.append('action', action);
    for (let k in data) fd.append(k, data[k]);
    try {
        const res = await fetch(API, { method: 'POST', body: fd });
        return await res.json();
    } catch(err) { return {status:'error'}; }
}

function togglePassword() {
    const pwInput = document.getElementById('loginPassword');
    const eyeIcon = document.getElementById('eyeIcon');
    if (pwInput.type === 'password') {
        pwInput.type = 'text';
        eyeIcon.innerHTML = '<use href="#icon-eye-off"></use>';
    } else {
        pwInput.type = 'password';
        eyeIcon.innerHTML = '<use href="#icon-eye"></use>';
    }
}

async function login() {
    const u = document.getElementById('loginUsername').value;
    const p = document.getElementById('loginPassword').value;
    const r = document.getElementById('loginRemember').checked ? 'true' : 'false';
    const res = await callApi('login', {username: u, password: p, remember: r});
    if(res.status === 'success') location.reload(); else document.getElementById('loginError').innerText = res.message;
}

async function logout() {
    await callApi('logout');
    location.reload();
}

// Navigation Button Handler
document.querySelectorAll('[data-page]').forEach(btn => btn.addEventListener('click', () => {
    document.querySelector('.nav-links button.active')?.classList.remove('active');
    btn.classList.add('active');
    loadPage(btn.dataset.page);
}));

function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function showAlert(msg) { document.getElementById('customAlertText').innerText = msg; document.getElementById('customAlertModal').classList.add('open'); }

// ==========================================
// AUTOCOMPLETE FOR MY MEDS PAGE
// ==========================================
let acTimeout = null;
async function handleAC(inputObj, listId) {
    const list = document.getElementById(listId);
    if(!list) return;
    
    clearTimeout(acTimeout);
    const val = inputObj.value.trim();
    
    if(val.length < 2) { 
        list.innerHTML = ''; 
        return; 
    }
    
    acTimeout = setTimeout(async () => {
        const suggestions = await callApi('search_master', {term: val});
        if(suggestions && suggestions.length > 0) {
            list.innerHTML = suggestions.map(s => `
                <div class="autocomplete-item" onclick="selectAC('${inputObj.id}', '${listId}', '${e(s).replace(/'/g, "\\'")}')">
                    ${e(s)}
                </div>
            `).join('');
            list.style.display = 'block';
        } else {
            list.innerHTML = '';
        }
    }, 300);
}

function selectAC(inputId, listId, val) {
    document.getElementById(inputId).value = val;
    document.getElementById(listId).innerHTML = '';
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.autocomplete-wrapper')) {
        document.querySelectorAll('.autocomplete-list').forEach(l => l.innerHTML = '');
    }
});


// --- RENDERERS ---

async function loadPage(p) {
    const c = document.getElementById('appContent'); c.innerHTML = '<p>Loading...</p>';
    if(p === 'dashboard') renderDashboard(c);
    if(p === 'inventory') renderInventory(c);
    if(p === 'mymeds') renderMyMeds(c);
    if(p === 'master') renderMaster(c);
    if(p === 'reports') renderReports(c);
    if(p === 'system') renderSystem(c);
}

// DASHBOARD ACTIONS
async function markOrdered(id) {
    await callApi('mark_mymed_ordered', {id: id});
    loadPage('dashboard');
}

async function discardFromDashboard(id) {
    await callApi('update_status', {id: id, status: 'trash'});
    loadPage('dashboard');
}

async function renderDashboard(c) {
    const stats = await callApi('get_dashboard_stats');
    const attn = await callApi('get_needs_attention');
    c.innerHTML = `
        <div class="stats-grid">
            <div class="card red"><h3>Expired</h3><div class="number">${stats.expired}</div></div>
            <div class="card orange"><h3>Expiring Soon</h3><div class="number">${stats.expiring_soon}</div></div>
        </div>
        <div class="card">
            <h2>Needs Attention</h2>
            ${attn.length === 0 ? '<p>No alerts.</p>' : attn.map(i => `
                <div class="list-item" style="border-left: 5px solid ${i.color}">
                    <div style="flex:1;">
                        <strong>${e(i.name)}</strong> 
                        ${i.code ? `<span style="background: var(--primary, #4a90e2); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-left: 5px; font-family: monospace; white-space: nowrap;">#${e(i.code)}</span>` : ''}
                        - ${e(i.reason)}
                    </div>
                    ${i.type === 'reorder' ? `<button class="btn-sm" style="background:#d9534f; color:white; border:none; margin-left:10px;" onclick="markOrdered(${i.id})">Mark Ordered</button>` : ''}
                    ${i.type === 'expired' ? `<button class="btn-sm" style="background:#f0ad4e; color:white; border:none; margin-left:10px;" onclick="discardFromDashboard(${i.id})">Discarded</button>` : ''}
                </div>`).join('')}
        </div>`;
}

async function renderInventory(c) {
    const inv = await callApi('get_inventory');
    c.innerHTML = `
        <div class="flex-between"><h2>Inventory</h2><button class="btn" onclick="openAddModal()">+ Add</button></div>
        <div id="invList">${inv.map(i => `
            <div class="list-item">
                <div>
                    <strong>${e(i.name)} ${e(i.strength)}</strong> 
                    <span style="background: var(--primary, #4a90e2); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; margin-left: 5px; font-family: monospace; white-space: nowrap;">#${e(i.code)}</span>
                    ${i.status === 'in_use' ? '<small style="color:green; margin-left: 5px;">(In Use)</small>' : ''}<br>
                    <small>For: ${e(i.owner)} | Loc: ${e(i.location)} | Exp: ${e(i.expiry_date)}</small>
                </div>
                <div class="action-group">
                    <button class="btn-sm btn-outline" onclick="openInUseModal(${i.id})" title="Mark In Use"><svg class="icon"><use href="#icon-play"></use></svg></button>
                    <button class="btn-sm btn-outline" onclick="openConfirmUsedModal(${i.id})" title="Item Used"><svg class="icon"><use href="#icon-trash"></use></svg></button>
                </div>
            </div>`).join('')}</div>`;
}

function openConfirmUsedModal(id) {
    currentActionId = id;
    document.getElementById('itemUsedModal').classList.add('open');
}

async function executeItemUsed() {
    await callApi('update_status', {id: currentActionId, status: 'trash'});
    closeModal('itemUsedModal');
    loadPage('inventory');
}

async function openInUseModal(id) {
    currentActionId = id;
    const locs = await callApi('get_locations');
    document.getElementById('inUseLocationSelect').innerHTML = locs.map(l => `<option ${l.is_default == '1' ? 'selected' : ''}>${e(l.name)}</option>`).join('');
    document.getElementById('markInUseModal').classList.add('open');
}

async function executeInUse() {
    const newLoc = document.getElementById('inUseLocationSelect').value;
    await callApi('update_status', {id: currentActionId, status: 'in_use', location: newLoc});
    closeModal('markInUseModal');
    loadPage('inventory');
}

async function renderMyMeds(c) {
    const fam = await callApi('get_family');
    const meds = await callApi('get_mymeds');
    
    c.innerHTML = `
        <h2>My Medications</h2>
        <div class="card">
            <div class="autocomplete-wrapper" style="margin-bottom: 5px;">
                <input type="text" id="myMedName" placeholder="Medication Name" autocomplete="off" oninput="handleAC(this, 'myMedNameList')" style="width: 100%; box-sizing: border-box;">
                <div id="myMedNameList" class="autocomplete-list"></div>
            </div>
            <input type="text" id="myMedStr" placeholder="Strength (e.g. 500mg)" style="width: 100%; box-sizing: border-box; margin-bottom: 5px;">
            <select id="myMedOwner" style="width: 100%; box-sizing: border-box; margin-bottom: 5px;">${fam.map(f => `<option ${f.is_default == '1' ? 'selected' : ''}>${e(f.name)}</option>`).join('')}</select>
            <button class="btn" style="margin-top:10px;" onclick="addMyMed()">Add Med</button>
        </div>
        <div id="activeMyMeds">
            ${meds.active.map(i => `
                <div class="list-item ${i.stock_count < 1 ? 'low' : ''}">
                    <div><strong>${e(i.name)} ${e(i.strength)}</strong> (${e(i.owner)})<br><small>Stock: ${i.stock_count}</small></div>
                    <button class="btn-sm btn-outline" onclick="delMyMed(${i.id})" title="Delete"><svg class="icon"><use href="#icon-trash"></use></svg></button>
                </div>`).join('')}
                
            ${meds.ordered.map(i => `
                <div class="list-item">
                    <div>
                        <strong>${e(i.name)} ${e(i.strength)}</strong> (${e(i.owner)})
                        <span style="background:var(--orange, #f0ad4e); color:white; padding:2px 6px; border-radius:4px; font-size:0.8rem; margin-left:5px;">Ordered</span>
                        <br><small>Stock: ${i.stock_count}</small>
                    </div>
                    <button class="btn-sm btn-outline" onclick="delMyMed(${i.id})" title="Delete"><svg class="icon"><use href="#icon-trash"></use></svg></button>
                </div>`).join('')}
        </div>`;
}

async function addMyMed() {
    await callApi('add_mymed', {name: document.getElementById('myMedName').value, strength: document.getElementById('myMedStr').value, owner: document.getElementById('myMedOwner').value});
    loadPage('mymeds');
}

async function delMyMed(id) {
    if(confirm("Are you sure you want to remove this medication from your list?")) {
        await callApi('delete_mymed', {id: id});
        loadPage('mymeds');
    }
}

// INVENTORY ADD MODAL LOGIC
async function openAddModal() {
    document.getElementById('addItemModal').classList.add('open');
    document.getElementById('addStockForm').reset();
    
    const locs = await callApi('get_locations');
    document.getElementById('locationSelect').innerHTML = locs.map(l => `<option ${l.is_default == '1' ? 'selected' : ''}>${e(l.name)}</option>`).join('');
    
    const fam = await callApi('get_family');
    document.getElementById('addOwnerSelect').innerHTML = fam.map(f => `<option ${f.is_default == '1' ? 'selected' : ''}>${e(f.name)}</option>`).join('');
    
    const meds = await callApi('get_mymeds');
    let medOptions = '<option value="">Select a medication...</option>';
    
    const allMeds = meds.active.concat(meds.ordered);
    allMeds.forEach(m => {
        medOptions += `<option value="${e(m.name)}" data-str="${e(m.strength)}" data-owner="${e(m.owner)}">${e(m.name)} ${e(m.strength)} (${e(m.owner)})</option>`;
    });
    
    document.getElementById('addNameSelect').innerHTML = medOptions;
}

function handleModalMedSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('addStrengthInput').value = opt.getAttribute('data-str') || '';
        document.getElementById('addOwnerSelect').value = opt.getAttribute('data-owner') || '';
    }
}

document.getElementById('addStockForm')?.addEventListener('submit', async (evt) => {
    evt.preventDefault(); 
    const data = {};
    new FormData(evt.target).forEach((value, key) => data[key] = value);
    const res = await callApi('add_inventory', data);
    if(res.status === 'success'){ closeModal('addItemModal'); loadPage('inventory'); } else { showAlert(res.message); }
});

// MASTER LIST LOGIC
async function renderMaster(c) {
    c.innerHTML = `
        <h2>Master List</h2>
        <div class="card" style="margin-bottom: 20px;">
            <p><small>Add a new medication to the database if it doesn't already exist.</small></p>
            <div style="display:flex; gap:5px; margin-top:10px;">
                <input type="text" id="newMasterMed" placeholder="New Medication Name">
                <button class="btn" onclick="addMasterMed()">Add</button>
            </div>
        </div>
        <div class="alphabet" id="alpha" style="display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 15px;"></div>
        <div id="mList"></div>`;
        
    "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split('').forEach(l => {
        const span = document.createElement('span'); span.innerText = l;
        span.onclick = () => loadMasterLetter(l);
        document.getElementById('alpha').appendChild(span);
    });
    
    loadMasterLetter('A');
}

async function loadMasterLetter(l) {
    const list = await callApi('get_master', {letter: l});
    document.getElementById('mList').innerHTML = list.length === 0 ? '<p style="padding: 10px;">No medications found.</p>' : list.map(i => `
        <div class="list-item">
            <div>${e(i.name)}</div>
            <button class="btn-sm btn-outline" onclick="delMaster(${i.id}, '${l}')" title="Delete"><svg class="icon"><use href="#icon-trash"></use></svg></button>
        </div>`).join('');
}

async function addMasterMed() {
    const name = document.getElementById('newMasterMed').value.trim();
    if (!name) return showAlert("Please enter a medication name.");
    
    const res = await callApi('add_master', {name: name});
    if (res.status === 'success') {
        document.getElementById('newMasterMed').value = '';
        const firstLetter = name.charAt(0).toUpperCase();
        loadMasterLetter(firstLetter);
        showAlert("Added successfully!");
    } else {
        showAlert("Failed to add medication.");
    }
}

async function delMaster(id, letter) {
    await callApi('delete_master', {id: id});
    loadMasterLetter(letter);
}

// REPORTS LOGIC
function renderReports(c) {
    c.innerHTML = `<h2>Reports</h2>
        <div class="card">
            <p>Generate and Download PDF Reports</p>
            <div class="action-group">
                <button class="btn" onclick="genReport('all')"><svg class="icon" style="fill:white;"><use href="#icon-download"></use></svg> All Stock</button>
                <button class="btn" onclick="genReport('reorder')"><svg class="icon" style="fill:white;"><use href="#icon-download"></use></svg> Reorder List</button>
                <button class="btn" onclick="genReport('discard')"><svg class="icon" style="fill:white;"><use href="#icon-download"></use></svg> Discard List</button>
            </div>
        </div>`;
}

async function genReport(type) {
    const { jsPDF } = window.jspdf; 
    const doc = new jsPDF();
    
    const d = new Date();
    const dateFormatted = String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
    
    const drawFooter = function(data) {
        const pageCount = doc.internal.getNumberOfPages();
        doc.setFontSize(10);
        doc.text('Page ' + pageCount, data.settings.margin.left, doc.internal.pageSize.height - 10);
        doc.text(dateFormatted, doc.internal.pageSize.width - data.settings.margin.right, doc.internal.pageSize.height - 10, { align: 'right' });
    };
    
    if (type === 'reorder') {
        const meds = await callApi('get_mymeds');
        const filtered = meds.active.filter(m => m.stock_count < 1);
        doc.text(`MediTrack Report: REORDER LIST`, 10, 10); 
        doc.autoTable({ 
            head: [['Name', 'Strength', 'Owner', 'Current Stock']], 
            body: filtered.map(r => [r.name, (r.strength && r.strength.trim() !== '') ? r.strength : 'N/A', r.owner, 'Low Stock (0)']), 
            startY: 20,
            didDrawPage: drawFooter
        });
    } else {
        const data = await callApi('get_inventory');
        let filtered = data;
        if(type === 'discard') filtered = data.filter(r => new Date(r.expiry_date) < new Date());
        
        doc.text(`MediTrack Report: ${type.toUpperCase()}`, 10, 10); 
        doc.autoTable({ 
            head: [['Label ID', 'Name', 'Strength', 'Owner', 'Expiry']], 
            body: filtered.map(r => [r.code, r.name, (r.strength && r.strength.trim() !== '') ? r.strength : 'N/A', r.owner, r.expiry_date]), 
            startY: 20,
            didDrawPage: drawFooter
        });
    }
    
    doc.save(`meditrack_${type}.pdf`); 
}

// SYSTEM LOGIC
async function renderSystem(c) {
    const sc = await callApi('get_share_code');
    let html = `<h2>System Settings</h2>`;

    html += `
        <div class="card">
            <h3>Household Share Code</h3>
            <div class="action-group" style="margin-top:10px;">
                <input type="text" value="${sc.code}" readonly style="padding:8px; border:1px solid #ccc; border-radius:4px; font-weight:bold; width: 140px;">
                <button class="btn" onclick="navigator.clipboard.writeText('${sc.code}'); showAlert('Code Copied!');"><svg class="icon" style="fill:white;"><use href="#icon-copy"></use></svg> Copy</button>
                <button class="btn" onclick="regenerateShareCode()" title="Revoke Access"><svg class="icon" style="fill:white;"><use href="#icon-refresh"></use></svg> Revoke & Regenerate</button>
            </div>
            <p><small>Give this code to family members so they can join your household. Regenerating the code revokes access for anyone using the old one.</small></p>
        </div>`;

    html += `
        <div class="card">
            <h3>Join Household</h3>
            <p><small>Enter a share code to view and manage another household's data.</small></p>
            <div class="action-group" style="margin-top:10px;">
                <input type="text" id="joinCodeInput" placeholder="10-Char Code" maxlength="10" style="text-transform:uppercase; padding:8px; border:1px solid #ccc; border-radius:4px; width: 140px;">
                <button class="btn" onclick="joinHousehold()">Join</button>
            </div>
        </div>`;

    html += `
        <div class="card">
            <h3>Family Members</h3>
            <div style="display:flex; gap:5px; margin-bottom:10px;">
                <input type="text" id="newFamName" placeholder="Name">
                <button class="btn" onclick="addFamily()">Add</button>
            </div>
            <div id="famList">Loading...</div>
        </div>`;

    html += `
        <div class="card">
            <h3>Locations</h3>
            <div style="display:flex; gap:5px; margin-bottom:10px;">
                <input type="text" id="newLocName" placeholder="Location Name">
                <button class="btn" onclick="addLoc()">Add</button>
            </div>
            <div id="locList">Loading...</div>
        </div>`;

    if(currentUserRole === 'admin'){
        html += `<div class="card"><h3>User Management (Admin)</h3>
            <div style="display:flex; gap:5px; margin-bottom:15px; flex-wrap:wrap;">
                <input type="text" id="newUsername" placeholder="Username">
                <input type="password" id="newUserPass" placeholder="Password">
                <select id="newUserRole"><option value="user">User</option><option value="admin">Admin</option></select>
                <button class="btn" onclick="addUser()">Add</button>
            </div>
            <div id="userList"></div>
        </div>`;
    }

    html += `
        <div class="card"><h3>My Account</h3>
            <div class="action-group">
                <input type="password" id="myNewPass" placeholder="New Password" style="padding:8px; border:1px solid #ccc; border-radius:4px;">
                <button class="btn" onclick="updateMyPass()"><svg class="icon" style="fill:white;"><use href="#icon-lock"></use></svg> Change Password</button>
            </div>
        </div>
        
        <div class="card"><h3>Data Management</h3>
            <div class="action-group">
                <button class="btn" onclick="downloadBackup()"><svg class="icon" style="fill:white;"><use href="#icon-download"></use></svg> Backup JSON</button>
                <button class="btn" onclick="exportCSV()"><svg class="icon" style="fill:white;"><use href="#icon-download"></use></svg> Export CSV</button>
            </div>
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            <div class="action-group">
                <input type="file" id="restoreFile" style="padding: 5px;">
                <button class="btn" onclick="restoreBackup()"><svg class="icon" style="fill:white;"><use href="#icon-upload"></use></svg> Restore Data</button>
            </div>
        </div>`;

    c.innerHTML = html;

    loadFamily();
    loadLocations();
    if(currentUserRole === 'admin') loadUsers();
}

async function regenerateShareCode() {
    if(confirm("Are you sure? This will generate a new code and instantly revoke access for anyone using the current code. Your data will be moved to the new code.")) {
        const res = await callApi('regenerate_share_code');
        if(res.status === 'success') {
            showAlert("Share code regenerated successfully!");
            loadPage('system');
        }
    }
}

async function joinHousehold() {
    const code = document.getElementById('joinCodeInput').value;
    if(code.length !== 10) return showAlert("Code must be exactly 10 characters.");
    const res = await callApi('join_share_code', {code: code});
    if(res.status === 'success') { 
        showAlert("Joined successfully!"); 
        setTimeout(() => location.reload(), 1500); 
    } else { 
        showAlert(res.message); 
    }
}

async function loadFamily(){
    const fam = await callApi('get_family');
    document.getElementById('famList').innerHTML = fam.map(f => `
        <div class="list-item">
            <div>${e(f.name)} ${f.is_default == '1' ? '<small style="color:var(--orange); font-weight:bold;">(Default)</small>' : ''}</div> 
            <div class="action-group">
                <button class="btn-sm btn-outline" onclick="editFam(${f.id}, '${e(f.name).replace(/'/g, "\\'")}')" title="Edit Name"><svg class="icon"><use href="#icon-edit"></use></svg></button>
                ${f.is_default != '1' ? `<button class="btn-sm btn-outline" onclick="setDefaultFam(${f.id})" title="Set Default"><svg class="icon"><use href="#icon-star"></use></svg></button>` : `<button class="btn-sm btn-outline" disabled title="Default"><svg class="icon" style="fill:var(--orange)"><use href="#icon-star-filled"></use></svg></button>`}
                <button class="btn-sm btn-outline" onclick="delFam(${f.id})" title="Delete"><svg class="icon"><use href="#icon-trash"></use></svg></button>
            </div>
        </div>`).join('');
}
async function loadLocations(){
    const loc = await callApi('get_locations');
    document.getElementById('locList').innerHTML = loc.map(l => `
        <div class="list-item">
            <div>${e(l.name)} ${l.is_default == '1' ? '<small style="color:var(--orange); font-weight:bold;">(Default)</small>' : ''}</div> 
            <div class="action-group">
                ${l.is_default != '1' ? `<button class="btn-sm btn-outline" onclick="setDefaultLoc(${l.id})" title="Set Default"><svg class="icon"><use href="#icon-star"></use></svg></button>` : `<button class="btn-sm btn-outline" disabled title="Default"><svg class="icon" style="fill:var(--orange)"><use href="#icon-star-filled"></use></svg></button>`}
                <button class="btn-sm btn-outline" onclick="delLoc(${l.id})" title="Delete"><svg class="icon"><use href="#icon-trash"></use></svg></button>
            </div>
        </div>`).join('');
}

async function editFam(id, oldName) {
    const newName = prompt("Enter a new name for this family member:", oldName);
    if (newName && newName.trim() !== "" && newName !== oldName) {
        await callApi('edit_family', {id: id, name: newName, old_name: oldName});
        loadFamily();
    }
}

async function addFamily(){ await callApi('add_family', {name: document.getElementById('newFamName').value}); loadFamily(); }
async function delFam(id){ await callApi('delete_family', {id:id}); loadFamily(); }
async function setDefaultFam(id){ await callApi('set_default_family', {id:id}); loadFamily(); }

async function addLoc(){ await callApi('add_location', {name: document.getElementById('newLocName').value}); loadLocations(); }
async function delLoc(id){ await callApi('delete_location', {id:id}); loadLocations(); }
async function setDefaultLoc(id){ await callApi('set_default_location', {id:id}); loadLocations(); }

async function loadUsers() {
    const users = await callApi('get_users');
    document.getElementById('userList').innerHTML = users.map(u => `
        <div class="list-item">
            <div><strong>${e(u.username)}</strong> (${e(u.role)})</div>
            <div class="action-group">
                <button class="btn-sm btn-outline" onclick="openResetPassModal(${u.id})" title="Reset Password"><svg class="icon"><use href="#icon-lock"></use></svg></button>
                <button class="btn-sm btn-outline" onclick="delUser(${u.id})" title="Delete"><svg class="icon"><use href="#icon-trash"></use></svg></button>
            </div>
        </div>`).join('');
}
async function addUser(){ await callApi('add_user', {username: document.getElementById('newUsername').value, password: document.getElementById('newUserPass').value, role: document.getElementById('newUserRole').value}); loadUsers(); }
async function delUser(id){ await callApi('delete_user', {id:id}); loadUsers(); }
async function updateMyPass(){ await callApi('update_my_password', {password: document.getElementById('myNewPass').value}); showAlert('Password Updated'); document.getElementById('myNewPass').value=''; }

let resetUserId = null;
function openResetPassModal(id) { resetUserId = id; document.getElementById('resetPassModal').classList.add('open'); }
async function confirmResetPass() { await callApi('admin_reset_password', {id: resetUserId, password: document.getElementById('resetPassInput').value}); closeModal('resetPassModal'); showAlert("Password reset."); }

async function downloadBackup(){ const data = await callApi('backup_json'); const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([JSON.stringify(data)], {type: 'application/json'})); a.download = 'meditrack_backup.json'; a.click(); }
async function restoreBackup(){ const f = document.getElementById('restoreFile').files[0]; if(!f) return; const fd = new FormData(); fd.append('action', 'restore_json'); fd.append('file', f); await fetch(API, {method:'POST', body:fd}); showAlert('Restored!'); location.reload(); }
async function exportCSV() {
    const res = await callApi('export_csv');
    if(res && res.csv) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([res.csv], {type: 'text/csv'}));
        a.download = 'meditrack_export.csv'; a.click();
    }
}

// Startup
if(document.getElementById('appContent')) loadPage('dashboard');
</script>
</body>
</html>
