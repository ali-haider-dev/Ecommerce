<?php
session_start();
if (!isset($_SESSION["LogedIn"])) {
    header("Location: form.php", replace: true, );
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>SB Admin 2 - Dashboard</title>

    <!-- Custom fonts for this template-->
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="./assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body id="page-top">
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
        } else {
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
    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <?php include './layout/sidebar.php' ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <?php include './layout/header.php' ?>

                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <div class="container mt-5">
                        <?php echo $message; ?>
                    </div>

                    <div class="vh-100 d-flex justify-content-center align-items-center">
                        <form method="post" class="d-flex flex-column p-4 border rounded shadow-sm"
                            enctype="multipart/form-data">
                            <h1 class="mb-4">PDF Upload</h1>

                            <label for="fileToUpload" class="form-label">Select a PDF file (Max 1MB)</label>
                            <input type="file" name="fileToUpload" id="fileToUpload" class="form-control pl-0">

                            <button type="submit" name="submit" class="btn btn-primary my-3">Upload PDF</button>
                        </form>

                    </div>
                    <!-- /.container-fluid -->

                </div>
                <!-- End of Main Content -->

                <!-- Footer -->
                <footer class="sticky-footer bg-white">
                    <div class="container my-auto">
                        <div class="copyright text-center my-auto">
                            <span>Copyright &copy; Your Website 2021</span>
                        </div>
                    </div>
                </footer>
                <!-- End of Footer -->

            </div>
            <!-- End of Content Wrapper -->

        </div>
        <!-- End of Page Wrapper -->

        <!-- Scroll to Top Button-->
        <a class="scroll-to-top rounded" href="#page-top">
            <i class="fas fa-angle-up"></i>
        </a>

        <!-- Logout Modal-->

    </div>
    <!-- Bootstrap core JavaScript-->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="assets/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="assets/js/sb-admin-2.min.js"></script>

    <!-- Page level plugins -->
    <script src="assets/vendor/chart.js/Chart.min.js"></script>

    <!-- Page level custom scripts -->
    <script src="assets/js/demo/chart-area-demo.js"></script>
    <script src="assets/js/demo/chart-pie-demo.js"></script>

</body>

</html>