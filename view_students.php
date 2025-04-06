<?php
session_start();

// Check if user is staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

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
function safeQuery($conn, $sql, $params = [], $is_select = false) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }
    if ($params) {
        $types = str_repeat('s', count($params)); // كل المعاملات تعامل كنصوص
        $stmt->bind_param($types, ...$params);
    }
    $success = $stmt->execute();
    if ($is_select && $success) {
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }
    $stmt->close();
    return $success;
}

// Handle student deletion
if (isset($_GET['delete_id'])) {
    $student_id = $_GET['delete_id'];
    $sql = "DELETE FROM Students WHERE StudentID = ?";
    $result = safeQuery($conn, $sql, [$student_id]);
    
    if ($result) {
        $success_msg = "Student deleted successfully!";
    } else {
        $error_msg = "An error occurred while deleting the student: " . $conn->error;
    }
}

// Handle student update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_student'])) {
    $student_id = $_POST['student_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $date_of_birth = $_POST['date_of_birth'];
    $major = $_POST['major'] === '' ? null : $_POST['major'];
    $academic_year = $_POST['academic_year'] === '' ? null : $_POST['academic_year'];
    $gpa = $_POST['gpa'] === '' ? null : floatval($_POST['gpa']);
    $attendance = $_POST['attendance'] === '' ? null : floatval($_POST['attendance']);
    $guide = $_POST['guide'];

    // Validate GPA
    if ($gpa !== null && ($gpa < -9.99 || $gpa > 9.99)) {
        $error_msg = "GPA must be between -9.99 and 9.99.";
    } else {
        $sql = "UPDATE Students SET FirstName = ?, LastName = ?, DateOfBirth = ?, Major = ?, AcademicYear = ?, GPA = ?, Attendance = ?, Guide = ? WHERE StudentID = ?";
        $params = [$first_name, $last_name, $date_of_birth, $major, $academic_year, $gpa, $attendance, $guide, $student_id];
        $result = safeQuery($conn, $sql, $params);

        if ($result) {
            $affected_rows = $conn->affected_rows;
            $success_msg = "Updated successfully! (Rows affected: $affected_rows)";
        } else {
            $error_msg = "An error occurred while updating student data: " . $conn->error;
        }
    }
}

// Handle student addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $student_id = $_POST['student_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $date_of_birth = $_POST['date_of_birth'];
    $major = $_POST['major'] === '' ? null : $_POST['major'];
    $academic_year = $_POST['academic_year'] === '' ? null : $_POST['academic_year'];
    $gpa = $_POST['gpa'] === '' ? null : floatval($_POST['gpa']);
    $attendance = $_POST['attendance'] === '' ? null : floatval($_POST['attendance']);
    $guide = $_POST['guide'];

    // Validate GPA
    if ($gpa !== null && ($gpa < -9.99 || $gpa > 9.99)) {
        $error_msg = "GPA must be between -9.99 and 9.99.";
    } 
    // Validate Attendance
    elseif ($attendance !== null && ($attendance < -999.99 || $attendance > 999.99)) {
        $error_msg = "Attendance must be between -999.99 and 999.99.";
    } 
    // Check if StudentID already exists
    else {
        $check_sql = "SELECT COUNT(*) FROM Students WHERE StudentID = ?";
        $check_result = safeQuery($conn, $check_sql, [$student_id], true);
        $exists = $check_result && $check_result->fetch_row()[0] > 0;

        if ($exists) {
            $error_msg = "Student ID '$student_id' already exists. Please use a unique ID.";
        } else {
            $sql = "INSERT INTO Students (StudentID, FirstName, LastName, DateOfBirth, Major, AcademicYear, GPA, Attendance, Guide) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$student_id, $first_name, $last_name, $date_of_birth, $major, $academic_year, $gpa, $attendance, $guide];
            $result = safeQuery($conn, $sql, $params);

            if ($result) {
                $success_msg = "Student added successfully!";
            } else {
                $error_msg = "An error occurred while adding student: " . $conn->error;
            }
        }
    }
}

// Fetch all students with search functionality by StudentID
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    // البحث باستخدام StudentID فقط
    $sql = "SELECT * FROM Students WHERE StudentID = ?";
    $params = [$search]; // البحث بقيمة دقيقة (بدون % لأن StudentID فريد)
    $students_result = safeQuery($conn, $sql, $params, true);
} else {
    // إذا لم يتم إدخال قيمة بحث، جلب كل الطلاب
    $sql = "SELECT * FROM Students";
    $students_result = safeQuery($conn, $sql, [], true);
}
$students = $students_result ? $students_result : null;

// Get total student count (غير متأثر بالبحث)
$studentCount_result = safeQuery($conn, "SELECT COUNT(*) as count FROM Students", [], true);
$studentCount = $studentCount_result ? $studentCount_result->fetch_assoc()['count'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | AI Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3a6ea5;
            --secondary-color: #004e98;
            --accent-color: #ff6700;
            --light-bg: #f8f9fa;
            --dark-text: #333;
            --light-text: #fff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: var(--dark-text);
        }

        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--light-text);
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .user-welcome {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .welcome-text h2 {
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }

        .welcome-text p {
            color: #666;
        }

        .dashboard-link {
            display: inline-block;
            color: var(--primary-color);
            text-decoration: none;
            padding: 0.8rem;
            border-radius: 4px;
            transition: background-color 0.2s ease;
            font-weight: 500;
            cursor: pointer;
        }

        .dashboard-link:hover {
            background-color: rgba(58, 110, 165, 0.1);
        }

        .dashboard-link i {
            margin-right: 0.5rem;
        }

        .student-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin: 20px 0;
        }

        .student-table th, .student-table td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .student-table th {
            background-color: var(--primary-color);
            color: var(--light-text);
            font-weight: bold;
        }

        .student-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .student-table tr:hover {
            background-color: rgba(58, 110, 165, 0.1);
        }

        .action-btns {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }

        .action-btns a {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            min-width: 80px;
            justify-content: center;
        }

        .action-btns a:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .view-btn {
            background-color: var(--primary-color);
        }

        .edit-btn {
            background-color: #4CAF50;
        }

        .delete-btn {
            background-color: #dc3545;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            width: 50%;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .close:hover {
            color: var(--dark-text);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: opacity 0.5s ease-out;
        }

        .alert-success {
            background-color: #dff0d8;
            color: #3c763d;
        }

        .alert-danger {
            background-color: #f2dede;
            color: #a94442;
        }

        .search-container {
            margin: 1.5rem 0;
            display: flex;
            gap: 0.5rem;
        }

        .search-container input {
            padding: 0.8rem;
            width: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .search-container button {
            padding: 0.8rem 1.2rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .search-container button:hover {
            background-color: var(--secondary-color);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .modal button {
            background-color: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .modal button:hover {
            background-color: var(--secondary-color);
        }

        @media (max-width: 768px) {
            .student-table {
                font-size: 0.9rem;
            }
            .modal-content {
                width: 80%;
            }
            .action-btns {
                flex-direction: column;
                gap: 0.3rem;
            }
            .action-btns a {
                min-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .search-container {
                flex-direction: column;
            }
            .search-container input {
                width: 100%;
            }
            .user-welcome {
                flex-direction: column;
                text-align: center;
            }
            .user-avatar {
                margin: 0 auto 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>AI Management System</h1>
        <p>Staff Dashboard - Student Management</p>
    </header>
    
    <div class="container">
        <div class="user-welcome">
            <div class="user-avatar">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="welcome-text">
                <h2>Student Management</h2>
                <p>Total Students: <?php echo $studentCount; ?></p>
                <span class="dashboard-link" onclick="addStudent()">
                    <i class="fas fa-user-plus"></i> Add New Student
                </span>
            </div>
        </div>

        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success" id="successAlert">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger">
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <form action="" method="GET">
                <input type="text" name="search" placeholder="Search by Student ID..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>

        <table class="student-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Date of Birth</th>
                    <th>Major</th>
                    <th>Academic Year</th>
                    <th>GPA</th>
                    <th>Attendance</th>
                    <th>Guide</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students && $students->num_rows > 0): ?>
                    <?php while ($student = $students->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['StudentID']); ?></td>
                            <td><?php echo htmlspecialchars($student['FirstName']); ?></td>
                            <td><?php echo htmlspecialchars($student['LastName']); ?></td>
                            <td><?php echo htmlspecialchars($student['DateOfBirth']); ?></td>
                            <td><?php echo empty($student['Major']) ? 'General' : htmlspecialchars($student['Major']); ?></td>
                            <td><?php echo htmlspecialchars($student['AcademicYear'] ?? 'Null'); ?></td>
                            <td><?php echo htmlspecialchars($student['GPA'] ?? 'Null'); ?></td>
                            <td><?php echo htmlspecialchars($student['Attendance'] ?? 'Null'); ?></td>
                            <td><?php echo htmlspecialchars($student['Guide']); ?></td>
                            <td class="action-btns">
                                <a href="#" class="view-btn" onclick="viewStudent('<?php echo $student['StudentID']; ?>')">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="#" class="edit-btn" onclick="editStudent('<?php echo $student['StudentID']; ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="view_students.php?delete_id=<?php echo $student['StudentID']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this student?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10">No student found with this ID.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Modal for viewing/editing/adding student data -->
        <div id="studentModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">×</span>
                <div id="modalContent"></div>
            </div>
        </div>
    </div>

    <script>
        function viewStudent(studentId) {
            fetch('get_student.php?id=' + studentId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                    document.getElementById('studentModal').style.display = 'block';
                });
        }

        function editStudent(studentId) {
            fetch('get_student.php?id=' + studentId + '&edit=true')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                    document.getElementById('studentModal').style.display = 'block';
                });
        }

        function addStudent() {
            const modalContent = `
                <h2>Add New Student</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Student ID:</label>
                        <input type="text" name="student_id" required>
                    </div>
                    <div class="form-group">
                        <label>First Name:</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name:</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth:</label>
                        <input type="date" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label>Major:</label>
                        <input type="text" name="major">
                    </div>
                    <div class="form-group">
                        <label>Academic Year:</label>
                        <input type="number" name="academic_year">
                    </div>
                    <div class="form-group">
                        <label>GPA (max 9.99):</label>
                        <input type="number" step="0.01" name="gpa" min="-9.99" max="9.99">
                    </div>
                    <div class="form-group">
                        <label>Attendance (max 999.99):</label>
                        <input type="number" step="0.01" name="attendance" min="-999.99" max="999.99">
                    </div>
                    <div class="form-group">
                        <label>Guide:</label>
                        <input type="text" name="guide" required>
                    </div>
                    <button type="submit" name="add_student">Add Student</button>
                </form>
            `;
            document.getElementById('modalContent').innerHTML = modalContent;
            document.getElementById('studentModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('studentModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('studentModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Hide success alert after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>