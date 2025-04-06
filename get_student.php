<?php
// Database connection details
$servername = "localhost";
$username_db = "root";
$password_db = "Mmina@12345";
$dbname = "AI_Management";

// Create database connection
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Safe query execution function
function safeQuery($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Get student ID from request
$student_id = isset($_GET['id']) ? $_GET['id'] : null;
$is_edit = isset($_GET['edit']) && $_GET['edit'] === 'true';

if (!$student_id) {
    echo "<p>No student ID provided!</p>";
    exit();
}

// Fetch student data
$sql = "SELECT * FROM Students WHERE StudentID = ?";
$result = safeQuery($conn, $sql, [$student_id]);
$student = $result->fetch_assoc();

if (!$student) {
    echo "<p>Student not found!</p>";
    exit();
}

// Display student data (view mode) or edit form (edit mode)
if ($is_edit) {
    ?>
    <h2>Edit Student</h2>
    <form method="POST" action="view_students.php">
        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['StudentID']); ?>">
        <div class="form-group">
            <label>First Name:</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['FirstName']); ?>" required>
        </div>
        <div class="form-group">
            <label>Last Name:</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['LastName']); ?>" required>
        </div>
        <div class="form-group">
            <label>Date of Birth:</label>
            <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($student['DateOfBirth']); ?>" required>
        </div>
        <div class="form-group">
            <label>Major:</label>
            <input type="text" name="major" value="<?php echo htmlspecialchars($student['Major']); ?>">
        </div>
        <div class="form-group">
            <label>Academic Year:</label>
            <input type="number" name="academic_year" value="<?php echo htmlspecialchars($student['AcademicYear']); ?>">
        </div>
        <div class="form-group">
            <label>GPA:</label>
            <input type="number" step="0.01" name="gpa" value="<?php echo htmlspecialchars($student['GPA']); ?>">
        </div>
        <div class="form-group">
            <label>Attendance:</label>
            <input type="number" step="0.01" name="attendance" value="<?php echo htmlspecialchars($student['Attendance']); ?>">
        </div>
        <div class="form-group">
            <label>Guide:</label>
            <input type="text" name="guide" value="<?php echo htmlspecialchars($student['Guide']); ?>" required>
        </div>
        <button type="submit" name="update_student">Update Student</button>
    </form>
    <style>
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
        button { background-color: #3a6ea5; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #004e98; }
    </style>
    <?php
} else {
    ?>
    <h2>Student Details</h2>
    <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['StudentID']); ?></p>
    <p><strong>First Name:</strong> <?php echo htmlspecialchars($student['FirstName']); ?></p>
    <p><strong>Last Name:</strong> <?php echo htmlspecialchars($student['LastName']); ?></p>
    <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($student['DateOfBirth']); ?></p>
    <p><strong>Major:</strong> <?php echo empty($student['Major']) ? 'Null' : htmlspecialchars($student['Major']); ?></p>
    <p><strong>Academic Year:</strong> <?php echo htmlspecialchars($student['AcademicYear']); ?></p>
    <p><strong>GPA:</strong> <?php echo htmlspecialchars($student['GPA']); ?></p>
    <p><strong>Attendance:</strong> <?php echo htmlspecialchars($student['Attendance']); ?></p>
    <p><strong>Guide:</strong> <?php echo htmlspecialchars($student['Guide']); ?></p>
    <style>
        p { margin: 0.5rem 0; }
        strong { color: #3a6ea5; }
    </style>
    <?php
}

$conn->close();
?>