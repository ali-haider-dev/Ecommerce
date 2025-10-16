<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Upload Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- <style>
        .vh-100 {
            min-height: 100vh;
        }
    </style> -->
</head>

<body>
    <?php

    $target_dir = "uploads/";
    $max_file_size = 1048576; 
    $allowed_file_type = "pdf";
    $message = '';



    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0777, true);
    }

    if (isset($_POST["submit"])) {


        $uploaded_file = $_FILES["fileToUpload"] ?? null;
        $upload_ok = true;

        if (!$uploaded_file || $uploaded_file["error"] == UPLOAD_ERR_NO_FILE) {
            $message = '<div class="alert alert-danger">Please select a file to upload.</div>';
            $upload_ok = false;
        }

       
        else {
            $original_filename = basename($uploaded_file["name"]);
            $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            $target_file = $target_dir . $original_filename;

            if ($uploaded_file["error"] !== UPLOAD_ERR_OK) {
                $message = '<div class="alert alert-danger">Upload failed due to a server error. Check your file size or try again.</div>';
                $upload_ok = false;
            } elseif ($uploaded_file["size"] > $max_file_size) {
                $message = '<div class="alert alert-danger">Sorry, your file is too large. Max size is 1MB.</div>';
                $upload_ok = false;
            } elseif ($file_extension !== $allowed_file_type) {
                $message = '<div class="alert alert-danger">Sorry, only **' . strtoupper($allowed_file_type) . '** files are allowed.</div>';
                $upload_ok = false;
            }


            if ($upload_ok && file_exists($target_file)) {
                $current_timestamp = time();
                $file_name_without_ext = pathinfo($original_filename, PATHINFO_FILENAME);

                $new_target_file = $target_dir . $file_name_without_ext . '-' . $current_timestamp . '.' . $file_extension;


                if (rename($target_file, $new_target_file)) {

                    $warning_message = '<div class="alert alert-warning">A file named "' . $original_filename . '" already existed. It was renamed to "' . basename($new_target_file) . '".</div>';
                } else {
                    $message = '<div class="alert alert-danger">Could not rename the existing file. Upload aborted due to permission issues.</div>';
                    $upload_ok = false;
                }
            }


            if ($upload_ok) {
                if (move_uploaded_file($uploaded_file["tmp_name"], $target_file)) {

                    $message = '<div class="alert alert-success">The file **' . htmlspecialchars($original_filename) . '** has been successfully uploaded!</div>' . ($warning_message ?? '');
                } else {
                    $message = '<div class="alert alert-danger">Sorry, there was an error moving your file (permissions or temporary file issue).</div>';
                }
            }
        }
    }
    ?>
    <div class="container mt-5">
        <?php echo $message; ?>
    </div>

    <div class="vh-100 d-flex justify-content-center align-items-center">
        <form method="post" class="d-flex flex-column p-4 border rounded shadow-sm" enctype="multipart/form-data">
            <h1 class="mb-4">PDF Upload</h1>

            <label for="fileToUpload" class="form-label">Select a PDF file (Max 1MB)</label>
            <input type="file" name="fileToUpload" id="fileToUpload" class="form-control">

            <button type="submit" name="submit" class="btn btn-primary my-3">Upload PDF</button>
        </form>
    </div>
</body>

</html>