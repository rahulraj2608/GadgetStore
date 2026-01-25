<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../includes/db_connect.php';
require_once '../includes/functions.php'; // Ensure write_log() is available here

$ip = $_SERVER['REMOTE_ADDR'];
$current_time = time();

try {
    // --- 0. PRE-CHECK: IS IP BANNED? ---
    $check_ban = $pdo->prepare("SELECT ip_address FROM ip_blacklist WHERE ip_address = ?");
    $check_ban->execute([$ip]);
    if ($check_ban->fetch()) {
        // Optional: Log attempt from already banned IP
        write_log($pdo, "API_BANNED_IP_ATTEMPT", 0); 
        http_response_code(403);
        exit(json_encode(["status" => "error", "message" => "Your IP has been permanently blocked for abuse."]));
    }

    // --- 1. FETCH USER AND SECURITY DATA ---
    $headers = getallheaders();
    $api_key = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? '';
    $hashed_key = hash('sha256', $api_key);

    $stmt = $pdo->prepare("SELECT id, bucket_tokens, last_refill_time, window_requests, last_window_reset FROM api_keys WHERE `key` = ? AND is_active = 1");
    $stmt->execute([$hashed_key]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        write_log($pdo, "API_INVALID_KEY_ATTEMPT", 0); // Log failed authentication
        http_response_code(401);
        exit(json_encode(["status" => "error", "message" => "Invalid API Key"]));
    }

    $current_user_id = $user['id']; // Assign ID for logging

    // --- 2. WINDOW LOGIC ---
    $window_start = $user['last_window_reset'];
    $requests = $user['window_requests'];

    if (($current_time - $window_start) > 60) {
        $requests = 1;
        $window_start = $current_time;
    } else {
        $requests++;
    }

    // --- TIER 3: IP BAN (> 120 req/min) ---
    if ($requests > 120) {
        $ban = $pdo->prepare("INSERT IGNORE INTO ip_blacklist (ip_address, reason) VALUES (?, 'Exceeded 120 req/min')");
        $ban->execute([$ip]);
        
        write_log($pdo, "API_TIER3_IP_BAN", $current_user_id); // Log the ban event
        
        http_response_code(403);
        exit(json_encode(["status" => "error", "message" => "Abuse detected. IP Banned."]));
    }

    // --- TIER 2: RATE LIMIT (> 60 req/min) ---
    if ($requests > 60) {
        $upd = $pdo->prepare("UPDATE api_keys SET window_requests = ?, last_window_reset = ? WHERE id = ?");
        $upd->execute([$requests, $window_start, $current_user_id]);
        
        write_log($pdo, "API_TIER2_RATE_LIMIT", $current_user_id); // Log rate limit hit
        
        http_response_code(429);
        exit(json_encode(["status" => "error", "message" => "Rate limit (60) exceeded. Wait for the next minute."]));
    }

    // --- TIER 1: THROTTLING (20-60 req/min Speed Control) ---
    $refill_rate = 0.33; 
    $last_refill = $user['last_refill_time'];
    $tokens = (float)$user['bucket_tokens'];
    $new_tokens = min(20, $tokens + (($current_time - $last_refill) * $refill_rate));

    if ($new_tokens < 1.0) {
        $upd = $pdo->prepare("UPDATE api_keys SET window_requests = ?, last_window_reset = ? WHERE id = ?");
        $upd->execute([$requests, $window_start, $current_user_id]);
        
        write_log($pdo, "API_TIER1_THROTTLE", $current_user_id); // Log throttling
        
        http_response_code(429);
        exit(json_encode(["status" => "error", "message" => "Throttling: Moving too fast. Slow down."]));
    }

    // --- 3. PERSIST SECURITY UPDATES ---
    $final_tokens = $new_tokens - 1.0;
    $update_all = $pdo->prepare("UPDATE api_keys SET window_requests = ?, last_window_reset = ?, bucket_tokens = ?, last_refill_time = ? WHERE id = ?");
    $update_all->execute([$requests, $window_start, $final_tokens, $current_time, $current_user_id]);

    // --- 4. DATA FETCHING ---
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $records_per_page = 20;
    $offset = ($page - 1) * $records_per_page;

    $sql = "SELECT p.*, b.brand_name, c.category_name, s.supplier_name 
            FROM product p 
            LEFT JOIN brand b ON p.brand_id = b.brand_id 
            LEFT JOIN category c ON p.category_id = c.category_id 
            LEFT JOIN supplier s ON p.supplier_id = s.supplier_id 
            ORDER BY p.product_id DESC 
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_items = (int)$pdo->query("SELECT COUNT(*) FROM product")->fetchColumn();
    $total_pages = ceil($total_items / $records_per_page);

    // --- 5. LOG SUCCESS AND SEND RESPONSE ---
    // Optional: Log every successful data fetch (Keep an eye on DB size!)
    // write_log($pdo, "API_DATA_FETCH_SUCCESS", $current_user_id);

    echo json_encode([
        "status" => "success",
        "timestamp" => date('Y-m-d H:i:s'),
        "security_stats" => [
            "requests_this_minute" => $requests,
            "burst_tokens_remaining" => floor($final_tokens),
            "tier" => ($requests > 20) ? "Throttled" : "Normal"
        ],
        "pagination" => [
            "current_page" => $page,
            "total_pages" => $total_pages,
            "total_records" => $total_items
        ],
        "data" => $products
    ]);

} catch (Exception $e) {
    // Log fatal server errors
    error_log("API FATAL ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error"]);
}
?>