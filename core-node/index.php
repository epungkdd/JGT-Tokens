<?php
/**
 * ============================================================================
 * JAGAT ARSY MASTER NODE API v11.4 (Ironclad Security + Auto Discovery)
 * ============================================================================
 * Arsitektur: Sovereign P2P Blockchain
 * Keamanan: Strict Ed25519, Anti Double-Spend, Verified Sync, Mempool GC
 * Fitur Baru: Auto-Registration ke Jaringan P2P pada First Boot
 * ============================================================================
 */

// ----------------------------------------------------------------------------
// 1. SISTEM & KONFIGURASI AWAL
// ----------------------------------------------------------------------------
ini_set('memory_limit', '1024M'); 
ini_set('max_execution_time', 1200); 
error_reporting(0); 
ini_set('display_errors', 0);
ob_start();

// SECURITY: Wajib Libsodium untuk Ed25519 murni!
if (!extension_loaded('sodium')) {
    $error_msg = 'SYSTEM HALTED: Ekstensi PHP "libsodium" WAJIB diaktifkan! ';
    $error_msg .= 'Jika pakai XAMPP: 1) Hapus titik koma pada ;extension=sodium di php.ini. ';
    $error_msg .= '2) COPY file libsodium.dll dari folder C:\xampp\php\ ke folder C:\xampp\apache\bin\. ';
    $error_msg .= '3) Restart Apache.';
    die(json_encode(['status' => 'error', 'message' => $error_msg]));
}

if (!file_exists('config.php')) {
    die(json_encode(['status' => 'error', 'message' => 'SYSTEM HALTED: File config.php tidak ditemukan! Buat file config.php terlebih dahulu.']));
}
require_once 'config.php';

// Global Exception Handler
set_exception_handler(function($e) {
    ob_clean();
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(['status' => 'error', 'message' => 'SERVER_EXCEPTION: ' . $e->getMessage()]);
    exit();
});

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Node-Signature, ngrok-skip-browser-warning");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

// ----------------------------------------------------------------------------
// 2. KONEKSI DATABASE & SETUP TABEL (AUTO-MIGRATE)
// ----------------------------------------------------------------------------
function getDBConnection() {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Memaksa penggunaan Prepared Statement murni
    return $db;
}
$pdo = getDBConnection();

// Create Core Tables
$tables = [
    "CREATE TABLE IF NOT EXISTS `blocks` (`index` INT PRIMARY KEY, `timestamp` BIGINT, `nonce` BIGINT, `previous_hash` VARCHAR(64), `hash` VARCHAR(64))",
    "CREATE TABLE IF NOT EXISTS `transactions` (`txid` VARCHAR(128) PRIMARY KEY, `block_index` INT, `sender` VARCHAR(128), `recipient` VARCHAR(128), `amount` DECIMAL(16,4), `fee` DECIMAL(16,4) DEFAULT 0, `timestamp` BIGINT, `signature` TEXT, `data` TEXT)",
    "CREATE TABLE IF NOT EXISTS `mempool` (`txid` VARCHAR(128) PRIMARY KEY, `sender` VARCHAR(128), `recipient` VARCHAR(128), `amount` DECIMAL(16,4), `fee` DECIMAL(16,4) DEFAULT 0, `timestamp` BIGINT, `signature` TEXT, `data` TEXT)",
    "CREATE TABLE IF NOT EXISTS `peers` (`url` VARCHAR(255) PRIMARY KEY, `added_at` BIGINT, `status` VARCHAR(20) DEFAULT 'PENDING')",
    "CREATE TABLE IF NOT EXISTS `wallet_balances` (`address` VARCHAR(128) PRIMARY KEY, `balance` DECIMAL(16,4) DEFAULT 0, `last_updated` BIGINT DEFAULT 0)",
    "CREATE TABLE IF NOT EXISTS `active_miners` (`address` VARCHAR(128) PRIMARY KEY, `last_seen` BIGINT)",
    "CREATE TABLE IF NOT EXISTS `nfts` (`token_id` VARCHAR(128) PRIMARY KEY, `creator` VARCHAR(128), `owner` VARCHAR(128), `name` VARCHAR(255), `description` TEXT, `image_url` VARCHAR(500), `created_at` BIGINT, `price` DECIMAL(16,4) DEFAULT 0, `is_for_sale` TINYINT(1) DEFAULT 0)",
    "CREATE TABLE IF NOT EXISTS `system_state` (`key_name` VARCHAR(50) PRIMARY KEY, `value` TEXT)" 
];
foreach($tables as $sql) { $pdo->exec($sql); }

// Optimasi Database (Indexing)
$indexes = [
    "CREATE INDEX idx_tx_sender ON transactions(sender)",
    "CREATE INDEX idx_tx_recipient ON transactions(recipient)",
    "CREATE INDEX idx_mempool_sender ON mempool(sender)",
    "CREATE INDEX idx_nft_owner ON nfts(owner)"
];
foreach($indexes as $sql) { try { $pdo->exec($sql); } catch(Exception $e){} }

// Setup NFT Directory
$nft_dir = __DIR__ . '/nfts';
if (!is_dir($nft_dir)) { mkdir($nft_dir, 0777, true); }

// Genesis Block Injection
if ($pdo->query("SELECT COUNT(*) FROM blocks")->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO blocks VALUES (1, ".time().", 0, '0', '0000genesis_block_jagat_arsy_fixed')");
}

// ----------------------------------------------------------------------------
// 3. PARAMETER TOKENOMICS, DYNAMIC DIFFICULTY & UTILITIES
// ----------------------------------------------------------------------------
$total_blocks = (int)$pdo->query("SELECT COUNT(*) FROM blocks")->fetchColumn();
$mining_reward = 50 / pow(2, floor($total_blocks / 210000));
$network_fee = 0.01; // Base Fee

// ALGORITMA DYNAMIC DIFFICULTY ADJUSTMENT (DDA) - SECURED (DB Driven)
function get_current_difficulty($pdo) {
    $TARGET_BLOCK_TIME = 60; 
    $ADJUSTMENT_INTERVAL = 10; 
    $MIN_DIFFICULTY = 4; 
    $MAX_DIFFICULTY = 6; 

    $current_index = (int)$pdo->query("SELECT MAX(`index`) FROM blocks")->fetchColumn();

    $stmt_cache = $pdo->query("SELECT value FROM system_state WHERE key_name = 'difficulty_cache'");
    $cache_val = $stmt_cache->fetchColumn();

    if (!$cache_val) {
        $data = json_encode(['difficulty' => $MIN_DIFFICULTY, 'last_adjusted_block' => $current_index]);
        $pdo->prepare("REPLACE INTO system_state (key_name, value) VALUES ('difficulty_cache', ?)")->execute([$data]);
        return $MIN_DIFFICULTY;
    }

    $data = json_decode($cache_val, true);
    $current_diff = $data['difficulty'] ?? $MIN_DIFFICULTY;
    $last_adjusted = $data['last_adjusted_block'] ?? 0;

    if ($current_index - $last_adjusted < $ADJUSTMENT_INTERVAL) {
        return $current_diff;
    }

    $stmt = $pdo->prepare("SELECT timestamp, hash FROM blocks WHERE `index` = ?");
    $stmt->execute([$current_index]);
    $last_block = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$last_block) return $MIN_DIFFICULTY;

    $last_block_time = (int)$last_block['timestamp'];
    $last_hash = $last_block['hash'];

    $stmt->execute([$current_index - $ADJUSTMENT_INTERVAL]);
    $old_block_time = (int)$stmt->fetchColumn();

    $time_taken = $last_block_time - $old_block_time;
    $expected_time = $TARGET_BLOCK_TIME * $ADJUSTMENT_INTERVAL; 

    $current_diff_real = 0;
    for ($i = 0; $i < strlen($last_hash); $i++) {
        if ($last_hash[$i] === '0') $current_diff_real++;
        else break;
    }
    if ($current_diff_real < $MIN_DIFFICULTY) $current_diff_real = $MIN_DIFFICULTY;

    $new_diff = $current_diff_real;
    if ($time_taken < ($expected_time / 2)) {
        $new_diff = $current_diff_real + 1; 
    } elseif ($time_taken > ($expected_time * 2)) {
        $new_diff = $current_diff_real - 1; 
    }

    if ($new_diff < $MIN_DIFFICULTY) $new_diff = $MIN_DIFFICULTY;
    if ($new_diff > $MAX_DIFFICULTY) $new_diff = $MAX_DIFFICULTY;

    $new_data = json_encode(['difficulty' => $new_diff, 'last_adjusted_block' => $current_index]);
    $pdo->prepare("REPLACE INTO system_state (key_name, value) VALUES ('difficulty_cache', ?)")->execute([$new_data]);

    return $new_diff;
}

$difficulty = get_current_difficulty($pdo);

function getBaseUrl() {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
    return ($is_https ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
}

function fetchJsonUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch); curl_close($ch); return $res;
}

function isValidJgtAddress($address) {
    return preg_match('/^jgt1q[a-z0-9]{35,}$/i', $address);
}

function verifyEd25519Signature($senderAddress, $recipient, $amount, $timestamp, $payload, $signatureHex) {
    $pubKeyHex = substr($senderAddress, 5); 
    if (strlen($pubKeyHex) !== 64) return false; 

    try {
        $message = $senderAddress . $recipient . $amount . $timestamp . $payload;
        $sigBin = hex2bin($signatureHex);
        $pubKeyBin = hex2bin($pubKeyHex);
        return sodium_crypto_sign_verify_detached($sigBin, $message, $pubKeyBin);
    } catch (Exception $e) {
        return false;
    }
}

// ----------------------------------------------------------------------------
// 4. API ROUTER MAIN SWITCH
// ----------------------------------------------------------------------------
$action = trim($_GET['action'] ?? '');
$raw_input = file_get_contents("php://input");

if ($action !== '') {
    ob_clean();
    header("Content-Type: application/json");
    
    // SECURITY: Mempool Garbage Collector
    try { $pdo->exec("DELETE FROM mempool WHERE timestamp < " . (time() - 86400)); } catch(Exception $e){}

    switch ($action) {
        
        // --- A. DATA EXPLORER & STATISTIK ---
        case 'explorer_stats':
            $active_limit = time() - 300; 
            $real_miners = (int)$pdo->query("SELECT COUNT(*) FROM active_miners WHERE last_seen > $active_limit")->fetchColumn();
            
            echo json_encode(["status" => "success", "data" => [
                "total_blocks" => $total_blocks, 
                "total_txs" => (int)$pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn(), 
                "total_wallets" => (int)$pdo->query("SELECT COUNT(*) FROM wallet_balances")->fetchColumn(), 
                "total_supply" => (float)$pdo->query("SELECT SUM(balance) FROM wallet_balances")->fetchColumn(), 
                "mempool_size" => (int)$pdo->query("SELECT COUNT(*) FROM mempool")->fetchColumn(), 
                "total_nfts" => (int)$pdo->query("SELECT COUNT(*) FROM nfts")->fetchColumn(), 
                "difficulty" => $difficulty, 
                "current_reward" => $mining_reward,
                "active_miners" => $real_miners
            ]]); 
            break;

        case 'richlist':
            $richlist = $pdo->query("SELECT address, balance FROM wallet_balances WHERE balance > 0 ORDER BY balance DESC LIMIT 100")->fetchAll();
            foreach($richlist as &$r) { $r['balance'] = (float)$r['balance']; }
            echo json_encode(["status" => "success", "data" => $richlist]); 
            break;

        case 'recent_wallets':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $stmt = $pdo->prepare("SELECT address, balance, last_updated FROM wallet_balances WHERE last_updated > 0 ORDER BY last_updated DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT); $stmt->execute();
            $recent = $stmt->fetchAll();
            foreach($recent as &$r) { $r['balance'] = (float)$r['balance']; }
            echo json_encode(["status" => "success", "data" => $recent]); 
            break;

        case 'tx_info':
            $txid = $_GET['txid'] ?? '';
            if (strlen($txid) !== 64 && strlen($txid) !== 128) throw new Exception("Format TxID tidak valid.");
            
            $stmt = $pdo->prepare("SELECT * FROM mempool WHERE txid = ?"); $stmt->execute([$txid]);
            if($tx = $stmt->fetch()) {
                $tx['status'] = 'Mempool'; $tx['amount'] = (float)$tx['amount']; $tx['fee'] = (float)$tx['fee'];
                die(json_encode(["status" => "success", "data" => $tx]));
            }
            
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE txid = ?"); $stmt->execute([$txid]);
            if($tx = $stmt->fetch()) {
                $tx['status'] = 'Terkonfirmasi (Blok #' . $tx['block_index'] . ')'; $tx['amount'] = (float)$tx['amount']; $tx['fee'] = (float)$tx['fee'];
                die(json_encode(["status" => "success", "data" => $tx]));
            }
            echo json_encode(["status" => "error", "message" => "TxID tidak ditemukan"]); 
            break;

        case 'address_info':
            $addr = $_GET['address'] ?? '';
            if (!isValidJgtAddress($addr) && !str_starts_with(strtoupper($addr), 'SISTEM')) throw new Exception("Format Address Invalid");
            
            $stmt_bal = $pdo->prepare("SELECT balance FROM wallet_balances WHERE address = ?");
            $stmt_bal->execute([$addr]);
            $bal = (float)$stmt_bal->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE sender = ? OR recipient = ? ORDER BY timestamp DESC LIMIT 100");
            $stmt->execute([$addr, $addr]); $txs = $stmt->fetchAll();
            foreach($txs as &$t) { $t['amount'] = (float)$t['amount']; $t['fee'] = (float)$t['fee']; }
            echo json_encode(["status" => "success", "data" => ["address" => $addr, "balance" => $bal, "transactions" => $txs]]); 
            break;

        case 'chain': 
            $limit = min(isset($_GET['limit']) ? (int)$_GET['limit'] : 500, 1000);
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $stmt = $pdo->prepare("SELECT * FROM `blocks` ORDER BY `index` ASC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT); $stmt->bindValue(2, $offset, PDO::PARAM_INT); $stmt->execute();
            $blocks = $stmt->fetchAll();
            
            foreach ($blocks as &$block) {
                $stmt_tx = $pdo->prepare("SELECT * FROM `transactions` WHERE block_index = ?");
                $stmt_tx->execute([$block['index']]); $block['transactions'] = $stmt_tx->fetchAll();
                foreach($block['transactions'] as &$t) { $t['amount'] = (float)$t['amount']; $t['fee'] = (float)$t['fee']; }
            }
            echo json_encode($blocks); 
            break;

        // --- B. NFT SYSTEM ---
        case 'user_nfts':
            $addr = $_GET['address'] ?? '';
            if (!isValidJgtAddress($addr)) throw new Exception("Format Address Invalid");
            
            $stmt = $pdo->prepare("SELECT * FROM nfts WHERE owner = ? ORDER BY created_at DESC"); $stmt->execute([$addr]);
            $nfts = $stmt->fetchAll(); foreach($nfts as &$nft) { $nft['price'] = (float)$nft['price']; }
            echo json_encode(["status" => "success", "data" => $nfts]); 
            break;

        case 'market_nfts':
            $nfts = $pdo->query("SELECT * FROM nfts WHERE is_for_sale = 1 OR price > 0 ORDER BY created_at DESC LIMIT 100")->fetchAll();
            foreach($nfts as &$nft) { $nft['price'] = (float)$nft['price']; }
            echo json_encode(["status" => "success", "data" => $nfts]); 
            break;

        case 'upload_nft':
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Gagal memuat file gambar.");
            }
            $file = $_FILES['image'];
            
            if ($file['size'] > 5 * 1024 * 1024) throw new Exception("Ukuran maksimal file adalah 5MB.");
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime_type, $allowed_mimes)) throw new Exception("MIME Type ditolak. Hanya gambar murni yang diizinkan.");
            if (!getimagesize($file['tmp_name'])) throw new Exception("Data file korup atau bukan gambar.");

            $ext = 'png'; 
            if ($mime_type === 'image/jpeg') $ext = 'jpg';
            elseif ($mime_type === 'image/webp') $ext = 'webp';
            elseif ($mime_type === 'image/gif') $ext = 'gif';

            $filename = 'nft_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], __DIR__ . '/nfts/' . $filename)) {
                echo json_encode(["status" => "success", "image_url" => getBaseUrl() . '/nfts/' . $filename]);
            } else {
                throw new Exception("Gagal menyimpan gambar di server.");
            }
            break;

        // --- C. MINING & MEMPOOL ---
        case 'mempool':
            $mempool = $pdo->query("SELECT * FROM mempool ORDER BY timestamp ASC")->fetchAll();
            foreach($mempool as &$tx) { $tx['amount'] = (float)$tx['amount']; $tx['fee'] = (float)$tx['fee']; }
            echo json_encode(["status" => "success", "mempool" => $mempool]); 
            break;

        case 'balance':
            $addr = $_GET['address'] ?? '';
            if (!isValidJgtAddress($addr) && !str_starts_with(strtoupper($addr), 'SISTEM')) throw new Exception("Format Address Invalid");
            
            $stmt_conf = $pdo->prepare("SELECT balance FROM wallet_balances WHERE address = ?");
            $stmt_conf->execute([$addr]);
            $conf = (float)$stmt_conf->fetchColumn();
            
            $stmt_pend = $pdo->prepare("SELECT SUM(amount + fee) FROM mempool WHERE sender = ?");
            $stmt_pend->execute([$addr]);
            $pend = (float)$stmt_pend->fetchColumn();
            
            echo json_encode(["status" => "success", "address" => $addr, "balance" => max(0, $conf - $pend)]); 
            break;

        case 'get_job':
            $miner_req = $_GET['miner'] ?? '';
            if (!empty($miner_req) && isValidJgtAddress($miner_req)) { 
                $pdo->prepare("REPLACE INTO active_miners (address, last_seen) VALUES (?, ?)")->execute([$miner_req, time()]); 
            }
            
            $last = $pdo->query("SELECT * FROM `blocks` ORDER BY `index` DESC LIMIT 1")->fetch();
            $mempool = $pdo->query("SELECT * FROM `mempool` ORDER BY `timestamp` ASC")->fetchAll();
            $norm_mempool = [];
            
            foreach($mempool as $tx) {
                $ntx = ['amount' => (float)$tx['amount'], 'data' => (string)($tx['data'] ?? ''), 'fee' => (float)$tx['fee'], 'recipient' => (string)$tx['recipient'], 'sender' => (string)$tx['sender'], 'signature' => (string)$tx['signature'], 'timestamp' => (int)$tx['timestamp'], 'txid' => (string)$tx['txid']];
                ksort($ntx); $norm_mempool[] = $ntx;
            }
            echo json_encode(["status" => "success", "job" => ["index" => (int)$last['index'] + 1, "timestamp" => time(), "transactions" => $norm_mempool, "previous_hash" => $last['hash'], "difficulty" => $difficulty]]); 
            break;

        case 'send_tx':
            $data = json_decode($raw_input, true);
            if(!isset($data['sender'], $data['recipient'], $data['amount'])) throw new Exception("Payload Tidak Lengkap.");
            
            $sender = $data['sender']; 
            $recipient = $data['recipient'];
            $amount = (float)$data['amount']; 
            $timestamp = (int)($data['timestamp'] ?? time());
            $signature = $data['signature'] ?? 'Unsigned'; 
            $data_payload = isset($data['data']) ? (string)$data['data'] : '';
            
            if (strlen($data_payload) > 1024) throw new Exception("SECURITY BLOCK: Payload data terlalu besar (Max 1024 bytes).");
            if (!isValidJgtAddress($sender)) throw new Exception("SECURITY BLOCK: Format Pengirim Tidak Valid.");
            if (!isValidJgtAddress($recipient) && !str_starts_with(strtoupper($recipient), 'SISTEM')) throw new Exception("SECURITY BLOCK: Format Penerima Tidak Valid.");
            if ($amount <= 0) throw new Exception("SECURITY BLOCK: Nilai Amount harus lebih besar dari 0.");
            if (strpos(strtoupper($sender), 'SISTEM') === 0 || strpos(strtoupper($sender), 'FAUCET') !== false) { 
                throw new Exception("SECURITY BREACH: Akses menggunakan identitas sistem dilarang."); 
            }

            $mempool_size = (int)$pdo->query("SELECT COUNT(*) FROM mempool")->fetchColumn();
            if ($mempool_size >= 2000) throw new Exception("Jaringan Sibuk: Mempool telah mencapai kapasitas maksimal (2000 TX). Coba lagi nanti.");

            if (!verifyEd25519Signature($sender, $recipient, $amount, $timestamp, $data_payload, $signature)) {
                throw new Exception("SECURITY BREACH: Tanda Tangan Digital (Signature) Palsu atau Tidak Valid!");
            }
            
            $required_fee = 0.01; 
            if (!empty($data_payload)) {
                $p_obj = json_decode($data_payload, true);
                if (isset($p_obj['jgt_action'])) {
                    if ($p_obj['jgt_action'] === 'mint_nft') $required_fee = 50.0;
                    elseif ($p_obj['jgt_action'] === 'transfer_nft') $required_fee = 10.0;
                    elseif ($p_obj['jgt_action'] === 'list_nft' || $p_obj['jgt_action'] === 'delist_nft') $required_fee = 5.0;
                    elseif ($p_obj['jgt_action'] === 'buy_offer') $required_fee = 10.0;
                }
            }
            
            $stmt_conf = $pdo->prepare("SELECT balance FROM wallet_balances WHERE address = ?");
            $stmt_conf->execute([$sender]);
            $conf = (float)$stmt_conf->fetchColumn();

            $stmt_pend = $pdo->prepare("SELECT SUM(amount + fee) FROM mempool WHERE sender = ?");
            $stmt_pend->execute([$sender]);
            $pend = (float)$stmt_pend->fetchColumn();
            
            if (($conf - $pend) < ($amount + $required_fee)) throw new Exception("Saldo Tidak Mencukupi (Termasuk Gas Fee $required_fee JGT).");
            
            $txid = hash('sha256', $sender . $recipient . $amount . $timestamp . $signature . $data_payload);
            
            $cek = $pdo->prepare("SELECT txid FROM mempool WHERE txid = ?"); $cek->execute([$txid]);
            if ($cek->fetch()) die(json_encode(["status" => "success"]));

            $pdo->prepare("INSERT INTO mempool VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$txid, $sender, $recipient, $amount, $required_fee, $timestamp, $signature, $data_payload]);
            echo json_encode(["status" => "success", "txid" => $txid]); 
            break;

        case 'submit_block':
            $data = json_decode($raw_input, true);
            $block = $data['block'] ?? null; 
            $miner = $data['miner_address'] ?? '';
            
            if (!isValidJgtAddress($miner)) throw new Exception("Miner Address Invalid.");

            $last = $pdo->query("SELECT * FROM `blocks` ORDER BY `index` DESC LIMIT 1")->fetch();

            if (!$block) throw new Exception("Data block kosong.");
            if ((int)$block['index'] <= (int)$last['index'] || $block['previous_hash'] !== $last['hash']) { 
                die(json_encode(["status" => "out_of_sync", "message" => "Node tertinggal. Sync diperlukan."])); 
            }

            $norm_txs = []; $txids = [];
            if (isset($block['transactions']) && is_array($block['transactions'])) {
                foreach($block['transactions'] as $tx) {
                    if (strpos(strtoupper($tx['sender']), 'SISTEM_MASTER') === 0 || $tx['signature'] === 'REWARD' || $tx['signature'] === 'PARTICIPATION_REWARD') { continue; }
                    if (strpos(strtoupper($tx['sender']), 'SISTEM') === 0 || strpos(strtoupper($tx['sender']), 'FAUCET') !== false) { throw new Exception("SECURITY BREACH: TX Ilegal."); }
                    
                    $sAmount = (float)$tx['amount'];
                    $sFee = (float)$tx['fee'];
                    if ($sAmount <= 0 || $sFee < 0) throw new Exception("SECURITY BREACH: Manipulasi Amount di Dalam Blok.");

                    $data_payload = (string)($tx['data'] ?? '');
                    if (!verifyEd25519Signature((string)$tx['sender'], (string)$tx['recipient'], $sAmount, (int)$tx['timestamp'], $data_payload, (string)$tx['signature'])) {
                        throw new Exception("SECURITY BREACH: Blok mengandung transaksi dengan tanda tangan palsu!");
                    }

                    $ntx = ['amount' => $sAmount, 'data' => $data_payload, 'fee' => $sFee, 'recipient' => (string)$tx['recipient'], 'sender' => (string)$tx['sender'], 'signature' => (string)$tx['signature'], 'timestamp' => (int)$tx['timestamp'], 'txid' => (string)$tx['txid']];
                    ksort($ntx); $norm_txs[] = $ntx; $txids[] = (string)$tx['txid'];
                }
            }

            $merkle_root = empty($txids) ? '0' : hash('sha256', implode(',', $txids));
            $calc_hash = hash('sha256', "{$block['index']}:{$block['previous_hash']}:{$block['timestamp']}:{$merkle_root}:{$block['nonce']}");
            if (substr($calc_hash, 0, $difficulty) !== str_repeat("0", $difficulty)) throw new Exception("PoW Tidak Valid. (Dibutuhkan $difficulty angka nol)");

            try {
                $pdo->beginTransaction();
                
                $stmt_b = $pdo->prepare("INSERT IGNORE INTO blocks VALUES (?,?,?,?,?)");
                $stmt_b->execute([(int)$block['index'], (int)$block['timestamp'], (int)$block['nonce'], (string)$block['previous_hash'], $calc_hash]);
                if ($stmt_b->rowCount() === 0) { $pdo->rollBack(); die(json_encode(["status" => "error", "message" => "Blok sudah ditambang."])); }
                
                $stmt_tx = $pdo->prepare("INSERT IGNORE INTO transactions VALUES (?,?,?,?,?,?,?,?,?)");
                $update_bal = $pdo->prepare("INSERT INTO wallet_balances (address, balance, last_updated) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = balance + ?, last_updated = ?");
                $stmt_del_mempool = $pdo->prepare("DELETE FROM mempool WHERE txid = ?");

                $fees = 0;
                $stmt_cek_bal = $pdo->prepare("SELECT balance FROM wallet_balances WHERE address = ? FOR UPDATE");

                foreach($norm_txs as $tx) {
                    $stmt_cek_bal->execute([$tx['sender']]);
                    $current_sender_bal = (float)$stmt_cek_bal->fetchColumn();
                    $required_deduction = (float)$tx['amount'] + (float)$tx['fee'];

                    if ($current_sender_bal < $required_deduction) {
                        throw new Exception("DOUBLE SPEND TERDETEKSI: Saldo {$tx['sender']} tidak mencukupi untuk TX {$tx['txid']}. Blok ditolak!");
                    }

                    $stmt_tx->execute([$tx['txid'], $block['index'], $tx['sender'], $tx['recipient'], $tx['amount'], $tx['fee'], $tx['timestamp'], $tx['signature'], $tx['data']]);
                    $stmt_del_mempool->execute([$tx['txid']]); 
                    $fees += (float)$tx['fee'];
                    
                    $v_out = -$required_deduction;
                    $update_bal->execute([$tx['sender'], $v_out, $tx['timestamp'], $v_out, $tx['timestamp']]);
                    $update_bal->execute([$tx['recipient'], (float)$tx['amount'], $tx['timestamp'], (float)$tx['amount'], $tx['timestamp']]);
                }
                
                $actual_mining_reward = ($mining_reward < 0.00000001) ? 0 : $mining_reward;
                $miner_fee_share = $fees * 0.8;
                $participants_fee_share = $fees * 0.2;
                
                $reward_total = $actual_mining_reward + $miner_fee_share;
                if ($reward_total > 0) {
                    $reward_txid = hash('sha256', 'reward' . time() . $miner);
                    $stmt_tx->execute([$reward_txid, $block['index'], 'SISTEM_MASTER', $miner, $reward_total, 0, time(), 'REWARD', '']);
                    $update_bal->execute([$miner, $reward_total, time(), $reward_total, time()]);
                }
                
                if ($participants_fee_share > 0) {
                    $active_limit = time() - 300; 
                    $stmt_active = $pdo->prepare("SELECT address FROM active_miners WHERE last_seen > ? AND address != ?");
                    $stmt_active->execute([$active_limit, $miner]);
                    $other_miners = $stmt_active->fetchAll(PDO::FETCH_COLUMN);
            
                    if (count($other_miners) > 0) {
                        $share_per_miner = $participants_fee_share / count($other_miners);
                        foreach($other_miners as $om) {
                            if(isValidJgtAddress($om)){
                                $p_txid = hash('sha256', 'part_reward' . time() . $om . uniqid());
                                $stmt_tx->execute([$p_txid, $block['index'], 'SISTEM_MASTER', $om, $share_per_miner, 0, time(), 'PARTICIPATION_REWARD', '']);
                                $update_bal->execute([$om, $share_per_miner, time(), $share_per_miner, time()]);
                            }
                        }
                    } else {
                        $treasury_txid = hash('sha256', 'treasury' . time() . 'SISTEM_TREASURY');
                        $stmt_tx->execute([$treasury_txid, $block['index'], 'SISTEM_MASTER', 'SISTEM_TREASURY', $participants_fee_share, 0, time(), 'FEE_BURN_OR_TREASURY', '']);
                        $update_bal->execute(['SISTEM_TREASURY', $participants_fee_share, time(), $participants_fee_share, time()]);
                    }
                }

                $pdo->commit();
                echo json_encode(["status" => "success", "message" => "Blok sah. Reward dikirim!"]);
            } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; } 
            break;

        case 'sync':
            $peers = array_unique(array_merge($pdo->query("SELECT url FROM peers WHERE status = 'VALIDATED'")->fetchAll(PDO::FETCH_COLUMN), ["https://api.kcln.my.id/index.php"]));
            $max_length = $total_blocks; $longest_chain_url = null;
            
            foreach ($peers as $url) {
                if (strpos($url, $_SERVER['HTTP_HOST']) !== false) continue;
                $clean_url = rtrim($url, '/') . (strpos($url, '.php') ? '' : '/index.php');
                $stats_json = fetchJsonUrl($clean_url . "?action=explorer_stats");
                if ($stats_json) {
                    $stats = json_decode($stats_json, true);
                    if (isset($stats['status']) && $stats['status'] == 'success' && $stats['data']['total_blocks'] > $max_length) {
                        $max_length = $stats['data']['total_blocks']; $longest_chain_url = $clean_url;
                    }
                }
            }

            if ($longest_chain_url && $max_length > $total_blocks) {
                $chunk_size = 100; $total_downloaded = 0;
                
                for ($offset = $total_blocks; $offset < $max_length; $offset += $chunk_size) {
                    $chain_json = fetchJsonUrl($longest_chain_url . "?action=chain&limit={$chunk_size}&offset={$offset}");
                    $chunk_blocks = json_decode($chain_json, true);
                    
                    try { $pdo->query("SELECT 1"); } catch (Exception $e) { $pdo = getDBConnection(); }

                    if (!$chunk_blocks || !is_array($chunk_blocks) || count($chunk_blocks) == 0) break; 
                    
                    usort($chunk_blocks, function($a, $b) { return $a['index'] <=> $b['index']; });

                    foreach ($chunk_blocks as $block) {
                        if ($block['index'] <= $total_blocks) continue;

                        $txids = [];
                        if (isset($block['transactions']) && is_array($block['transactions'])) {
                            foreach($block['transactions'] as $tx) {
                                if (strpos(strtoupper($tx['sender']), 'SISTEM_MASTER') === 0 || $tx['signature'] === 'REWARD' || $tx['signature'] === 'PARTICIPATION_REWARD') { 
                                    continue; 
                                }

                                if (!str_starts_with(strtoupper($tx['sender']), 'SISTEM')) {
                                    $data_payload = (string)($tx['data'] ?? '');
                                    if (!verifyEd25519Signature((string)$tx['sender'], (string)$tx['recipient'], (float)$tx['amount'], (int)$tx['timestamp'], $data_payload, (string)$tx['signature'])) {
                                        die(json_encode(["status" => "error", "message" => "Node Remote Jahat: Memuat transaksi palsu! Sync dihentikan."]));
                                    }
                                }
                                $txids[] = (string)$tx['txid'];
                            }
                        }

                        $merkle_root = empty($txids) ? '0' : hash('sha256', implode(',', $txids));
                        $calc_hash = hash('sha256', "{$block['index']}:{$block['previous_hash']}:{$block['timestamp']}:{$merkle_root}:{$block['nonce']}");
                        if ($calc_hash !== $block['hash']) {
                            die(json_encode(["status" => "error", "message" => "Node Remote Jahat: Hash blok salah! Sync dihentikan."]));
                        }

                        try {
                            $pdo->beginTransaction();
                            $stmt_b = $pdo->prepare("INSERT IGNORE INTO blocks VALUES (?,?,?,?,?)");
                            $stmt_tx = $pdo->prepare("INSERT IGNORE INTO transactions VALUES (?,?,?,?,?,?,?,?,?)");
                            $update_bal = $pdo->prepare("INSERT INTO wallet_balances (address, balance, last_updated) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE balance = balance + ?, last_updated = ?");
                            $stmt_cek_bal = $pdo->prepare("SELECT balance FROM wallet_balances WHERE address = ? FOR UPDATE");

                            $stmt_b->execute([$block['index'], $block['timestamp'], $block['nonce'], $block['previous_hash'], $block['hash']]);
                            if ($stmt_b->rowCount() > 0 && isset($block['transactions'])) {
                                foreach ($block['transactions'] as $tx) {
                                    if (strpos(strtoupper($tx['sender']), 'SISTEM') !== 0 && strpos(strtoupper($tx['sender']), 'FAUCET') === false) {
                                        $stmt_cek_bal->execute([$tx['sender']]);
                                        $current_bal = (float)$stmt_cek_bal->fetchColumn();
                                        $deduction = (float)$tx['amount'] + (float)$tx['fee'];
                                        
                                        if ($current_bal < $deduction) {
                                            throw new Exception("Saldo tidak cukup saat sync blok {$block['index']} untuk TX {$tx['txid']}");
                                        }

                                        $v_out = -$deduction;
                                        $update_bal->execute([$tx['sender'], $v_out, $tx['timestamp'], $v_out, $tx['timestamp']]);
                                    }
                                    
                                    $stmt_tx->execute([$tx['txid'], $block['index'], $tx['sender'], $tx['recipient'], $tx['amount'], $tx['fee'], $tx['timestamp'], $tx['signature'], $tx['data']]);
                                    $update_bal->execute([$tx['recipient'], (float)$tx['amount'], $tx['timestamp'], (float)$tx['amount'], $tx['timestamp']]);
                                }
                            }
                            $pdo->commit(); 
                            $total_downloaded++;
                            $total_blocks = $block['index']; 
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            die(json_encode(["status" => "error", "message" => "Sync gagal pada blok {$block['index']}: " . $e->getMessage()]));
                        }
                    }
                }
                echo json_encode(["status" => "success", "message" => "Sync Selesai! (+$total_downloaded blok ditarik dari node lain secara aman)."]);
            } else { 
                echo json_encode(["status" => "success", "message" => "Server up-to-date. Tidak ada blok baru."]); 
            }
            break;

        case 'register_node':
            $node_url = $_GET['url'] ?? '';
            if (filter_var($node_url, FILTER_VALIDATE_URL) && strpos($node_url, 'http') === 0) {
                $clean_url = rtrim($node_url, '/') . (strpos($node_url, '.php') ? '' : '/index.php');
                if (strpos($clean_url, $_SERVER['HTTP_HOST']) !== false) {
                    die(json_encode(["status" => "error", "message" => "Tidak dapat mendaftarkan node sendiri."]));
                }

                $stats_json = fetchJsonUrl($clean_url . "?action=explorer_stats");
                if ($stats_json) {
                    $stats = json_decode($stats_json, true);
                    if (isset($stats['status']) && $stats['status'] == 'success') {
                        $pdo->prepare("INSERT IGNORE INTO peers (url, added_at, status) VALUES (?, ?, 'VALIDATED')")->execute([$clean_url, time()]);
                        die(json_encode(["status" => "success", "message" => "Node berhasil diverifikasi dan didaftarkan ke jaringan P2P Global!"]));
                    }
                }
            }
            echo json_encode(["status" => "error", "message" => "Pendaftaran ditolak. Node tidak valid atau tidak dapat dijangkau (Pastikan Node tidak di Localhost)."]);
            break;

        // FUNGSI LOKAL: Menyimpan status registrasi agar layar setup tidak muncul terus
        case 'set_registered':
            $pdo->prepare("REPLACE INTO system_state (key_name, value) VALUES ('is_registered', '1')")->execute();
            echo json_encode(["status" => "success", "message" => "Status setup First-Boot tersimpan."]);
            break;

        case 'prune_ledger':
            $keep_blocks = 10000;
            $current_index = (int)$pdo->query("SELECT MAX(`index`) FROM blocks")->fetchColumn();
            $threshold = $current_index - $keep_blocks;
            
            if ($threshold > 1) { 
                try {
                    $pdo->beginTransaction();
                    $stmt_b = $pdo->prepare("DELETE FROM blocks WHERE `index` < ? AND `index` > 1");
                    $stmt_b->execute([$threshold]);
                    $deleted_blocks = $stmt_b->rowCount();
                    
                    $stmt_t = $pdo->prepare("DELETE FROM transactions WHERE block_index < ? AND block_index > 1");
                    $stmt_t->execute([$threshold]);
                    $deleted_txs = $stmt_t->rowCount();
                    
                    $pdo->commit();
                    echo json_encode([
                        "status" => "success", 
                        "message" => "Pruning selesai! Menghemat database.",
                        "data_deleted" => ["blocks" => $deleted_blocks, "transactions" => $deleted_txs]
                    ]);
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    echo json_encode(["status" => "error", "message" => "Pruning gagal: " . $e->getMessage()]);
                }
            } else {
                echo json_encode(["status" => "success", "message" => "Pruning dibatalkan. Jumlah blok belum mencapai batas pemangkasan."]);
            }
            break;
            
        case 'get_nodes_list': 
            $dynamic_peers = $pdo->query("SELECT url FROM peers WHERE status = 'VALIDATED'")->fetchAll(PDO::FETCH_COLUMN);
            $static_peers = file_exists('node.json') ? json_decode(file_get_contents('node.json'), true) : ["https://api.kcln.my.id/index.php"];
            $all_peers = array_values(array_unique(array_merge($static_peers, $dynamic_peers)));
            echo json_encode($all_peers); 
            break;

        default: 
            echo json_encode(["status" => "active", "version" => "11.4 (Ironclad Security Core)"]);
    }
    exit();
}
ob_end_flush();

// ============================================================================
// 5. MASTER NODE VISUAL DASHBOARD (HTML UI)
// ============================================================================
$supply_raw = (float)$pdo->query("SELECT SUM(balance) FROM wallet_balances")->fetchColumn();
$blocks_raw = (int)$pdo->query("SELECT COUNT(*) FROM blocks")->fetchColumn();

// Cek apakah node ini sudah pernah melakukan pendaftaran ke P2P Master (First Boot Setup)
$stmt_reg = $pdo->query("SELECT value FROM system_state WHERE key_name = 'is_registered'");
$is_registered = $stmt_reg->fetchColumn() === '1' ? 'true' : 'false';

// Jika server saat ini adalah Master Node asli atau berjalan di Localhost, lewati pendaftaran
$host = $_SERVER['HTTP_HOST'];
$is_master_or_local = (strpos($host, 'api.kcln.my.id') !== false || strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JGT Master Node - Core Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #020617; color: #f8fafc; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .terminal-scroll::-webkit-scrollbar { width: 4px; }
        .terminal-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        
        .signal-ring { position: absolute; border-radius: 50%; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); animation: pingRing 2s infinite cubic-bezier(0.215, 0.61, 0.355, 1); }
        @keyframes pingRing {
            0% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(2); box-shadow: 0 0 0 15px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .loader-spin { border: 3px solid rgba(255,255,255,0.1); border-top: 3px solid #10b981; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased relative overflow-hidden">
    
    <!-- LAYAR AUTO REGISTRASI (FIRST BOOT) -->
    <div id="register-overlay" class="fixed inset-0 z-[9999] bg-slate-950 flex flex-col items-center justify-center p-6 transition-opacity duration-500 hidden">
        <div class="glass-panel p-8 md:p-12 rounded-[2.5rem] w-full max-w-lg shadow-[0_0_50px_rgba(16,185,129,0.1)] text-center border border-emerald-500/20 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/10 rounded-bl-full blur-xl"></div>
            
            <div id="reg-loading-ui" class="flex flex-col items-center">
                <div class="loader-spin mb-6 shadow-[0_0_15px_rgba(16,185,129,0.5)] rounded-full"></div>
                <h2 class="text-2xl font-black text-white mb-2">Inisialisasi Node Baru</h2>
                <p class="text-sm text-slate-400 mb-6">Sistem sedang mendaftarkan server ini ke Jaringan P2P Jagat Arsy secara otomatis...</p>
                
                <div class="bg-slate-900/80 border border-slate-700 rounded-xl p-4 w-full">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Mendaftarkan URL:</p>
                    <p class="text-xs font-mono text-emerald-400 break-all" id="reg-url-display">...</p>
                </div>
                
                <p id="reg-status-text" class="mt-6 text-sm font-bold text-amber-400 animate-pulse">Menghubungi Master Node Utama...</p>
            </div>

            <div id="reg-error-ui" class="hidden flex flex-col items-center mt-6">
                <button onclick="skipRegistration()" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold py-3 rounded-xl transition border border-slate-600 mb-3">
                    Lanjutkan ke Dashboard (Mode Privat)
                </button>
                <button onclick="performAutoRegistration()" class="w-full text-emerald-400 hover:text-white font-bold py-2 text-xs transition">
                    Coba Lagi
                </button>
            </div>
        </div>
    </div>

    <!-- EFEK CAHAYA LATAR -->
    <div class="absolute top-[-20%] left-[-10%] w-[500px] h-[500px] bg-emerald-600/10 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="max-w-5xl mx-auto w-full p-6 relative z-10 flex-grow flex flex-col">
        <!-- HEADER -->
        <header class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 border-b border-slate-800 pb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-xl flex items-center justify-center shadow-[0_0_20px_rgba(16,185,129,0.3)]">
                    <i class="ph-bold ph-shield-check text-slate-900 text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black italic tracking-tighter text-white">JGT <span class="text-emerald-400">NODE</span></h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Sovereign Core v11.4</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="bg-slate-900/80 px-4 py-2 rounded-xl border border-slate-800 flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]"></div>
                    <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest">Server Secure</span>
                </div>
            </div>
        </header>

        <!-- GRID STATISTIK -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass-panel p-6 rounded-[2rem] relative overflow-hidden">
                <div class="absolute right-0 top-0 w-24 h-24 bg-emerald-500/10 rounded-bl-full"></div>
                <div class="flex items-center gap-2 mb-2 text-slate-400">
                    <i class="ph-bold ph-cubes text-xl"></i>
                    <p class="text-[10px] font-bold uppercase tracking-widest">Total Blok Tertambang</p>
                </div>
                <p class="text-3xl font-black text-white" id="ui-blocks">...</p>
            </div>
            <div class="glass-panel p-6 rounded-[2rem] relative overflow-hidden">
                <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500/10 rounded-bl-full"></div>
                <div class="flex items-center gap-2 mb-2 text-slate-400">
                    <i class="ph-bold ph-coins text-xl"></i>
                    <p class="text-[10px] font-bold uppercase tracking-widest">Suplai Beredar (JGT)</p>
                </div>
                <p class="text-3xl font-black text-blue-400 truncate" id="ui-supply">...</p>
            </div>
            <div class="glass-panel p-6 rounded-[2rem] relative overflow-hidden group cursor-pointer hover:border-emerald-500/50 transition-colors" onclick="forceSync()">
                <div class="flex items-center justify-between h-full">
                    <div>
                        <div class="flex items-center gap-2 mb-2 text-emerald-400">
                            <i id="sync-icon" class="ph-bold ph-arrows-clockwise text-xl"></i>
                            <p class="text-[10px] font-bold uppercase tracking-widest">Status Jaringan P2P</p>
                        </div>
                        <p class="text-lg font-black text-white" id="ui-sync-status">Menyinkronkan...</p>
                        <p class="text-[9px] text-slate-500 mt-1">Klik untuk paksa sync manual</p>
                    </div>
                    <div class="relative w-12 h-12 flex items-center justify-center shrink-0">
                        <div class="signal-ring w-4 h-4 bg-emerald-500"></div>
                        <i class="ph-fill ph-globe text-emerald-100 z-10"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- TERMINAL P2P AUTO-SYNC -->
        <div class="glass-panel rounded-[2rem] flex flex-col flex-grow overflow-hidden border-slate-700 h-[300px]">
            <div class="bg-slate-900/80 px-6 py-4 border-b border-slate-800 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="flex gap-1.5"><div class="w-3 h-3 rounded-full bg-rose-500"></div><div class="w-3 h-3 rounded-full bg-amber-500"></div><div class="w-3 h-3 rounded-full bg-emerald-500"></div></div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Live Auto-Sync Terminal</span>
                </div>
                <span class="text-[9px] text-slate-500 font-mono">Penjadwal: Aktif (Setiap 60 Detik)</span>
            </div>
            
            <div id="terminal-log" class="bg-[#0b101e] p-6 flex-grow overflow-y-auto terminal-scroll space-y-2 text-xs font-mono">
                <div class="text-emerald-500 font-bold mb-4">JGT Master Node Core [Version 11.4] Ready.</div>
                <div class="text-slate-400 mb-4">
                    - P2P Auto-Discovery Validation    : <span class="text-emerald-400">ACTIVE</span><br>
                    - Ed25519 Cryptographic Verifier   : <span class="text-emerald-400">STRICT ENFORCED</span><br>
                    - Double-Spend Balance Check       : <span class="text-emerald-400">ACTIVE</span><br>
                </div>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT AUTOMATION & AUTO-REGISTER LOGIC -->
    <script>
        const isRegistered = <?= $is_registered ?>;
        const isMasterOrLocal = <?= $is_master_or_local ?>;
        const terminal = document.getElementById('terminal-log');
        let initialBlocks = <?= isset($blocks_raw) ? $blocks_raw : 0 ?>;
        let initialSupply = <?= isset($supply_raw) ? $supply_raw : 0 ?>;

        document.getElementById('ui-blocks').innerText = '#' + initialBlocks.toLocaleString('id-ID');
        document.getElementById('ui-supply').innerText = initialSupply.toLocaleString('id-ID', {maximumFractionDigits: 2});

        // LOGIKA AUTO-REGISTRASI (FIRST BOOT)
        window.onload = () => {
            if (!isRegistered && !isMasterOrLocal) {
                // Tampilkan Layar Registrasi jika ini server komunitas yang baru diinstal
                document.getElementById('register-overlay').classList.remove('hidden');
                performAutoRegistration();
            } else {
                // Jika sudah teregistrasi (Atau ini server API utama / localhost), langsung jalankan Sync normal
                startNormalOperations();
            }
        };

        async function performAutoRegistration() {
            const masterNodeUrl = "https://api.kcln.my.id/index.php";
            const myUrl = window.location.origin + window.location.pathname;
            
            document.getElementById('reg-url-display').innerText = myUrl;
            const statusText = document.getElementById('reg-status-text');
            const errorUi = document.getElementById('reg-error-ui');
            
            errorUi.classList.add('hidden');
            statusText.className = "mt-6 text-sm font-bold text-amber-400 animate-pulse";
            statusText.innerText = "Menghubungi Master Node Utama...";

            try {
                // Tembak URL kita ke Master Node agar kita didaftarkan
                const res = await fetch(`${masterNodeUrl}?action=register_node&url=${encodeURIComponent(myUrl)}`);
                const data = await res.json();

                if (data.status === 'success') {
                    statusText.className = "mt-6 text-sm font-black text-emerald-400";
                    statusText.innerText = "✓ Registrasi Berhasil! Menyimpan status...";
                    
                    // Panggil API lokal kita sendiri untuk menandai bahwa kita sudah terdaftar (agar tidak muncul lagi)
                    await fetch('?action=set_registered');
                    
                    // Tutup layar perlahan dan jalankan dashboard
                    setTimeout(() => {
                        document.getElementById('register-overlay').classList.add('opacity-0');
                        setTimeout(() => {
                            document.getElementById('register-overlay').classList.add('hidden');
                            logToTerminal("[P2P] Server berhasil didaftarkan ke jaringan global secara otomatis.", "success");
                            startNormalOperations();
                        }, 500);
                    }, 1500);
                } else {
                    throw new Error(data.message);
                }
            } catch (err) {
                statusText.className = "mt-6 text-sm font-bold text-rose-400";
                statusText.innerHTML = `Gagal Mendaftar: ${err.message || 'Koneksi ke Master Node terputus atau server belum bisa diakses publik.'}`;
                errorUi.classList.remove('hidden'); // Tampilkan tombol Lewati
            }
        }

        window.skipRegistration = async function() {
            // Tandai sudah selesai setup meskipun gagal/dilewati, agar layar ini tidak mengganggu lagi
            await fetch('?action=set_registered');
            document.getElementById('register-overlay').classList.add('opacity-0');
            setTimeout(() => {
                document.getElementById('register-overlay').classList.add('hidden');
                logToTerminal("[P2P] Pendaftaran dilewati. Berjalan di Mode Privat.", "warning");
                startNormalOperations();
            }, 500);
        }

        function startNormalOperations() {
            setTimeout(executeAutoSync, 1000);
            setInterval(executeAutoSync, 60000);
            setInterval(fetchLiveStats, 10000);
        }

        // FUNGSI DASHBOARD STANDAR
        function logToTerminal(message, type = 'info') {
            const time = new Date().toLocaleTimeString('id-ID', {hour12:false});
            let color = 'text-slate-400';
            if (type === 'success') color = 'text-emerald-400 font-bold';
            if (type === 'error') color = 'text-rose-400 font-bold';
            if (type === 'warning') color = 'text-amber-400';
            
            const div = document.createElement('div');
            div.className = color;
            div.innerHTML = `<span class="text-slate-600">[${time}]</span> ${message}`;
            
            terminal.appendChild(div);
            
            if (terminal.childElementCount > 100) terminal.removeChild(terminal.firstChild);
            terminal.scrollTop = terminal.scrollHeight;
        }

        async function fetchLiveStats() {
            try {
                const res = await fetch('?action=explorer_stats');
                const data = await res.json();
                
                if (data.status === 'success') {
                    const blk = data.data.total_blocks;
                    const sup = data.data.total_supply;
                    
                    document.getElementById('ui-blocks').innerText = '#' + blk.toLocaleString('id-ID');
                    document.getElementById('ui-supply').innerText = sup.toLocaleString('id-ID', {maximumFractionDigits: 2});
                    
                    if (blk > initialBlocks) {
                        logToTerminal(`[INFO] Terdeteksi blok baru di database lokal: #${blk}. Kesulitan (Difficulty) saat ini: ${data.data.difficulty}`, 'warning');
                        initialBlocks = blk;
                    }
                }
            } catch(e) {}
        }

        async function executeAutoSync() {
            const icon = document.getElementById('sync-icon');
            const statusTxt = document.getElementById('ui-sync-status');
            
            icon.classList.add('animate-spin');
            statusTxt.innerText = "Menyinkronkan...";
            statusTxt.className = "text-lg font-black text-amber-400";
            
            logToTerminal("Menghubungi jaringan P2P (Node lain) untuk memeriksa pembaruan rantai...", "info");
            
            try {
                const res = await fetch('?action=sync');
                const data = await res.json();
                
                if (data.status === 'success') {
                    if (data.message.includes('up-to-date')) {
                        logToTerminal("Node sudah ter-update dengan jaringan. Tidak ada blok baru.", "info");
                        statusTxt.innerText = "Sinkron (Up-to-Date)";
                        statusTxt.className = "text-lg font-black text-emerald-400";
                    } else {
                        logToTerminal(data.message, "success");
                        statusTxt.innerText = "Blok Ditarik!";
                        statusTxt.className = "text-lg font-black text-blue-400";
                        fetchLiveStats(); 
                    }
                } else {
                    throw new Error(data.message);
                }
            } catch(e) {
                logToTerminal(`Gagal melakukan sinkronisasi: ${e.message || 'Koneksi terputus.'}`, "error");
                statusTxt.innerText = "Gagal (Offline)";
                statusTxt.className = "text-lg font-black text-rose-400";
            }
            
            icon.classList.remove('animate-spin');
        }

        window.forceSync = function() {
            logToTerminal("Pengguna memicu sinkronisasi manual secara paksa.", "warning");
            executeAutoSync();
        };
    </script>
</body>
</html>