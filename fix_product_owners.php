<?php
/**
 * fix_product_owners.php
 *
 * CLI helper to diagnose and optionally fix product.seller_id problems.
 * Usage:
 *   php fix_product_owners.php           # dry-run report
 *   php fix_product_owners.php --apply   # apply fixes when safe mapping found
 *
 * Behavior:
 * - Lists products whose seller_id does not match any users.id (or is non-numeric)
 * - If seller_id matches a users.username, offers to map to that user's id when --apply is used
 * - Always shows summary and a small CSV backup file (fix_product_owners_backup.csv) when applying
 */

require_once __DIR__ . '/db.php';

$apply = in_array('--apply', $argv);

echo "Running fix_product_owners.php (apply=" . ($apply? 'yes' : 'no') . ")\n";

$res = $conn->query("SELECT id, name, seller_id FROM products ORDER BY id DESC");
if(!$res){
    echo "ERROR: failed to query products: " . $conn->error . "\n";
    exit(1);
}

$rows = $res->fetch_all(MYSQLI_ASSOC);
$bad = [];

foreach($rows as $r){
    $sid = $r['seller_id'];
    // treat empty string as null
    if($sid === null || $sid === ''){
        // missing owner -> candidate to claim but not an error necessarily
        continue;
    }

    // If numeric and matches a user id, it's fine
    if(is_numeric($sid)){
        $uid = intval($sid);
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $r2 = $stmt->get_result()->fetch_assoc();
        if($r2) continue; // valid
        // numeric but no matching user -> report
        $bad[] = ['product_id'=>$r['id'],'name'=>$r['name'],'seller_id'=>$sid,'reason'=>'numeric_not_found'];
        continue;
    }

    // Non-numeric seller_id: maybe stored username historically
    $username = $sid;
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if($u){
        $bad[] = ['product_id'=>$r['id'],'name'=>$r['name'],'seller_id'=>$sid,'reason'=>'username_match','match_user_id'=>$u['id'],'match_username'=>$u['username']];
    } else {
        $bad[] = ['product_id'=>$r['id'],'name'=>$r['name'],'seller_id'=>$sid,'reason'=>'unknown_string'];
    }
}

if(empty($bad)){
    echo "No problematic product.seller_id values found.\n";
    exit(0);
}

echo "Found " . count($bad) . " problematic products:\n";
foreach($bad as $b){
    echo " - [#{$b['product_id']}] {$b['name']}  seller_id=" . var_export($b['seller_id'], true) . "  reason={$b['reason']}";
    if(isset($b['match_user_id'])) echo "  -> can map to user_id={$b['match_user_id']} (username={$b['match_username']})";
    echo "\n";
}

if(!$apply){
    echo "\nDry-run only. To apply mappings where a username->user_id match exists, re-run with --apply\n";
    exit(0);
}

// APPLY: perform safe mapping for entries that have match_user_id
echo "\nApplying fixes... (a CSV backup will be written)\n";

$backupFile = __DIR__ . '/fix_product_owners_backup.csv';
$fh = fopen($backupFile, 'w');
fputcsv($fh, ['product_id','name','old_seller_id','new_seller_id','reason']);

$fixed = 0; $skipped = 0;
foreach($bad as $b){
    if(!isset($b['match_user_id'])){
        echo "Skipping product {$b['product_id']} (no safe mapping found)\n";
        $skipped++; fputcsv($fh, [$b['product_id'],$b['name'],$b['seller_id'],'',''.$b['reason']]);
        continue;
    }
    $pid = intval($b['product_id']);
    $newUid = intval($b['match_user_id']);

    // write backup row
    fputcsv($fh, [$pid,$b['name'],$b['seller_id'],$newUid,$b['reason']]);

    $stmt = $conn->prepare("UPDATE products SET seller_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $newUid, $pid);
    if($stmt->execute()){
        echo "Fixed product {$pid} => seller_id={$newUid}\n";
        $fixed++;
    } else {
        echo "Failed to update product {$pid}: " . $stmt->error . "\n";
    }
}

fclose($fh);

echo "\nDone. Fixed={$fixed}, Skipped={$skipped}. Backup: $backupFile\n";

exit(0);

?>
