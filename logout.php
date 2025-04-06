<?php
session_start();
session_unset();  // إزالة جميع بيانات الجلسة
session_destroy(); // تدمير الجلسة

// إعادة توجيه المستخدم إلى صفحة تسجيل الدخول
header("Location: login.php");
exit();
?>
