<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// User authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff' || $_SESSION['staff_type'] !== 'teach') {
    header("Location: login.php");
    exit();
}

// Database connection details
$servername = "localhost";
$username_db = "root";
$password_db = "Mmina@12345";
$dbname = "AI_Management";

// Establish database connection
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Check connection success
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Validate session ID and FacultyID
if (!isset($_SESSION['id']) || !is_numeric($_SESSION['id']) || !isset($_SESSION['faculty_id'])) {
    die("Error: Invalid session ID or FacultyID.");
}

$facultyID = (int)$_SESSION['faculty_id'];

// Function to execute queries safely
function safeQuery($conn, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $types = str_repeat('i', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Retrieve faculty member data
$facultyData = safeQuery($conn, "SELECT FirstName, LastName, Department, OfficeHours, FacultyRank FROM Faculty WHERE FacultyID = ?", [$facultyID])->fetch_assoc();

$facultyName = $facultyData ? htmlspecialchars($facultyData['FirstName'] . ' ' . $facultyData['LastName']) : 'Faculty Member';
$userRole = $facultyData['FacultyRank'] ?? 'Professor';
$department = $facultyData['Department'] ?? 'Not Specified';
$officeHours = $facultyData['OfficeHours'] ?? 'Not Available';

// User statistics
$myCourses = safeQuery($conn, "SELECT COUNT(*) as count FROM Courses WHERE Instructor = ?", [$facultyID])->fetch_assoc()['count'] ?? 0;
$myStudents = safeQuery($conn, "SELECT COUNT(DISTINCT r.StudentID) as count FROM Registration r
                                JOIN Courses c ON r.CourseID = c.CourseID
                                WHERE c.Instructor = ?", [$facultyID])->fetch_assoc()['count'] ?? 0;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    error_log("POST Data: " . print_r($_POST, true)); // Log incoming data
    $response = ['status' => 'error', 'message' => 'Invalid request'];

    // Handle Notification Submission
    if (isset($_POST['add_notification'])) {
        $title = trim($_POST['notification_title'] ?? '');
        $content = trim($_POST['notification_content'] ?? '');
        $target_level = $_POST['target_level'] ?? 'all';
        $notification_type = $_POST['notification_type'] ?? 'general';
        $expiry_date = $_POST['expiry_date'] ?? NULL;

        if (!empty($title) && !empty($content)) {
            if ($conn->ping()) {
                $sql = "INSERT INTO Notifications 
                        (Title, Content, SenderID, SenderType, TargetLevel, NotificationType, ExpiryDate) 
                        VALUES (?, ?, ?, 'faculty', ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssisss', $title, $content, $facultyID, $target_level, $notification_type, $expiry_date);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response = ['status' => 'success', 'message' => 'Notification added successfully'];
                    } else {
                        $response = ['status' => 'error', 'message' => 'No rows inserted into Notifications table'];
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'Error adding notification: ' . $stmt->error];
                }
                $stmt->close();
            } else {
                $response = ['status' => 'error', 'message' => 'Database connection lost'];
            }
        } else {
            $response = ['status' => 'error', 'message' => 'Title and content are required'];
        }
    }

    // Handle Registration Settings (only for FacultyID = 1001)
    if ($facultyID == 1001) {
        // Toggle Registration Status
        if (isset($_POST['toggle_registration'])) {
            $level = $_POST['level'];
            $isOpen = isset($_POST['is_open']) ? 1 : 0;

            if ($conn->ping()) {
                $sql = "UPDATE RegistrationSettings SET IsRegistrationOpen = ? WHERE AcademicLevel = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('is', $isOpen, $level);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0 || $stmt->affected_rows == 0) { // 0 is OK if no change needed
                        $response = ['status' => 'success', 'message' => 'Registration status updated successfully'];
                    } else {
                        $response = ['status' => 'error', 'message' => 'No rows updated in RegistrationSettings'];
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'Error updating registration status: ' . $stmt->error];
                }
                $stmt->close();
            } else {
                $response = ['status' => 'error', 'message' => 'Database connection lost'];
            }
        }

        // Update Allowed Courses
        if (isset($_POST['update_courses'])) {
            $level = $_POST['level'];
            $allowedCourses = isset($_POST['allowed_courses']) ? implode(',', $_POST['allowed_courses']) : '';

            if ($conn->ping()) {
                $sql = "UPDATE RegistrationSettings SET AllowedCourses = ? WHERE AcademicLevel = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ss', $allowedCourses, $level);

                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Allowed courses updated successfully'];
                } else {
                    $response = ['status' => 'error', 'message' => 'Error updating allowed courses: ' . $stmt->error];
                }
                $stmt->close();
            } else {
                $response = ['status' => 'error', 'message' => 'Database connection lost'];
            }
        }

        // Add Schedule (Groups, Lectures, Sections)
        if (isset($_POST['add_schedule'])) {
            $courseID = $_POST['course_id'];
            $groupCount = $_POST['group_count'];
            $lectureTimes = $_POST['lecture_times'] ?? [];
            $sectionTimes = $_POST['section_times'] ?? [];
            $lectureClassRooms = $_POST['lecture_class_rooms'] ?? [];
            $sectionClassRooms = $_POST['section_class_rooms'] ?? [];
            $sectionsPerGroup = $_POST['sections_per_group'] ?? 1;
            $studentsPerSection = $_POST['students_per_section'] ?? 30;

            if ($conn->ping()) {
                $deleteSql = "DELETE FROM Schedule WHERE CourseID = ?";
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->bind_param('i', $courseID);
                $deleteStmt->execute();
                $deleteStmt->close();

                $success = false;
                $rowsInserted = 0;
                $errorMsg = '';

                for ($i = 1; $i <= $groupCount; $i++) {
                    $lectureTime = trim($lectureTimes[$i] ?? '');
                    $lectureClassRoom = trim($lectureClassRooms[$i] ?? '');

                    if (!empty($lectureTime)) {
                        $timeSlot = "Lecture: $lectureTime";
                        $sql = "INSERT INTO Schedule (CourseID, FacultyID, ClassRoom, TimeSlot, SectionsPerGroup, StudentsPerSection) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('iissii', $courseID, $facultyID, $lectureClassRoom, $timeSlot, $sectionsPerGroup, $studentsPerSection);

                        if ($stmt->execute() && $stmt->affected_rows > 0) {
                            $rowsInserted++;
                        } else {
                            $errorMsg = $stmt->error;
                            break;
                        }
                        $stmt->close();

                        for ($j = 1; $j <= $sectionsPerGroup; $j++) {
                            $sectionTime = trim($sectionTimes[$i][$j] ?? '');
                            $sectionClassRoom = trim($sectionClassRooms[$i][$j] ?? '');
                            if (!empty($sectionTime)) {
                                $timeSlot = "Lecture: $lectureTime, Section $j: $sectionTime";
                                $sql = "INSERT INTO Schedule (CourseID, FacultyID, ClassRoom, TimeSlot, SectionsPerGroup, StudentsPerSection) 
                                        VALUES (?, ?, ?, ?, ?, ?)";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param('iissii', $courseID, $facultyID, $sectionClassRoom, $timeSlot, $sectionsPerGroup, $studentsPerSection);

                                if ($stmt->execute() && $stmt->affected_rows > 0) {
                                    $rowsInserted++;
                                } else {
                                    $errorMsg = $stmt->error;
                                    break 2;
                                }
                                $stmt->close();
                            }
                        }
                        $success = true;
                    }
                }

                if ($success && $rowsInserted > 0) {
                    $response = ['status' => 'success', 'message' => "Schedule saved successfully ($rowsInserted rows inserted)"];
                } else {
                    $response = ['status' => 'error', 'message' => 'Error saving schedule: ' . ($errorMsg ?: 'No valid data provided')];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Database connection lost'];
            }
        }
    }

    echo json_encode($response);
    exit();
}

// Retrieve Registration Settings
$regSettings = [];
$result = $conn->query("SELECT * FROM RegistrationSettings");
while ($row = $result->fetch_assoc()) {
    $regSettings[$row['AcademicLevel']] = $row;
}

// Retrieve All Courses for Selection
$courses = [];
$result = $conn->query("SELECT CourseID, CourseName, AcademicLevel FROM Courses ORDER BY AcademicLevel, CourseName");
while ($row = $result->fetch_assoc()) {
    $courses[$row['AcademicLevel']][] = $row;
}

// Retrieve current schedule for editing
$currentSchedule = [];
if (isset($_GET['edit_course'])) {
    $editCourseID = (int)$_GET['edit_course'];
    $result = safeQuery($conn, "SELECT * FROM Schedule WHERE CourseID = ?", [$editCourseID]);
    while ($row = $result->fetch_assoc()) {
        $currentSchedule[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        header {
            background-color: var(--secondary-color);
            color: white;
            padding: 20px 0;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .user-welcome {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background-color: var(--light-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 36px;
            color: var(--primary-color);
        }
        
        .welcome-text h2 {
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .welcome-text p {
            margin-bottom: 5px;
            color: #666;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .staff-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .dashboard-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        
        .card-header i {
            margin-right: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .dashboard-link {
            display: block;
            padding: 12px 15px;
            background-color: var(--light-color);
            margin-bottom: 10px;
            border-radius: 5px;
            color: var(--dark-color);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .dashboard-link:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateX(5px);
        }
        
        .dashboard-link i {
            margin-right: 8px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .schedule-form {
            margin-top: 20px;
        }
        
        .group-schedule {
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        
        .group-schedule h4 {
            margin-top: 0;
            color: var(--secondary-color);
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        
        .support-section {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .whatsapp-icon {
            width: 60px;
            height: 60px;
            background-color: #25D366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }
        
        .whatsapp-icon:hover {
            transform: scale(1.1);
        }
        
        .team-list {
            position: absolute;
            bottom: 70px;
            right: 0;
            width: 300px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            padding: 15px;
            display: none;
        }
        
        .team-list.active {
            display: block;
        }
        
        .team-list h3 {
            margin-top: 0;
            color: var(--secondary-color);
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .team-member {
            display: flex;
            align-items: center;
            padding: 10px 0;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 1px solid #eee;
        }
        
        .team-member:last-child {
            border-bottom: none;
        }
        
        .team-member:hover {
            background-color: #f5f5f5;
        }
        
        .team-member .img {
            width: 40px;
            height: 40px;
            background-color: var(--light-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .team-member-info {
            flex: 1;
        }
        
        .team-member-name {
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .team-member-role {
            font-size: 12px;
            color: #666;
        }
        
        .team-member-status {
            width: 10px;
            height: 10px;
            background-color: #2ecc71;
            border-radius: 50%;
        }
        
        .logout-section {
            text-align: center;
            margin-top: 30px;
            padding: 20px 0;
            border-top: 1px solid #eee;
        }
        
        .logout-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background-color: var(--accent-color);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background-color: #c0392b;
        }
        
        .logout-btn i {
            margin-right: 8px;
        }
        
        .course-selector {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .course-option {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .course-option:hover {
            background-color: #f0f8ff;
        }
        
        .course-option input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .section-settings {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .section-settings h5 {
            margin-bottom: 15px;
            color: var(--secondary-color);
        }
        
        .capacity-summary {
            background-color: #e9f7ef;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .notification-card .alert,
        .registration-card .alert {
            margin-top: 15px;
        }
        
        .global-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            min-width: 300px;
            opacity: 0;
            transition: opacity 0.5s;
            display: none;
        }
        
        .global-alert.show {
            opacity: 1;
            display: block;
        }
        
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .staff-dashboard {
                grid-template-columns: 1fr;
            }
            
            .team-list {
                width: 250px;
            }
            
            .global-alert {
                left: 20px;
                right: 20px;
                width: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Global Alert Message -->
    <div id="global-alert" class="global-alert"></div>

    <header>
        <h1>Learning Management System</h1>
        <p>Faculty Dashboard</p>
    </header>

    <div class="container">
        <div class="user-welcome">
            <div class="user-avatar">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="welcome-text">
                <h2>Welcome, <?php echo $facultyName; ?>!</h2>
                <p><?php echo $userRole; ?> at the Learning Management System</p>
                <p>Department: <?php echo $department; ?></p>
                <p>Office Hours: <?php echo $officeHours; ?></p>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-box">
                <i class="fas fa-book fa-2x" style="color: var(--primary-color);"></i>
                <div class="stat-value"><?php echo $myCourses; ?></div>
                <div class="stat-label">My Courses</div>
            </div>
            <div class="stat-box">
                <i class="fas fa-user-graduate fa-2x" style="color: var(--primary-color);"></i>
                <div class="stat-value"><?php echo $myStudents; ?></div>
                <div class="stat-label">Enrolled Students</div>
            </div>
            <div class="stat-box">
                <i class="fas fa-calendar-alt fa-2x" style="color: var(--primary-color);"></i>
                <div class="stat-value"><?php echo date('m/d/Y'); ?></div>
                <div class="stat-label">Today's Date</div>
            </div>
        </div>

        <div class="staff-dashboard">
            <div class="dashboard-card notification-card">
                <div class="card-header">
                    <i class="fas fa-book-open"></i> Course Management
                </div>
                <div class="card-body">
                    <a href="my_courses.php" class="dashboard-link">
                        <i class="fas fa-list"></i> My Courses
                    </a>
                    <a href="upload_materials.php" class="dashboard-link">
                        <i class="fas fa-file-upload"></i> Upload Materials
                    </a>
                    <a href="course_content.php" class="dashboard-link">
                        <i class="fas fa-book-reader"></i> Course Content
                    </a>
                </div>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-user-graduate"></i> Academic Guidance
                </div>
                <div class="card-body">
                    <a href="academic_guidance.php" class="dashboard-link">
                        <i class="fas fa-check-circle"></i> Confirm Student Registrations
                    </a>
                </div>
            </div>
            
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-users"></i> Student Management
                </div>
                <div class="card-body">
                    <a href="student_list.php" class="dashboard-link">
                        <i class="fas fa-list"></i> Student List
                    </a>
                    <a href="attendance.php" class="dashboard-link">
                        <i class="fas fa-clipboard-check"></i> Attendance
                    </a>
                    <a href="grades_entry.php" class="dashboard-link">
                        <i class="fas fa-chart-line"></i> Enter Grades
                    </a>
                    <a href="student_progress.php" class="dashboard-link">
                        <i class="fas fa-graduation-cap"></i> Student Progress
                    </a>
                </div>
            </div>

            <div class="dashboard-card notification-card">
                <div class="card-header">
                    <i class="fas fa-bell"></i> Notification Management
                </div>
                <div class="card-body">
                    <form id="notification-form" method="POST">
                        <div class="form-group">
                            <label for="notification_title">Notification Title</label>
                            <input type="text" id="notification_title" name="notification_title" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="notification_content">Notification Content</label>
                            <textarea id="notification_content" name="notification_content" rows="3" required class="form-control"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="target_level">Target Level</label>
                            <select id="target_level" name="target_level" class="form-control">
                                <option value="all">All Levels</option>
                                <option value="Level1">Level 1</option>
                                <option value="Level2">Level 2</option>
                                <option value="Level3">Level 3</option>
                                <option value="Level4">Level 4</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notification_type">Notification Type</label>
                            <select id="notification_type" name="notification_type" class="form-control">
                                <option value="general">General</option>
                                <option value="academic">Academic</option>
                                <option value="administrative">Administrative</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="expiry_date">Expiry Date</label>
                            <input type="datetime-local" id="expiry_date" name="expiry_date" class="form-control">
                        </div>
                        <button type="submit" name="add_notification" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Notification
                        </button>
                    </form>
                </div>
            </div>

            <!-- Registration and Schedule Management (only for FacultyID = 1001) -->
            <?php if ($facultyID == 1001): ?>
            <div class="dashboard-card registration-card">
                <div class="card-header">
                    <i class="fas fa-cog"></i> Registration & Schedule Management
                </div>
                <div class="card-body">
                    <!-- Registration Toggle -->
                    <h3>Registration Management</h3>
                    <?php foreach (['Level1', 'Level2', 'Level3', 'Level4'] as $level): ?>
                        <div class="form-group">
                            <h4>Level: <?php echo $level; ?></h4>
                            <form method="POST" name="toggle_registration">
                                <input type="hidden" name="level" value="<?php echo $level; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="checkbox" name="is_open" id="is_open_<?php echo $level; ?>" value="1" <?php echo $regSettings[$level]['IsRegistrationOpen'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_open_<?php echo $level; ?>">
                                            Open Registration
                                        </label>
                                    </div>
                                    <button type="submit" name="toggle_registration" class="btn btn-primary btn-sm">Update</button>
                                </div>
                            </form>

                            <!-- Allowed Courses Selection -->
                            <form method="POST" name="update_courses" class="mt-3">
                                <input type="hidden" name="level" value="<?php echo $level; ?>">
                                <label class="mb-2">Allowed Courses:</label>
                                
                                <div class="course-selector">
                                    <?php if (isset($courses[$level])): ?>
                                        <?php 
                                        $allowedCourses = explode(',', $regSettings[$level]['AllowedCourses']);
                                        foreach ($courses[$level] as $course): 
                                            $selected = in_array($course['CourseID'], $allowedCourses) ? 'checked' : '';
                                        ?>
                                            <div class="course-option">
                                                <input type="checkbox" name="allowed_courses[]" value="<?php echo $course['CourseID']; ?>" id="course_<?php echo $course['CourseID']; ?>" <?php echo $selected; ?>>
                                                <label for="course_<?php echo $course['CourseID']; ?>"><?php echo $course['CourseName']; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted">No courses registered for this level</div>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="submit" name="update_courses" class="btn btn-primary mt-2">
                                    <i class="fas fa-save"></i> Save Allowed Courses
                                </button>
                            </form>
                        </div>
                        <hr>
                    <?php endforeach; ?>

                    <!-- Schedule Management -->
                    <h3 class="mt-4">Schedule Management</h3>
                    <form method="POST" class="schedule-form" id="schedule-form">
                        <div class="form-group">
                            <label for="course_id">Select Course:</label>
                            <select name="course_id" id="course_id" class="form-control" required>
                                <option value="">-- Select Course --</option>
                                <?php foreach ($courses as $level => $levelCourses): ?>
                                    <optgroup label="Level: <?php echo $level; ?>">
                                        <?php foreach ($levelCourses as $course): ?>
                                            <option value="<?php echo $course['CourseID']; ?>" <?php echo (isset($_GET['edit_course']) && $_GET['edit_course'] == $course['CourseID']) ? 'selected' : ''; ?>>
                                                <?php echo $course['CourseName']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="section-settings">
                            <h5>Section Configuration</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sections_per_group">Sections per Group:</label>
                                        <input type="number" name="sections_per_group" id="sections_per_group" min="1" max="10" 
                                               class="form-control" value="<?php echo !empty($currentSchedule) ? $currentSchedule[0]['SectionsPerGroup'] : '1'; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="students_per_section">Students per Section:</label>
                                        <input type="number" name="students_per_section" id="students_per_section" min="1" max="100" 
                                               class="form-control" value="<?php echo !empty($currentSchedule) ? $currentSchedule[0]['StudentsPerSection'] : '30'; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="capacity-summary">
                                <div>Total Sections: <span id="total_sections"><?php echo !empty($currentSchedule) ? count($currentSchedule) * $currentSchedule[0]['SectionsPerGroup'] : '0'; ?></span></div>
                                <div>Total Student Capacity: <span id="total_students"><?php echo !empty($currentSchedule) ? count($currentSchedule) * $currentSchedule[0]['SectionsPerGroup'] * $currentSchedule[0]['StudentsPerSection'] : '0'; ?></span></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="group_count">Number of Groups:</label>
                            <input type="number" name="group_count" id="group_count" min="1" max="10" class="form-control" required 
                                   value="<?php echo count($currentSchedule) > 0 ? count($currentSchedule) : '1'; ?>">
                        </div>
                        
                        <div id="group_schedules">
                            <?php if (!empty($currentSchedule)): ?>
                                <?php 
                                $groupIndex = 0;
                                $currentGroup = 1;
                                $lectureShown = false;
                                foreach ($currentSchedule as $schedule): 
                                    $timeSlot = $schedule['TimeSlot'];
                                    $isLectureOnly = strpos($timeSlot, 'Section') === false;
                                    $timeParts = explode(', Section ', str_replace('Lecture: ', '', $timeSlot));
                                    $lectureTime = $timeParts[0] ?? '';
                                    $sectionTime = isset($timeParts[1]) ? str_replace('Section ', '', $timeParts[1]) : '';
                                    $sectionNum = $sectionTime ? preg_replace('/[^0-9]/', '', $sectionTime) : '';
                                    $sectionTimeClean = $sectionTime ? preg_replace('/[0-9]/', '', $sectionTime) : '';

                                    if ($groupIndex % ($schedule['SectionsPerGroup'] + 1) == 0) {
                                        if ($groupIndex > 0) {
                                            echo '</div>';
                                        }
                                        echo '<div class="group-schedule">';
                                        echo "<h4>Group $currentGroup</h4>";
                                        $lectureShown = false;
                                        $currentGroup++;
                                    }

                                    if ($isLectureOnly && !$lectureShown) {
                                        echo '<div class="form-group">';
                                        echo '<label>Lecture Time:</label>';
                                        echo '<input type="datetime-local" name="lecture_times[' . ($currentGroup - 1) . ']" class="form-control" value="' . htmlspecialchars($lectureTime) . '">';
                                        echo '</div>';
                                        echo '<div class="form-group">';
                                        echo '<label>Lecture Classroom:</label>';
                                        echo '<input type="text" name="lecture_class_rooms[' . ($currentGroup - 1) . ']" placeholder="Example: Room 101" class="form-control" value="' . htmlspecialchars($schedule['ClassRoom']) . '">';
                                        echo '</div>';
                                        $lectureShown = true;
                                    } elseif (!$isLectureOnly) {
                                ?>
                                    <div class="form-group">
                                        <label>Section <?php echo $sectionNum; ?> Time:</label>
                                        <input type="datetime-local" name="section_times[<?php echo ($currentGroup - 1); ?>][<?php echo $sectionNum; ?>]" class="form-control" value="<?php echo htmlspecialchars($sectionTimeClean); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Section <?php echo $sectionNum; ?> Classroom:</label>
                                        <input type="text" name="section_class_rooms[<?php echo ($currentGroup - 1); ?>][<?php echo $sectionNum; ?>]" placeholder="Example: Room 102" class="form-control" value="<?php echo htmlspecialchars($schedule['ClassRoom']); ?>">
                                    </div>
                                <?php 
                                    }
                                    $groupIndex++;
                                    if ($groupIndex == count($currentSchedule)) {
                                        echo '</div>';
                                    }
                                endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Save Schedule Button -->
                        <button type="submit" name="add_schedule" class="btn btn-primary mt-3">
                            <i class="fas fa-save"></i> Save Schedule
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- WhatsApp Support -->
    <div class="support-section">
        <div class="whatsapp-icon" id="whatsappIcon">
            <i class="fab fa-whatsapp"></i>
        </div>
        <div class="team-list" id="teamList">
            <h3>Support Team</h3>
            <div class="team-member" onclick="openWhatsApp('+201032286185', 'Technical Support')">
                <div class="img">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="team-member-info">
                    <div class="team-member-name">Technical Support</div>
                    <div class="team-member-role">System Issues</div>
                </div>
                <div class="team-member-status"></div>
            </div>
            <div class="team-member" onclick="openWhatsApp('+201222746697', 'Student Affairs')">
                <div class="img">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="team-member-info">
                    <div class="team-member-name">Student Affairs</div>
                    <div class="team-member-role">Registration & Admin</div>
                </div>
                <div class="team-member-status"></div>
            </div>
            <div class="team-member" onclick="openWhatsApp('+201140650201', 'IT Department')">
                <div class="img">
                    <i class="fas fa-laptop-code"></i>
                </div>
                <div class="team-member-info">
                    <div class="team-member-name">IT Department</div>
                    <div class="team-member-role">Account Issues</div>
                </div>
                <div class="team-member-status"></div>
            </div>
        </div>
    </div>

    <div class="logout-section">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Dashboard loaded: ' + new Date().toLocaleDateString());

        // Dynamic group schedule fields with datetime-local
        const groupCountInput = document.getElementById('group_count');
        const groupSchedulesDiv = document.getElementById('group_schedules');
        const sectionsPerGroupInput = document.getElementById('sections_per_group');
        const studentsPerSectionInput = document.getElementById('students_per_section');

        function updateGroupFields() {
            const count = parseInt(groupCountInput.value) || 0;
            const sectionsPerGroup = parseInt(sectionsPerGroupInput.value) || 1;
            groupSchedulesDiv.innerHTML = '';

            for (let i = 1; i <= count; i++) {
                const groupDiv = document.createElement('div');
                groupDiv.className = 'group-schedule';
                let groupHTML = `
                    <h4>Group ${i}</h4>
                    <div class="form-group">
                        <label>Lecture Time:</label>
                        <input type="datetime-local" name="lecture_times[${i}]" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Lecture Classroom:</label>
                        <input type="text" name="lecture_class_rooms[${i}]" placeholder="Example: Room 101" class="form-control">
                    </div>`;

                for (let j = 1; j <= sectionsPerGroup; j++) {
                    groupHTML += `
                        <div class="form-group">
                            <label>Section ${j} Time:</label>
                            <input type="datetime-local" name="section_times[${i}][${j}]" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Section ${j} Classroom:</label>
                            <input type="text" name="section_class_rooms[${i}][${j}]" placeholder="Example: Room 102" class="form-control">
                        </div>`;
                }

                groupDiv.innerHTML = groupHTML;
                groupSchedulesDiv.appendChild(groupDiv);
            }
            calculateTotals();
        }

        function calculateTotals() {
            const sectionsPerGroup = parseInt(sectionsPerGroupInput.value) || 0;
            const studentsPerSection = parseInt(studentsPerSectionInput.value) || 0;
            const groupCount = parseInt(groupCountInput.value) || 0;
            
            const totalSections = sectionsPerGroup * groupCount;
            const totalStudents = totalSections * studentsPerSection;
            
            document.getElementById('total_sections').textContent = totalSections;
            document.getElementById('total_students').textContent = totalStudents;
        }

        sectionsPerGroupInput.addEventListener('change', function() {
            updateGroupFields();
            calculateTotals();
        });
        studentsPerSectionInput.addEventListener('change', calculateTotals);
        groupCountInput.addEventListener('change', function() {
            updateGroupFields();
            calculateTotals();
        });

        if (<?php echo empty($currentSchedule) ? 'true' : 'false'; ?>) {
            groupCountInput.addEventListener('change', updateGroupFields);
            if (groupCountInput.value) {
                updateGroupFields();
            }
        } else {
            calculateTotals();
        }

        // WhatsApp support toggle
        const whatsappIcon = document.getElementById('whatsappIcon');
        const teamList = document.getElementById('teamList');

        if (whatsappIcon && teamList) {
            whatsappIcon.addEventListener('click', function() {
                teamList.classList.toggle('active');
            });
            
            document.addEventListener('click', function(event) {
                if (!whatsappIcon.contains(event.target) && !teamList.contains(event.target)) {
                    teamList.classList.remove('active');
                }
            });
        }
        
        function showGlobalAlert(type, message) {
            const alertDiv = document.getElementById('global-alert');
            alertDiv.className = `alert alert-${type} global-alert show`;
            alertDiv.textContent = message;
            
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => {
                    alertDiv.textContent = '';
                    alertDiv.className = 'global-alert';
                }, 500);
            }, 5000);
        }

        // Handle Notification Form Submission
        const notificationForm = document.getElementById('notification-form');
        if (notificationForm) {
            notificationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('add_notification', 'true');
                
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    showGlobalAlert(data.status, data.message);
                    if (data.status === 'success') {
                        this.reset();
                    }
                })
                .catch(error => {
                    showGlobalAlert('error', 'An error occurred while processing your request');
                });
            });
        }

        // Handle Registration Toggle Forms
        document.querySelectorAll('form[name="toggle_registration"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('toggle_registration', 'true');
                
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    showGlobalAlert(data.status, data.message);
                })
                .catch(error => {
                    showGlobalAlert('error', 'An error occurred while updating registration status');
                });
            });
        });

        // Handle Allowed Courses Forms
        document.querySelectorAll('form[name="update_courses"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('update_courses', 'true');
                
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    showGlobalAlert(data.status, data.message);
                })
                .catch(error => {
                    showGlobalAlert('error', 'An error occurred while updating allowed courses');
                });
            });
        });

        // Handle Schedule Form
        const scheduleForm = document.getElementById('schedule-form');
        if (scheduleForm) {
            scheduleForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('add_schedule', 'true');
                
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    showGlobalAlert(data.status, data.message);
                    if (data.status === 'success') {
                        updateGroupFields();
                    }
                })
                .catch(error => {
                    showGlobalAlert('error', 'An error occurred while saving schedule');
                });
            });
        }
        
        calculateTotals();
    });

    function openWhatsApp(phoneNumber, memberName) {
        const message = `Hello ${memberName}, I need assistance with the Learning Management System.`;
        const url = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
        window.open(url, '_blank');
    }
    </script>
</body>
</html>