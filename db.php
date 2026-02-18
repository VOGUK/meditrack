<?php
// db.php
$db_file = 'meditrack.db';
$db = new SQLite3($db_file);
$db->exec("PRAGMA foreign_keys = ON;");

// --- Tables ---

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password TEXT,
    fullname TEXT,
    role TEXT DEFAULT 'user',
    share_code TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    is_default INTEGER DEFAULT 0,
    share_code TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS family_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    is_default INTEGER DEFAULT 0,
    share_code TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS master_meds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE
)");

$db->exec("CREATE TABLE IF NOT EXISTS inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE,
    name TEXT,
    strength TEXT,
    location TEXT,
    owner TEXT,
    expiry_date DATE,
    notes TEXT,
    status TEXT DEFAULT 'in_stock',
    date_added DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_date DATETIME,
    share_code TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS my_meds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    owner TEXT,
    status TEXT DEFAULT 'active',
    last_ordered_date DATE,
    share_code TEXT
)");

// Migrations
$tables_to_migrate = ['users', 'inventory', 'my_meds', 'locations', 'family_members'];
foreach ($tables_to_migrate as $table) {
    $cols = $db->query("PRAGMA table_info($table)");
    $hasCol = false;
    while($row = $cols->fetchArray()){ if($row['name'] === 'share_code') $hasCol = true; }
    if(!$hasCol) {
        $db->exec("ALTER TABLE $table ADD COLUMN share_code TEXT");
        $db->exec("UPDATE $table SET share_code = 'DEFAULT123' WHERE share_code IS NULL");
    }
}

// Defaults
if ($db->querySingle("SELECT count(*) FROM users") == 0) {
    $hash = password_hash('admin', PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (username, password, role, fullname, share_code) VALUES ('admin', '$hash', 'admin', 'Administrator', 'DEFAULT123')");
}

$defaultCheck = $db->querySingle("SELECT count(*) FROM locations WHERE share_code = 'DEFAULT123'");
if ($defaultCheck == 0) {
    $db->exec("INSERT INTO locations (name, is_default, share_code) VALUES ('Medicine Cupboard', 1, 'DEFAULT123'), ('Bedside Table', 0, 'DEFAULT123'), ('Kitchen', 0, 'DEFAULT123'), ('Fridge', 0, 'DEFAULT123')");
    $db->exec("INSERT INTO family_members (name, is_default, share_code) VALUES ('Me', 1, 'DEFAULT123')");
}

function populateMaster($db) {
    $meds = [
        'Aciclovir','Acrivastine','Adalimumab','Alendronic acid','Allopurinol','Alogliptin','Amitriptyline','Amlodipine','Amoxicillin','Anastrozole','Antacids','Antibiotics','Anticoagulant medicines','Antidepressants','Antifungal medicines','Antihistamines','Apixaban','Aripiprazole','Aspirin','Atenolol','Atorvastatin','Azathioprine','Azithromycin',
        'Baclofen','Beclometasone','Bendroflumethiazide','Benzoyl peroxide','Benzydamine','Beta blockers','Betahistine','Betamethasone','Bimatoprost','Bisacodyl','Bismuth subsalicylate','Bisoprolol','Brinzolamide','Budesonide','Bumetanide','Buprenorphine','Buscopan',
        'Calcipotriol','Cannabis oils','Candesartan','Carbamazepine','Carbimazole','Carbocisteine','Carmellose sodium','Carvedilol','Cefalexin','Cetirizine','Chloramphenicol','Chlorhexidine','Chlorphenamine','Cinnarizine','Ciprofloxacin','Citalopram','Clarithromycin','Clarityn','Clobetasol','Clobetasone','Clonazepam','Clonidine','Clopidogrel','Clotrimazole','Co-amoxiclav','Co-beneldopa','Co-careldopa','Co-codamol','Co-dydramol','Codeine','Colchicine','Colecalciferol','Continuous combined hormone replacement therapy','Contraceptive injections','Cyanocobalamin','Cyclizine',
        'Dapagliflozin','Decongestants','Dexamethasone','Diazepam','Diclofenac','Digoxin','Dihydrocodeine','Diltiazem','Diphenhydramine','Dipyridamole','Docusate','Domperidone','Donepezil','Doxazosin','Doxycycline','Duloxetine',
        'Edoxaban','Empagliflozin','Enalapril','Eplerenone','Epimax','Erythromycin','Escitalopram','Esomeprazole','Ezetimibe',
        'Felodipine','Fentanyl','Ferrous fumarate','Ferrous sulfate','Fexofenadine','Finasteride','Flucloxacillin','Fluconazole','Fluoxetine','Fluticasone','Folic acid','Furosemide','Fusidic acid','Fybogel',
        'Gabapentin','Gaviscon','Gliclazide','Glyceryl trinitrate','GTN',
        'Haloperidol','Herceptin','Hormone replacement therapy','HRT','Hydrocortisone','Hydroxocobalamin','Hydroxychloroquine','Hyoscine butylbromide','Hyoscine hydrobromide',
        'Ibuprofen','Imodium','Indapamide','Insulin','Irbesartan','Isosorbide mononitrate and isosorbide dinitrate','Isotretinoin capsules','Ispaghula husk',
        'Ketoconazole',
        'Lactulose','Lamotrigine','Lansoprazole','Latanoprost','Laxatives','Laxido','Lercanidipine','Letrozole','Levetiracetam','Levothyroxine','Lidocaine skin cream','Linagliptin','Lisinopril','Lithium','Loperamide','Loratadine','Lorazepam','Losartan','Lymecycline',
        'Macrogol','Mebendazole','Mebeverine','Medical cannabis','Medroxyprogesterone','Melatonin','Memantine','Mesalazine','Metformin','Methadone','Methotrexate','Methylphenidate','Metoclopramide','Metoprolol','Metronidazole','Micronised progesterone','Mirabegron','Mirtazapine','Mometasone','Montelukast','Morphine',
        'Naproxen','Nefopam','Nicorandil','Nifedipine','Nitrofurantoin','Nortriptyline','NSAIDs','Nystatin',
        'Oestrogen','Olanzapine','Omeprazole','Oxybutynin','Oxycodone',
        'Pantoprazole','Paracetamol','Paroxetine','Peppermint oil','Pepto-Bismol','Perindopril','Phenergan','Phenoxymethylpenicillin','Pravastatin','Pre-Exposure Prophylaxis','Prednisolone','Pregabalin','PrEP','Prochlorperazine','Promethazine','Propranolol','Prozac','Pseudoephedrine',
        'Quetiapine',
        'Ramipril','Ranitidine','Risperidone','Rivaroxaban','Ropinirole','Roaccutane','Rosuvastatin',
        'Salivix pastilles','Salbutamol inhalers','Senna','Sequential combined HRT','Sertraline','Sildenafil','Simeticone','Simvastatin','Sitagliptin','Sodium valproate','Solifenacin','Sotalol','Spironolactone','Statins','Steroids','Sudafed','Sulfasalazine','Sumatriptan',
        'Tadalafil','Tamsulosin','Terbinafine','Thiamine','Tibolone','Ticagrelor','Timolol eye drops','Tiotropium inhalers','Tolterodine','Topiramate','Tramadol','Tranexamic acid','Trastuzumab','Trazodone','Trimethoprim',
        'Utrogestan',
        'Vaginal oestrogen','Valproic acid','Valsartan','Varenicline','Venlafaxine','Verapamil','Viagra','Vitamin B1','Vitamin D',
        'Warfarin',
        'Zolpidem','Zopiclone','Zovirax'
    ];
    $db->exec("DELETE FROM master_meds");
    $stmt = $db->prepare("INSERT OR IGNORE INTO master_meds (name) VALUES (:name)");
    foreach ($meds as $med) {
        $stmt->bindValue(':name', $med, SQLITE3_TEXT);
        $stmt->execute();
    }
}
if ($db->querySingle("SELECT count(*) FROM master_meds") == 0) { populateMaster($db); }

$db->exec("DELETE FROM inventory WHERE status = 'trash' AND deleted_date < datetime('now', '-7 days')");
?>