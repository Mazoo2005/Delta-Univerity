<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff' || $_SESSION['staff_type'] !== 'teach') {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username_db = "root";
$password_db = "Mmina@12345";
$dbname = "AI_Management";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$facultyID = (int)$_SESSION['id'];

// Function to execute queries safely
function safeQuery($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    if ($params) {
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Check if Confirmed column exists and add it if it doesn't
$checkColumn = $conn->query("SHOW COLUMNS FROM Registration LIKE 'Confirmed'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE Registration ADD COLUMN Confirmed ENUM('Y', 'N', 'P') DEFAULT 'P'");
    if ($conn->error) {
        die("Error adding Confirmed column: " . $conn->error);
    }
}

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_registration'])) {
    $registrationID = (int)$_POST['registration_id'];
    $status = $_POST['status'];
    
    $sql = "UPDATE Registration SET Confirmed = ? WHERE RegistrationID = ? AND StudentID IN 
            (SELECT StudentID FROM Students WHERE Guide = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sii', $status, $registrationID, $facultyID);
    
    if ($stmt->execute()) {
        $message = "Registration " . ($status == 'Y' ? "confirmed" : "rejected") . " successfully";
    } else {
        $error = "Error updating registration: " . $stmt->error;
    }
    $stmt->close();
}

// Get students under this faculty's guidance and their pending registrations
$students = safeQuery($conn, "
    SELECT s.StudentID, s.FirstName, s.LastName, r.RegistrationID, r.CourseID, c.CourseName, r.Confirmed
    FROM Students s
    LEFT JOIN Registration r ON s.StudentID = r.StudentID
    LEFT JOIN Courses c ON r.CourseID = c.CourseID
    WHERE s.Guide = ? AND (r.Confirmed IS NULL OR r.Confirmed = 'P')
", [$facultyID]);

?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Guidance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="v.css">
    <style>
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: var(--primary-color);
            color: white;
        }

        .table tr:hover {
            background-color: #f5f5f5;
        }

        .btn {
            padding: 8px 16px;
            margin: 0 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .alert {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <header>
        <h1>Learning Management System</h1>
        <p>Academic Guidance - Registration Confirmation</p>
    </header>

    <div class="container">
        <?php 
        if (isset($message)) echo "<div class='alert alert-success'>$message</div>";
        if (isset($error)) echo "<div class='alert alert-danger'>$error</div>";
        ?>

        <div class="dashboard-card">
            <div class="card-header">
                <i class="fas fa-user-graduate"></i> Pending Registrations
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $students->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?></td>
                            <td><?php echo htmlspecialchars($row['CourseName'] ?? 'Not specified'); ?></td>
                            <td><?php echo $row['Confirmed'] == 'P' ? 'Pending' : ($row['Confirmed'] === null ? 'Not Set' : 'Pending'); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="registration_id" value="<?php echo $row['RegistrationID']; ?>">
                                    <button type="submit" name="confirm_registration" value="Y" class="btn btn-success">
                                        <i class="fas fa-check"></i> Confirm
                                    </button>
                                    <button type="submit" name="confirm_registration" value="N" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <a href="tech.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</body>
</html>

<?php $conn->close(); ?>