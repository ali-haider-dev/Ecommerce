<?php


require_once '../db.php';
session_start();


if (!isset($_SESSION["LoginAdmin"])) {
    header("Location: form.php", true, 302);
    exit();
}


if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
    $id = (int) $_GET["id"];


    $stmt = $conn->prepare("UPDATE tbl_products SET isActive=0 where id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {

        $stmt->close();
        $conn->close();
        header("Location: dashboard2.php?success=UserDeleted");
        exit();
    } else {

        $stmt->close();
        $conn->close();
        header("Location: dashboard2.php?error=DeleteFailed");
        exit();
    }

} else {
    header("Location: dashboard2.php?error=InvalidID");
    exit();
}
?>