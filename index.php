<?php
session_start();
$db = new SQLite3('breadsquad.db');
$db->exec("PRAGMA journal_mode=WAL");
$db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, password TEXT, email TEXT UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS pastes (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, content TEXT, file_name TEXT, file_content BLOB, user_id INTEGER, username TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS market (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, description TEXT, image_name TEXT, image_content BLOB, contact TEXT, user_id INTEGER, username TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS courses (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, content TEXT, category TEXT, user_id INTEGER, username TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                $username = sanitize($_POST['username']);
                $password = password_hash(sanitize($_POST['password']), PASSWORD_DEFAULT);
                $email = sanitize($_POST['email']);
                $stmt = $db->prepare("INSERT INTO users (username, password, email) VALUES (:username, :password, :email)");
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->bindValue(':password', $password, SQLITE3_TEXT);
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $stmt->execute();
                $_SESSION['user_id'] = $db->lastInsertRowID();
                $_SESSION['username'] = $username;
                break;

            case 'login':
                $username = sanitize($_POST['username']);
                $password = sanitize($_POST['password']);
                $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = :username");
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $result = $stmt->execute();
                $user = $result->fetchArray(SQLITE3_ASSOC);
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                }
                break;

            case 'logout':
                session_destroy();
                break;

            case 'create_paste':
                $title = sanitize($_POST['title']);
                $content = sanitize($_POST['content']);
                $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Anonymous';
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                
                $file_name = '';
                $file_content = '';
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    if ($_FILES['file']['size'] <= 40 * 1024 * 1024) {
                        $file_name = sanitize(basename($_FILES['file']['name']));
                        $file_content = file_get_contents($_FILES['file']['tmp_name']);
                    }
                }
                
                $stmt = $db->prepare("INSERT INTO pastes (title, content, file_name, file_content, user_id, username) VALUES (:title, :content, :file_name, :file_content, :user_id, :username)");
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':content', $content, SQLITE3_TEXT);
                $stmt->bindValue(':file_name', $file_name, SQLITE3_TEXT);
                $stmt->bindValue(':file_content', $file_content, SQLITE3_BLOB);
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->execute();
                break;

            case 'create_market_item':
                $title = sanitize($_POST['title']);
                $description = sanitize($_POST['description']);
                $contact = sanitize($_POST['contact']);
                $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Anonymous';
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                
                $image_name = '';
                $image_content = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        $image_name = sanitize(basename($_FILES['image']['name']));
                        $image_content = file_get_contents($_FILES['image']['tmp_name']);
                    }
                }
                
                $stmt = $db->prepare("INSERT INTO market (title, description, image_name, image_content, contact, user_id, username) VALUES (:title, :description, :image_name, :image_content, :contact, :user_id, :username)");
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':description', $description, SQLITE3_TEXT);
                $stmt->bindValue(':image_name', $image_name, SQLITE3_TEXT);
                $stmt->bindValue(':image_content', $image_content, SQLITE3_BLOB);
                $stmt->bindValue(':contact', $contact, SQLITE3_TEXT);
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->execute();
                break;

            case 'create_course':
                $title = sanitize($_POST['title']);
                $content = sanitize($_POST['content']);
                $category = sanitize($_POST['category']);
                $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Anonymous';
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                
                $stmt = $db->prepare("INSERT INTO courses (title, content, category, user_id, username) VALUES (:title, :content, :category, :user_id, :username)");
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':content', $content, SQLITE3_TEXT);
                $stmt->bindValue(':category', $category, SQLITE3_TEXT);
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->execute();
                break;
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
}

$current_tab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'paste';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BreadSquad</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --bg-dark: #0a0a0a;
            --bg-darker: #050505;
            --primary: #ff2d55;
            --primary-hover: #ff4d6d;
            --secondary: #4361ee;
            --secondary-hover: #4895ef;
            --accent: #7209b7;
            --text: #f8f9fa;
            --text-muted: #adb5bd;
            --card-bg: #161616;
            --card-border: #2b2b2b;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #3a86ff;
            --glow: 0 0 15px rgba(255, 45, 85, 0.7);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background-color: var(--bg-dark);
            color: var(--text);
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        header {
            background: linear-gradient(135deg, var(--bg-darker), #111);
            border-bottom: 1px solid rgba(255, 45, 85, 0.2);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: var(--glow);
            letter-spacing: 1px;
            transition: var(--transition);
        }
        .logo:hover {
            text-shadow: 0 0 20px rgba(255, 45, 85, 0.9);
        }
        nav {
            display: flex;
            gap: 15px;
        }
        nav a {
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 30px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 1px;
        }
        nav a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: var(--transition);
        }
        nav a:hover::before {
            width: 100%;
        }
        nav a.active {
            background: rgba(67, 97, 238, 0.2);
            color: var(--secondary);
        }
        nav a.active::before {
            width: 100%;
        }
        .auth-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            z-index: -1;
            transition: var(--transition);
            opacity: 0.9;
        }
        .btn:hover::before {
            opacity: 1;
            transform: scale(1.05);
        }
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.4);
        }
        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.6);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        .content {
            margin-top: 40px;
            padding-bottom: 50px;
        }
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tab-content.active {
            display: block;
        }
        .card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--card-border);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--card-border);
        }
        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            font-family: 'Orbitron', sans-serif;
        }
        .card-author {
            color: var(--secondary);
            font-weight: 500;
            background: rgba(67, 97, 238, 0.1);
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 14px;
        }
        .card-body {
            margin-bottom: 20px;
        }
        .card-body p {
            color: var(--text-muted);
            margin-bottom: 15px;
        }
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-muted);
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text);
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 1px;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            color: var(--text);
            font-size: 16px;
            transition: var(--transition);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(255, 45, 85, 0.2);
        }
        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }
        .modal-content {
            background: var(--bg-darker);
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            padding: 30px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--card-border);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--card-border);
        }
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            font-family: 'Orbitron', sans-serif;
        }
        .close {
            font-size: 28px;
            cursor: pointer;
            color: var(--text-muted);
            transition: var(--transition);
        }
        .close:hover {
            color: var(--primary);
            transform: rotate(90deg);
        }
        .categories {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .category {
            padding: 8px 15px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--secondary);
            border-radius: 30px;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid var(--card-border);
        }
        .category:hover {
            background: rgba(67, 97, 238, 0.2);
            transform: translateY(-2px);
        }
        .category.active {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.4);
        }
        .file-download {
            display: inline-block;
            margin-top: 15px;
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 8px 15px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 30px;
            border: 1px solid var(--card-border);
        }
        .file-download:hover {
            background: rgba(67, 97, 238, 0.2);
            color: var(--secondary-hover);
            transform: translateY(-2px);
        }
        .image-preview {
            max-width: 100%;
            max-height: 300px;
            margin: 15px 0;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transition: var(--transition);
        }
        .image-preview:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 45, 85, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(255, 45, 85, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 45, 85, 0); }
        }
        .glow-text {
            text-shadow: 0 0 10px var(--primary), 0 0 20px var(--primary);
        }
        .neon-border {
            position: relative;
        }
        .neon-border::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border-radius: 15px;
            background: linear-gradient(45deg, var(--primary), var(--secondary), var(--primary));
            z-index: -1;
            opacity: 0;
            transition: var(--transition);
        }
        .neon-border:hover::before {
            opacity: 1;
            filter: blur(2px);
        }
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            nav {
                flex-wrap: wrap;
                justify-content: center;
            }
            .auth-buttons {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo animate__animated animate__fadeInLeft">BreadSquad</div>
            <nav>
                <a href="?tab=paste" class="<?= $current_tab === 'paste' ? 'active' : '' ?> animate__animated animate__fadeInDown">Paste</a>
                <a href="?tab=market" class="<?= $current_tab === 'market' ? 'active' : '' ?> animate__animated animate__fadeInDown">Market</a>
                <a href="?tab=course" class="<?= $current_tab === 'course' ? 'active' : '' ?> animate__animated animate__fadeInDown">Courses</a>
            </nav>
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span style="color: var(--text); margin-right: 15px; font-family: 'Orbitron', sans-serif;" class="animate__animated animate__fadeInRight"><?= $_SESSION['username'] ?></span>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn btn-danger animate__animated animate__fadeInRight">Logout</button>
                    </form>
                <?php else: ?>
                    <button onclick="document.getElementById('login-modal').style.display='flex'" class="btn btn-primary animate__animated animate__fadeInRight">Login</button>
                    <button onclick="document.getElementById('register-modal').style.display='flex'" class="btn btn-secondary animate__animated animate__fadeInRight">Register</button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container content">
        <div class="tab-content <?= $current_tab === 'paste' ? 'active' : '' ?>" id="paste">
            <button onclick="document.getElementById('create-paste-modal').style.display='flex'" class="btn btn-primary pulse">Create Paste</button>
            
            <div style="margin-top: 30px;">
                <?php
                $result = $db->query("SELECT * FROM pastes ORDER BY created_at DESC");
                while ($row = $result->fetchArray(SQLITE3_ASSOC)): ?>
                    <div class="card neon-border">
                        <div class="card-header">
                            <div class="card-title glow-text"><?= $row['title'] ?></div>
                            <div class="card-author"><?= $row['username'] ?></div>
                        </div>
                        <div class="card-body">
                            <p><?= nl2br(substr($row['content'], 0, 200)) ?>...</p>
                            <?php if (!empty($row['file_name'])): ?>
                                <a href="?download_paste_file=<?= $row['id'] ?>" class="file-download">Download File: <?= $row['file_name'] ?></a>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="?view_paste=<?= $row['id'] ?>" class="btn btn-secondary">View Full Paste</a>
                            <span><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="tab-content <?= $current_tab === 'market' ? 'active' : '' ?>" id="market">
            <button onclick="document.getElementById('create-market-modal').style.display='flex'" class="btn btn-primary pulse">Create Market Item</button>
            
            <div style="margin-top: 30px;">
                <?php
                $result = $db->query("SELECT * FROM market ORDER BY created_at DESC");
                while ($row = $result->fetchArray(SQLITE3_ASSOC)): ?>
                    <div class="card neon-border">
                        <div class="card-header">
                            <div class="card-title glow-text"><?= $row['title'] ?></div>
                            <div class="card-author"><?= $row['username'] ?></div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($row['image_name'])): ?>
                                <img src="?view_market_image=<?= $row['id'] ?>" alt="<?= $row['title'] ?>" class="image-preview">
                            <?php endif; ?>
                            <p><?= nl2br($row['description']) ?></p>
                            <p><strong>Contact:</strong> <?= $row['contact'] ?></p>
                        </div>
                        <div class="card-footer">
                            <span><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="tab-content <?= $current_tab === 'course' ? 'active' : '' ?>" id="course">
            <button onclick="document.getElementById('create-course-modal').style.display='flex'" class="btn btn-primary pulse">Create Course</button>
            
            <div class="categories">
                <div class="category active" onclick="filterCourses('all')">All</div>
                <div class="category" onclick="filterCourses('tech')">Tech</div>
                <div class="category" onclick="filterCourses('cyber')">Cyber Security</div>
                <div class="category" onclick="filterCourses('pentest')">Pentesting</div>
                <div class="category" onclick="filterCourses('osint')">OSINT</div>
            </div>
            
            <div id="courses-container" style="margin-top: 30px;">
                <?php
                $result = $db->query("SELECT * FROM courses ORDER BY created_at DESC");
                while ($row = $result->fetchArray(SQLITE3_ASSOC)): ?>
                    <div class="card neon-border course-card" data-category="<?= strtolower($row['category']) ?>">
                        <div class="card-header">
                            <div class="card-title glow-text"><?= $row['title'] ?></div>
                            <div class="card-author"><?= $row['username'] ?></div>
                        </div>
                        <div class="card-body">
                            <p><?= nl2br(substr($row['content'], 0, 200)) ?>...</p>
                        </div>
                        <div class="card-footer">
                            <a href="?view_course=<?= $row['id'] ?>" class="btn btn-secondary">View Full Course</a>
                            <span><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['view_paste'])): ?>
        <div class="modal" id="view-paste-modal" style="display: flex;">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title">
                        <?php
                        $paste = $db->querySingle("SELECT * FROM pastes WHERE id = ".$db->escapeString($_GET['view_paste']), true);
                        echo $paste['title'];
                        ?>
                    </div>
                    <div class="close" onclick="window.location.href='?'">&times;</div>
                </div>
                <div class="modal-body">
                    <p><?= nl2br($paste['content']) ?></p>
                    <?php if (!empty($paste['file_name'])): ?>
                        <a href="?download_paste_file=<?= $paste['id'] ?>" class="file-download">Download File: <?= $paste['file_name'] ?></a>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <span>Posted by <?= $paste['username'] ?> on <?= date('M d, Y H:i', strtotime($paste['created_at'])) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['view_course'])): ?>
        <div class="modal" id="view-course-modal" style="display: flex;">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title">
                        <?php
                        $course = $db->querySingle("SELECT * FROM courses WHERE id = ".$db->escapeString($_GET['view_course']), true);
                        echo $course['title'];
                        ?>
                    </div>
                    <div class="close" onclick="window.location.href='?'">&times;</div>
                </div>
                <div class="modal-body">
                    <p><?= nl2br($course['content']) ?></p>
                </div>
                <div class="modal-footer">
                    <span>Posted by <?= $course['username'] ?> on <?= date('M d, Y H:i', strtotime($course['created_at'])) ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal" id="login-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Login</div>
                <div class="close" onclick="document.getElementById('login-modal').style.display='none'">&times;</div>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
        </div>
    </div>

    <div class="modal" id="register-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Register</div>
                <div class="close" onclick="document.getElementById('register-modal').style.display='none'">&times;</div>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Register</button>
            </form>
        </div>
    </div>

    <div class="modal" id="create-paste-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Create New Paste</div>
                <div class="close" onclick="document.getElementById('create-paste-modal').style.display='none'">&times;</div>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_paste">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-control" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">File (Optional, max 40MB)</label>
                    <input type="file" name="file" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">Create Paste</button>
            </form>
        </div>
    </div>

    <div class="modal" id="create-market-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Create Market Item</div>
                <div class="close" onclick="document.getElementById('create-market-modal').style.display='none'">&times;</div>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_market_item">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact (Discord or Email)</label>
                    <input type="text" name="contact" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Image (Optional, JPG/PNG/GIF/WEBP/SVG)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <button type="submit" class="btn btn-primary">Create Item</button>
            </form>
        </div>
    </div>

    <div class="modal" id="create-course-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Create Course</div>
                <div class="close" onclick="document.getElementById('create-course-modal').style.display='none'">&times;</div>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="create_course">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control" required>
                        <option value="Tech">Tech</option>
                        <option value="Cyber Security">Cyber Security</option>
                        <option value="Pentesting">Pentesting</option>
                        <option value="OSINT">OSINT</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-control" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create Course</button>
            </form>
        </div>
    </div>

    <?php
    if (isset($_GET['download_paste_file'])) {
        $paste = $db->querySingle("SELECT file_name, file_content FROM pastes WHERE id = ".$db->escapeString($_GET['download_paste_file']), true);
        if ($paste && !empty($paste['file_name'])) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.$paste['file_name'].'"');
            echo $paste['file_content'];
            exit();
        }
    }

    if (isset($_GET['view_market_image'])) {
        $item = $db->querySingle("SELECT image_name, image_content FROM market WHERE id = ".$db->escapeString($_GET['view_market_image']), true);
        if ($item && !empty($item['image_name'])) {
            $ext = pathinfo($item['image_name'], PATHINFO_EXTENSION);
            $mime_types = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml'
            ];
            if (array_key_exists(strtolower($ext), $mime_types)) {
                header('Content-Type: '.$mime_types[strtolower($ext)]);
                echo $item['image_content'];
                exit();
            }
        }
    }
    ?>

    <script>
        function filterCourses(category) {
            const categories = document.querySelectorAll('.category');
            categories.forEach(cat => {
                cat.classList.remove('active');
                if (cat.textContent.toLowerCase() === category || (category === 'all' && cat.textContent === 'All')) {
                    cat.classList.add('active');
                }
            });

            const courses = document.querySelectorAll('.course-card');
            courses.forEach(course => {
                if (category === 'all' || course.dataset.category === category) {
                    course.style.display = 'block';
                    setTimeout(() => {
                        course.style.opacity = '1';
                        course.style.transform = 'translateY(0)';
                    }, 50);
                } else {
                    course.style.opacity = '0';
                    course.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        course.style.display = 'none';
                    }, 300);
                }
            });
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });

        <?php if (isset($_GET['view_paste'])): ?>
            window.onload = function() {
                document.getElementById('view-paste-modal').style.display = 'flex';
            };
        <?php endif; ?>

        <?php if (isset($_GET['view_course'])): ?>
            window.onload = function() {
                document.getElementById('view-course-modal').style.display = 'flex';
            };
        <?php endif; ?>

        document.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', function() {
                document.querySelectorAll('nav a').forEach(navLink => {
                    navLink.classList.remove('active');
                });
                this.classList.add('active');
            });
        });

        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('mousedown', function() {
                this.style.transform = 'translateY(1px)';
            });
            button.addEventListener('mouseup', function() {
                this.style.transform = 'translateY(-2px)';
            });
            button.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
        });
    </script>
</body>
</html>
