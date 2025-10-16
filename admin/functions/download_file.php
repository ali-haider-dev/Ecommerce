<?php
if (isset($_GET['name']) && !empty($_GET['name'])) {
    $file_path ='../' . $_GET['name'];
    $full_path_resolved = realpath($file_path);
    echo ''. $file_path ;
    $allowed_dir = realpath(__DIR__ . '/uploads');
    if ($full_path_resolved && strpos($full_path_resolved, $allowed_dir) === 0 && file_exists($full_path_resolved)) {
        // Set headers to initiate file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($full_path_resolved) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($full_path_resolved));
        readfile($full_path_resolved);
        exit;
    } else {
        http_response_code(404);
        echo "Error: File not found.";
        exit;
    }
} else {
    http_response_code(400);
    echo "Error: Invalid request.";
    exit;
}