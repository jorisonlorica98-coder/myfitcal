cmd - "C:\xampp\php\php-cgi.exe" "C:\xampp\htdocs\myfitcal_system\cron\send_workout_reminder.php"

email delete - DELETE FROM email_notifications WHERE DATE(sent_at) = CURDATE();
SELECT * FROM email_notifications WHERE DATE(sent_at) = CURDATE();