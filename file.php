<?php
session_start();
$configFilePath = 'conn.php';

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: account/connexion');
    exit();
}

if (!file_exists($configFilePath)) {
    header('Location: setdb');
    exit();
}
require_once 'connexion_bdd.php';

if (isset($_SESSION['user_token'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE token = :token");
    $stmt->bindParam(':token', $_SESSION['user_token']);
    $stmt->execute();
    $utilisateur = $stmt->fetch();

    if (!$utilisateur) {
        header('Location: account/connexion');
        exit();
    }
} else {
    header('Location: account/connexion');
    exit();
}

// Fonction pour ajouter un log
function addLog($action) {
    $logEntry = [
        'user' => $_SESSION['user_token'],
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action
    ];
    $logJson = json_encode($logEntry) . "\n";
    file_put_contents(__DIR__ . '/logs/logs.json', $logJson, FILE_APPEND);
}

$baseDir = realpath(__DIR__ . '/data/files'); 
$currentDir = isset($_GET['dir']) ? $_GET['dir'] : '';

$fullPath = realpath($baseDir . '/' . $currentDir);

if ($fullPath === false || strpos($fullPath, $baseDir) !== 0) {
    die('Accès interdit ou chemin invalide.');
}

if (isset($_POST['create_folder']) && !empty($_POST['new_folder'])) {
    $newFolder = $fullPath . '/' . $_POST['new_folder'];
    if (!file_exists($newFolder)) {
        mkdir($newFolder, 0777, true);
        addLog("Création du dossier: " . $_POST['new_folder']);
    }
}

if (isset($_POST['upload'])) {
    $uploadFile = $fullPath . '/' . basename($_FILES['upload_file']['name']);
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $uploadFile)) {
        addLog("Upload du fichier: " . basename($_FILES['upload_file']['name']));
    }
}

if (isset($_POST['extract_zip']) && isset($_FILES['upload_zip'])) {
    $zipFile = $fullPath . '/' . basename($_FILES['upload_zip']['name']);
    if (move_uploaded_file($_FILES['upload_zip']['tmp_name'], $zipFile)) {
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($fullPath);
            $zip->close();
            unlink($zipFile);
            addLog("Extraction du zip: " . basename($_FILES['upload_zip']['name']));
        }
    }
}

if (isset($_GET['delete'])) {
    $deletePath = $baseDir . '/' . $_GET['delete'];
    if (is_file($deletePath)) {
        unlink($deletePath);
        addLog("Suppression du fichier: " . $_GET['delete']);
    } elseif (is_dir($deletePath)) {
        deleteDirectory($deletePath);
        addLog("Suppression du dossier: " . $_GET['delete']);
    }
}

if (isset($_POST['delete_selected']) && !empty($_POST['selected_files'])) {
    foreach ($_POST['selected_files'] as $file) {
        $deletePath = $fullPath . '/' . $file;
        if (is_file($deletePath)) {
            unlink($deletePath);
            addLog("Suppression du fichier: " . $file);
        } elseif (is_dir($deletePath)) {
            deleteDirectory($deletePath);
            addLog("Suppression du dossier: " . $file);
        }
    }
}

if (isset($_GET['download'])) {
    $filePath = $baseDir . '/' . $_GET['download'];
    if (is_file($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($filePath));
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        addLog("Téléchargement du fichier: " . $_GET['download']);
        exit;
    }
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

function createZip($files, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE) !== TRUE) {
        return false;
    }
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }
    $zip->close();
    return true;
}

if (isset($_POST['create_zip']) && !empty($_POST['selected_files'])) {
    $zipName = 'selection_' . date('YmdHis') . '.zip';
    $zipPath = $fullPath . '/' . $zipName;
    $filesToZip = array_map(function($file) use ($fullPath) {
        return $fullPath . '/' . $file;
    }, $_POST['selected_files']);
    
    if (createZip($filesToZip, $zipPath)) {
        addLog("Création du zip: " . $zipName);
        header("Location: " . $_SERVER['PHP_SELF'] . "?dir=" . urlencode($currentDir));
        exit;
    }
}

require_once 'ui/header.php';
?>

<div class="container mx-auto mt-10 p-6 bg-gray-900 text-white border border-gray-700 rounded-lg shadow-lg">
    <div class="grid grid-cols-1 gap-6">
        <h2 class="text-3xl font-bold mb-6 text-gray-100 border-b border-gray-600 pb-2">Explorateur de Fichiers</h2>
        
        <div class="flex mb-4">
            <form action="" method="post" class="mr-4">
                <input type="text" name="new_folder" placeholder="Nom du dossier" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit" name="create_folder" class="mt-2 px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-75">
                    <i class="bi bi-folder-plus"></i> Créer Dossier
                </button>
            </form>
            <form action="" method="post" enctype="multipart/form-data" class="mr-4">
                <input type="file" name="upload_file" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit" name="upload" class="mt-2 px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-75">
                    <i class="bi bi-upload"></i> Uploader
                </button>
            </form>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="file" name="upload_zip" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit" name="extract_zip" class="mt-2 px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-75">
                    <i class="bi bi-file-earmark-zip"></i> Extraire Zip
                </button>
            </form>
        </div>
        
        <form method="post" id="fileForm">
            <ul>
                <li class="mb-2 flex items-center">
                    <input type="checkbox" onclick="toggleSelectAll(this)" class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500 mr-2">
                    <label for="select_all" class="text-gray-200">Tout sélectionner</label>
                </li>
                <?php
                function listFiles($dir) {
                    $files = scandir($dir);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && $file !== 'index.php') { 
                            echo '<li class="mb-2 flex items-center">';
                            echo '<input type="checkbox" name="selected_files[]" value="' . htmlspecialchars($file) . '" class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500 mr-2">';
                            if (is_dir($dir . '/' . $file)) {
                                echo '<i class="bi bi-folder-fill text-yellow-500 mr-2"></i>';
                                echo '<a href="?dir=' . urlencode(trim($GLOBALS['currentDir'] . '/' . $file, '/')) . '" class="text-indigo-400 hover:underline">' . htmlspecialchars($file) . '</a>';
                            } else {
                                echo '<i class="bi bi-file-earmark-fill text-gray-500 mr-2"></i>';
                                echo htmlspecialchars($file);
                                if (is_file($dir . '/' . $file)) {
                                    echo '<a href="?download=' . urlencode(trim($GLOBALS['currentDir'] . '/' . $file, '/')) . '" class="text-green-500 hover:text-green-700 ml-4"><i class="bi bi-download"></i></a>';
                                }
                            }
                            echo '<a href="?delete=' . urlencode(trim($GLOBALS['currentDir'] . '/' . $file, '/')) . '" class="text-red-500 hover:text-red-700 ml-4"><i class="bi bi-trash"></i></a>';
                            echo '</li>';
                        }
                    }
                }

                if ($currentDir) {
                    $parentDir = dirname($currentDir);
                    echo '<li class="mb-2">';
                    echo '<i class="bi bi-arrow-left-short text-gray-500 mr-2"></i>';
                    echo '<a href="?dir=' . urlencode($parentDir) . '" class="text-indigo-400 hover:underline">.. (Retour)</a>';
                    echo '</li>';
                }

                listFiles($fullPath);
                ?>
            </ul>
            <div id="actionButtons" class="mt-4" style="display: none;">
                <button type="submit" name="delete_selected" id="deleteButton" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg shadow-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-75">
                    <i class="bi bi-trash"></i> Supprimer Sélectionnés
                </button>
                <button type="submit" name="create_zip" id="zipButton" class="px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-75 ml-2">
                    <i class="bi bi-file-earmark-zip"></i> Créer ZIP
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSelectAll(source) {
    var checkboxes = document.getElementsByName('selected_files[]');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
    updateActionButtons();
}

function updateActionButtons() {
    var checkboxes = document.getElementsByName('selected_files[]');
    var checkedCount = 0;
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
            checkedCount++;
        }
    }

    var actionButtons = document.getElementById('actionButtons');
    var deleteButton = document.getElementById('deleteButton');
    var zipButton = document.getElementById('zipButton');

    if (checkedCount > 0) {
        actionButtons.style.display = 'block';
        deleteButton.style.display = 'inline-block';
        zipButton.style.display = 'inline-block';
    } else {
        actionButtons.style.display = 'none';
    }
}

// Ajouter des écouteurs d'événements à toutes les cases à cocher
var checkboxes = document.getElementsByName('selected_files[]');
for (var i = 0; i < checkboxes.length; i++) {
    checkboxes[i].addEventListener('change', updateActionButtons);
}

// Appeler updateActionButtons au chargement de la page
updateActionButtons();
</script>

<?php require_once './ui/footer.php'; ?>