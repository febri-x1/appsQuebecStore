<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/functions.php';

// Server-Side URL Rewriting untuk Tab Isolation
ob_start(function($buffer) {
    if (isset($_SESSION['active_role'])) {
        $role = urlencode($_SESSION['active_role']);
        
        // Rewrite semua tautan <a> lokal
        $buffer = preg_replace_callback('/(<a\s+[^>]*href=")([^"]+)(")/i', function($m) use ($role) {
            $url = $m[2];
            // Abaikan tautan logout, anchor murni, javascript, atau eksternal
            if (strpos($url, 'logout.php') !== false || strpos($url, 'javascript:') === 0 || strpos($url, '#') === 0) return $m[0];
            if (strpos($url, 'http') === 0 && strpos($url, BASE_URL) === false) return $m[0];
            
            if (strpos($url, 'switch_role=') === false) {
                $sep = strpos($url, '?') !== false ? '&' : '?';
                return $m[1] . $url . $sep . 'switch_role=' . $role . $m[3];
            }
            return $m[0];
        }, $buffer);

        // Sisipkan hidden input ke dalam semua form
        $buffer = preg_replace('/(<form\s+[^>]*>)/i', '$1<input type="hidden" name="switch_role" value="' . $role . '">', $buffer);
    }
    return $buffer;
});

$pageTitle = $pageTitle ?? APP_NAME;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= clean($pageTitle); ?> - <?= APP_NAME; ?></title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
    
    <script>
        // SCRIPT ISOLASI MULTI-ROLE (TAB SEPARATION)
        // Intercept Semua AJAX/Fetch API agar menggunakan Role yang Benar sesuai URL
        const originalFetch = window.fetch;
        window.fetch = function() {
            let [resource, config] = arguments;
            if (!config) config = {};
            if (!config.headers) config.headers = {};
            
            // Ambil role dari URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const tr = urlParams.get('switch_role') || '<?= $_SESSION['active_role'] ?? '' ?>';
            
            if (tr) {
                config.headers['X-Tab-Role'] = tr;
            }
            return originalFetch(resource, config);
        };

        // SCRIPT PEMBATASAN 1 TAB PER ROLE
        (function() {
            const urlParams = new URLSearchParams(window.location.search);
            const role = urlParams.get('switch_role') || '<?= $_SESSION['active_role'] ?? '' ?>';
            if (role) {
                const channelName = 'tab_restriction_' + role;
                const bc = new BroadcastChannel(channelName);
                
                // Gunakan random string sebagai ID tab ini
                const tabId = Math.random().toString(36).substring(2);
                
                // Cek ke tab lain apakah ada yang aktif
                bc.postMessage({ type: 'ping', tabId: tabId });
                
                bc.onmessage = (event) => {
                    const data = event.data;
                    if (data.type === 'ping') {
                        // Ada tab baru yang sedang terbuka, beritahu bahwa kita sudah ada
                        bc.postMessage({ type: 'pong', activeTabId: tabId });
                    } else if (data.type === 'pong') {
                        // Ada tab yang lebih dulu aktif, blokir tab ini
                        document.body.innerHTML = `
                            <div style="display:flex; height:100vh; width:100vw; background:#f8d7da; color:#721c24; justify-content:center; align-items:center; flex-direction:column; font-family:sans-serif; z-index:999999; position:fixed; top:0; left:0;">
                                <h1>Akses Ditolak</h1>
                                <p style="font-size: 1.2rem; margin-top: 10px;">Role <b>${role.toUpperCase()}</b> sudah aktif di tab lain.</p>
                                <p style="margin-bottom: 20px;">Sistem membatasi penggunaan 1 tab per role untuk menjaga konsistensi data.</p>
                                <button onclick="window.close()" style="padding: 10px 20px; font-size: 1rem; cursor: pointer; border: none; border-radius: 5px; background: #dc3545; color: white;">Tutup Tab Ini</button>
                                <p style="font-size: 0.9rem; margin-top: 20px; color: #6c757d;">(Jika Anda yakin tab sebelumnya sudah ditutup, silakan muat ulang halaman ini)</p>
                                <button onclick="location.reload()" style="padding: 5px 10px; font-size: 0.9rem; cursor: pointer; border: 1px solid #6c757d; border-radius: 5px; background: transparent; color: #6c757d; margin-top: 10px;">Muat Ulang</button>
                            </div>
                        `;
                    }
                };
            }
        })();
    </script>
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div>
                <p class="muted">Sistem POS Kasir</p>
                <h1><?= clean($pageTitle); ?></h1>
            </div>
        </header>

        <?php if ($success = flash('success')): ?>
            <div class="alert success"><?= clean($success); ?></div>
        <?php endif; ?>
        <?php if ($error = flash('error')): ?>
            <div class="alert error"><?= clean($error); ?></div>
        <?php endif; ?>
