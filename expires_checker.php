<?php
//项目作者：ZherKing 项目链接：https://github.com/ZherKing/SakuraPanel_ExpiresChecker
//记得点个Star 呗 感谢你的支持！

require_once __DIR__ . '/core/Smtp.php';
require_once __DIR__ . '/configuration.php';

// 日志文件路径
$log_file = __DIR__ . '/logs/expires_checker.log'; // 请确保 logs 目录存在并有写入权限

// 数据库连接
$conn = new mysqli($_config['db_host'], $_config['db_user'], $_config['db_pass'], $_config['db_name']);

// 检查连接是否成功
if ($conn->connect_error) {
    write_log("数据库连接失败: " . $conn->connect_error, $log_file);
    die("数据库连接失败: " . $conn->connect_error);
}

// 当前时间
$current_time = time();

// 查询过期用户
$sql = "SELECT id, username, email, expires_at FROM users WHERE expires_at IS NOT NULL AND expires_at < NOW()";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        $username = $row['username'];
        $email = $row['email'];

        // 更新用户权限组为 'default' 并将过期时间设置为 2099-12-31
        $update_sql = "UPDATE users SET `group` = 'default', `expires_at` = '2099-12-31' WHERE id = $user_id";
        if ($conn->query($update_sql) === TRUE) {
            $message = "用户 $username 的权限组已更新为 default，过期时间已设置为 2099-12-31\n";
            echo $message;
            write_log($message, $log_file);

            // 发送通知邮件
            send_notification($email, $username);
            send_admin_notification($username, $email);
        } else {
            $message = "更新用户 $username 失败: " . $conn->error . "\n";
            echo $message;
            write_log($message, $log_file);
        }
    }
} else {
    $message = "没有需要更新的用户。\n";
    echo $message;
    write_log($message, $log_file);
}

// 提前3天提醒即将过期的用户
$three_days_later = date('Y-m-d', strtotime('+3 days'));
$reminder_sql = "SELECT id, username, email, expires_at FROM users WHERE expires_at = '$three_days_later'";
$reminder_result = $conn->query($reminder_sql);

if ($reminder_result->num_rows > 0) {
    while ($row = $reminder_result->fetch_assoc()) {
        $username = $row['username'];
        $email = $row['email'];

        // 发送过期提醒邮件
        send_reminder($email, $username);
    }
}

// 关闭数据库连接
$conn->close();

// 发送通知邮件给用户
function send_notification($email, $username) {
    global $_config, $log_file;

    $subject = "[XX穿透]您的账户已过期";
    $message = "亲爱的 $username 您的账户已过期，已经取消掉您的特权！请及时续费。";

    $smtp = new SakuraPanel\Smtp($_config['smtp']['host'], $_config['smtp']['port'], true, $_config['smtp']['user'], $_config['smtp']['pass']);

    if ($smtp->sendMail($email, $_config['smtp']['mail'], $subject, $message, "HTML")) {
        write_log("通知邮件已发送给用户: $username ($email)\n", $log_file);
    } else {
        write_log("无法发送通知邮件给用户: $username ($email)\n", $log_file);
    }
}

// 发送提醒邮件给即将过期的用户
function send_reminder($email, $username) {
    global $_config, $log_file;

    $subject = "[XX穿透]您的账户即将过期";
    $message = "亲爱的 $username 您的账户将在3天后过期，请及时续费以避免影响使用。";

    $smtp = new SakuraPanel\Smtp($_config['smtp']['host'], $_config['smtp']['port'], true, $_config['smtp']['user'], $_config['smtp']['pass']);

    if ($smtp->sendMail($email, $_config['smtp']['mail'], $subject, $message, "HTML")) {
        write_log("过期提醒邮件已发送给用户: $username ($email)\n", $log_file);
    } else {
        write_log("无法发送过期提醒邮件给用户: $username ($email)\n", $log_file);
    }
}

// 发送通知邮件给管理员
function send_admin_notification($username, $user_email) {
    global $_config, $log_file;
// 这边输入管理员的邮箱 用于通知管理员
    $admin_emails = ['XXXXXX@qq.com', 'XXXXXX@qq.com']; // 手动输入管理员邮箱
    $subject = "[XX穿透-管理员]用户 $username 已过期";
    $message = "用户 $username (邮箱: $user_email) 的账户已过期，权限组已更改为 default，过期时间已设置为 2099-12-31。";

    $smtp = new SakuraPanel\Smtp($_config['smtp']['host'], $_config['smtp']['port'], true, $_config['smtp']['user'], $_config['smtp']['pass']);

    foreach ($admin_emails as $admin_email) {
        if ($smtp->sendMail($admin_email, $_config['smtp']['mail'], $subject, $message, "HTML")) {
            write_log("通知邮件已发送给管理员: $admin_email\n", $log_file);
        } else {
            write_log("无法发送通知邮件给管理员: $admin_email\n", $log_file);
        }
    }
}

// 日志记录函数
function write_log($message, $log_file) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message, FILE_APPEND);
}
