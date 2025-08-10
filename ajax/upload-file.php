<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$taskId = $_POST['task_id'] ?? 0;

if (!$taskId || !canAccessTask($pdo, $_SESSION['user_id'], $taskId)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$fileName = sanitizeFileName($file['name']);

// Check file type
if (!isAllowedFileType($fileName)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed']);
    exit;
}

// Check file size (10MB limit)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size too large (max 10MB)']);
    exit;
}

try {
    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Create task-specific directory
    $taskUploadDir = $uploadDir . 'task_' . $taskId . '/';
    if (!is_dir($taskUploadDir)) {
        mkdir($taskUploadDir, 0755, true);
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
    $uniqueFileName = $baseName . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $filePath = $taskUploadDir . $uniqueFileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        exit;
    }
    
    // Save to database
    $stmt = $pdo->prepare("
        INSERT INTO attachments (project_task_id, user_id, file_name, file_path, file_size, mime_type) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $taskId, 
        $_SESSION['user_id'], 
        $fileName, 
        $filePath, 
        $file['size'], 
        $file['type']
    ]);
    
    // Get task and project info for activity log
    $stmt = $pdo->prepare("
        SELECT pt.project_id, pdt.name as task_name 
        FROM project_tasks pt 
        JOIN predefined_tasks pdt ON pt.predefined_task_id = pdt.id 
        WHERE pt.id = ?
    ");
    $stmt->execute([$taskId]);
    $taskInfo = $stmt->fetch();
    
    // Log activity
    logActivity($pdo, $taskInfo['project_id'], $_SESSION['user_id'], 'file_uploaded', 
               "Uploaded file '$fileName' to task '{$taskInfo['task_name']}'");
    
    echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
