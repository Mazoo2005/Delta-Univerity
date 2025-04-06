<?php
session_start();

$error_message = ""; // متغير لتخزين رسالة الخطأ

// لو الشخص مسجل دخول بالفعل
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'student': 
            header("Location: student.php"); 
            exit();
        case 'graduate': 
            header("Location: graduate.php"); 
            exit();
        case 'parent': 
            header("Location: parent.php"); 
            exit();
        case 'staff': 
            if (isset($_SESSION['staff_type'])) {
                if ($_SESSION['staff_type'] === 'admin') {
                    header("Location: admin.php");
                } elseif ($_SESSION['staff_type'] === 'teach') {
                    // التحقق من FacultyID
                    if (isset($_SESSION['faculty_id']) && $_SESSION['faculty_id'] == 1001) {
                        header("Location: manage_courses.php"); // صفحة إدارة المواد لـ FacultyID = 1001
                    } else {
                        header("Location: tech.php"); // صفحة الدكاترة العاديين
                    }
                } else {
                    header("Location: staff.php"); // افتراضي لو فيه خطأ أو قيمة غير متوقعة
                }
            } else {
                header("Location: staff.php"); // لو staff_type مش موجود في السيشن
            }
            exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $servername = "localhost";
    $username_db = "root";
    $password_db = "Mmina@12345";
    $dbname = "ai_management";

    $conn = new mysqli($servername, $username_db, $password_db, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $id = $_POST['id'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // تعديل الاستعلام ليشمل FacultyID بالإضافة إلى staff_type
    $stmt = $conn->prepare("SELECT id, password, role, staff_type, FacultyID FROM system_login WHERE id = ? AND role = ?");
    $stmt->bind_param("is", $id, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($password === $row['password']) { // مقارنة نص عادي
            $_SESSION['id'] = $id;
            $_SESSION['role'] = $row['role'];
            $_SESSION['faculty_id'] = $row['FacultyID']; // حفظ FacultyID في السيشن

            if ($row['role'] === 'staff') {
                $_SESSION['staff_type'] = $row['staff_type']; // حفظ نوع الستاف

                if ($row['staff_type'] === 'admin') {
                    header("Location: admin.php");
                } elseif ($row['staff_type'] === 'teach') {
                    // التحقق من FacultyID
                    if ($row['FacultyID'] == 1001) {
                        header("Location:mm.php"); // صفحة إدارة المواد لـ FacultyID = 1001
                    } else {
                        header("Location: tech.php"); // صفحة الدكاترة العاديين
                    }
                } else {
                    header("Location: staff.php"); // افتراضي في حالة أي قيمة غير متوقعة
                }
            } else {
                // باقي الأدوار
                switch ($row['role']) {
                    case 'student': header("Location: student.php"); exit();
                    case 'graduate': header("Location: graduate.php"); exit();
                    case 'parent': header("Location: parent.php"); exit();
                    default: $error_message = "⚠️ دور غير معروف!"; break;
                }
            }
            exit();
        } else {
            $error_message = "⚠️ كلمة المرور غير صحيحة!";
        }
    } else {
        $error_message = "⚠️ الحساب غير موجود!";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Smart Learning</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <img src="logo.png" alt="Delta University Logo" class="logo">
        <h1>Smart Learning Management System</h1>
    </header>
    <div class="login-container">
        <?php if (!empty($error_message)): ?>
            <p style="color: red; text-align: center;">
                <?php echo $error_message; ?>
            </p>
        <?php endif; ?>

        <form action="login.php" method="post">
            <input type="text" id="id" name="id" placeholder="ID" required>
            <div class="role-selection">
                <input type="radio" id="student" name="role" value="student" checked>
                <label for="student">Student</label>
                <input type="radio" id="graduate" name="role" value="graduate">
                <label for="graduate">Graduate</label>
                <input type="radio" id="parent" name="role" value="parent">
                <label for="parent">Parent</label>
                <input type="radio" id="staff" name="role" value="staff">
                <label for="staff">Staff</label>
            </div>
            <input type="password" id="password" name="password" placeholder="Password" required>
            <a href="#">Forget your password?</a>
            <button type="submit">Login</button>
        </form>
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
            <div class="team-member" onclick="openWhatsApp('1234567893', 'Financial Aid')">
                <div class="img">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="team-member-info">
                    <div class="team-member-name">Financial Aid</div>
                    <div class="team-member-role">Payments & Fees</div>
                </div>
                <div class="team-member-status"></div>
            </div>
        </div>
    </div>
    <div class="background-image"></div>
    <footer>
        © 2023 Team. All rights reserved.
    </footer>
    <script>
    // Toggle WhatsApp Team List
    document.getElementById('whatsappIcon').addEventListener('click', function() {
        document.getElementById('teamList').classList.toggle('active');
    });

    // Open WhatsApp Chat
    function openWhatsApp(phoneNumber, memberName) {
        const message = `Hello ${memberName}, I need assistance with the Smart College Management System.`;
        const url = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
        window.open(url, '_blank');
    }
    </script>
</body>
</html>