<?php
include "_config.php";

// Redirect to login page if user is not logged in
if (!$App->LoggedIn()) {
    header('location: login.php');
    exit(); // Stop further execution
}

$error_message = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data or generate random values if not provided
    $username = isset($_POST['username']) ? $_POST['username'] : $App->generateRandomString(15);
    $password = isset($_POST['password']) ? $_POST['password'] : $App->generateRandomString(15);
    $expire_date = isset($_POST['expire_date']) ? $_POST['expire_date'] : null;

    // Validate form data
    if (!empty($username) && !empty($password) && !empty($expire_date)) {
        // Insert new line into the database
        $inserted = $App->insertLine($username, $password, $expire_date);

        if ($inserted) {
            // Redirect to lines.php after adding the line
            header("Location: lines.php");
            exit;
        } else {
            // Handle insertion failure
            // You can log errors or display an error message to the user
            $error_message = "Failed to insert line into the database.";
        }
    } else {
        // Handle missing or empty fields
        $error_message = "Please fill in all required fields.";
    }
}

?>
<!doctype html>
<html lang="en">
<?php include "_htmlhead.php"?>
<style>
    /* Your custom styles here */
</style>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include "_header.php"?>
    <?php include "_sidebar.php"?>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Lines</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="javascript: void(0);">Main</a></li>
                                    <li class="breadcrumb-item active">Add Line</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-4">Add Line</h4>
                                <?php echo $error_message ?>
                                <div class="form-container">
                                    <form action="" method="POST">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username" name="username" value="">
                                        </div>
                                        <div class="mb-3">
                                            <label for="password" class="form-label">Password</label>
                                            <input type="text" class="form-control" id="password" name="password" value="">
                                        </div>
                                        <div class="mb-3">
                                            <label for="expire_date" class="form-label">Expire Date</label>
                                            <input type="text" class="form-control" id="expire_date" name="expire_date" required>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" class="btn btn-primary">Add Line</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "_footer.php"?>
    </div>
</div>

<?php include "_rightbar.php"?>
<div class="rightbar-overlay"></div>

<!-- Add the JavaScript code for line deletion here -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>
<script>
    // Example JavaScript code for line deletion
    var deleteButtons = document.querySelectorAll('.delete-line');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var lineId = this.getAttribute('data-line-id');
            // Implement the deletion logic here
        });
    });

    // Datepicker
    $(function() {
        $("#expire_date").datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true
        });
    });
</script>
</body>
</html>
