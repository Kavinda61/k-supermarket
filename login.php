<?php
// login.php
require_once 'db.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                if ($user['role'] == 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] == 'staff') {
                    header("Location: staff/dashboard.php");
                } else {
                    // customer
                    header("Location: customer/dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid username or password!";
            }
        } catch (PDOException $e) {
            $error = "Login Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in both fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - K Supermarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --login-accent: #10b981; --login-glow: rgba(16, 185, 129, 0.35); }
        body {
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            background: #07111f;
            color: #f8fafc;
            font-family: 'Segoe UI', sans-serif;
        }
        .ks-login-wrapper {
            position: relative;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 32px 16px;
            overflow: hidden;
            isolation: isolate;
            background:
                radial-gradient(circle at 15% 20%, rgba(16,185,129,.12), transparent 32%),
                radial-gradient(circle at 85% 80%, rgba(59,130,246,.14), transparent 34%),
                transparent;
        }
        .ks-login-wrapper::before {
            content: "";
            position: absolute;
            inset: 0;
            opacity: .18;
            background-image: linear-gradient(rgba(255,255,255,.08) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, transparent, black 30%, black 70%, transparent);
            z-index: -1;
        }
        .ks-bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .6;
            animation: floatOrb 8s infinite alternate ease-in-out;
            pointer-events: none;
            z-index: -1;
        }
        .ks-orb-1 { width: 300px; height: 300px; background: #10b981; top: 10%; left: 15%; }
        .ks-orb-2 { width: 350px; height: 350px; background: #3b82f6; bottom: 10%; right: 15%; animation-delay: -4s; }
        .ks-orb-3 { width: 180px; height: 180px; background: #ef4444; bottom: 18%; left: 8%; opacity: .28; animation-delay: -2s; }
        .ks-glass-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            padding: 40px;
            background: rgba(255,255,255,.09);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,.5), 0 0 35px rgba(16,185,129,.08);
        }
        .ks-card-header { text-align: center; margin-bottom: 28px; }
        .ks-brand-badge {
            width: 72px;
            height: 72px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            font-size: 34px;
            background: linear-gradient(135deg, rgba(255,255,255,.18), rgba(255,255,255,.04));
            border: 2px solid var(--login-accent);
            box-shadow: 0 0 25px var(--login-glow);
            animation: badgePulse 2.4s ease-in-out infinite;
        }
        .ks-card-header h2 { margin-bottom: 8px; color: #fff; letter-spacing: 2px; font-weight: 800; }
        .ks-card-header p { margin: 0; color: #cbd5e1; }
        .ks-role-strip { display: flex; gap: 8px; margin: -8px 0 24px; }
        .ks-role-pill {
            flex: 1;
            padding: 7px 4px;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 999px;
            color: #cbd5e1;
            font-size: 11px;
            text-align: center;
            letter-spacing: .4px;
            background: rgba(255,255,255,.04);
            cursor: pointer;
            transition: all .3s ease;
        }
        .ks-role-pill.admin { border-color: rgba(239,68,68,.45); }
        .ks-role-pill.staff { border-color: rgba(59,130,246,.45); }
        .ks-role-pill.customer { border-color: rgba(16,185,129,.55); color: #a7f3d0; }
        .ks-role-pill.active {
            color: #fff;
            background: rgba(255,255,255,.15);
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
        }
        .login-card { background: transparent; border: 0; box-shadow: none; }
        .form-label { color: #e2e8f0; font-weight: 600; }
        .ks-input-wrap { position: relative; }
        .ks-input-wrap i {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }
        .ks-glass-card input {
            min-height: 48px;
            padding-left: 42px;
            background: rgba(255,255,255,.08) !important;
            border: 1px solid rgba(255,255,255,.2) !important;
            color: #fff !important;
            border-radius: 12px;
        }
        .ks-glass-card input::placeholder { color: #94a3b8; }
        .ks-glass-card input:focus {
            border-color: var(--login-accent) !important;
            box-shadow: 0 0 12px var(--login-glow) !important;
            background: rgba(255,255,255,.12) !important;
        }
        .btn-custom {
            min-height: 48px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: 0;
            border-radius: 12px;
            box-shadow: 0 10px 22px rgba(16,185,129,.25);
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .btn-custom:hover { color: white; transform: translateY(-2px); box-shadow: 0 14px 26px rgba(16,185,129,.35); }
        .ks-glass-card a { color: #6ee7b7 !important; }
        .ks-glass-card .text-muted { color: #cbd5e1 !important; }
        .ks-glass-card .alert { background: rgba(220,38,38,.18); border-color: rgba(248,113,113,.45); color: #fecaca; }
        @keyframes floatOrb { 0% { transform: translateY(0) scale(1); } 100% { transform: translateY(-30px) scale(1.1); } }
        @keyframes badgePulse { 0%,100% { transform: scale(1); box-shadow: 0 0 20px var(--login-glow); } 50% { transform: scale(1.04); box-shadow: 0 0 34px var(--login-glow); } }
        @media (max-width: 480px) { .ks-glass-card { padding: 30px 22px; } }

        /* Fixed Background Video Positioning */
        .video-bg-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            z-index: -2;
        }
        #bg-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        #bg-canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 2;
        pointer-events: none;
        }
        .video-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.65);
            z-index: -1;
        }
        .login-card, .login-container {
            position: relative;
            z-index: 10;
        }
    </style>
</head>
<body>
<!-- Background Video Wrapper -->
<div class="video-bg-container">
    <video autoplay loop muted playsinline preload="metadata" id="bg-video"
        poster="https://images.unsplash.com/photo-1542838132-92c53300491e?q=80&w=1920&auto=format&fit=crop">
        <source src="12655224_1918_1080_30fps.mp4" type="video/mp4">
    </video>
    <canvas id="bg-canvas" aria-hidden="true"></canvas>
    <div class="video-overlay"></div>
</div>
<?php $page_loader_role = 'customer'; include __DIR__ . '/includes/page-loader.php'; ?>
<div class="ks-login-wrapper">
    <div class="ks-bg-orb ks-orb-1"></div>
    <div class="ks-bg-orb ks-orb-2"></div>
    <div class="ks-bg-orb ks-orb-3"></div>
    <div class="ks-glass-card">
        <div class="ks-card-header">
            <div class="ks-brand-badge">🛒</div>
            <h2>K SUPER MARKET</h2>
            <p>Sign in to access your portal</p>
        </div>
        <div class="ks-role-strip" aria-label="Portal roles">
            <button type="button" class="ks-role-pill admin" data-role="admin">Admin</button>
            <button type="button" class="ks-role-pill staff" data-role="staff">Staff</button>
            <button type="button" class="ks-role-pill customer active" data-role="customer">Customer</button>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="ks-input-wrap">
                    <i class="bi bi-person"></i>
                    <input type="text" name="username" class="form-control" required autocomplete="off" placeholder="Enter your username">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="ks-input-wrap">
                    <i class="bi bi-lock"></i>
                    <input type="password" name="password" class="form-control" required placeholder="Enter your password">
                </div>
            </div>
            <button type="submit" class="btn btn-custom w-100 py-2 fw-bold">Login</button>
            <div class="text-center mt-3">
                <a href="forgot-password.php" class="text-success text-decoration-none fw-bold d-block mb-2">Forgot password?</a>
                <p class="mb-0 text-muted">Don't have an account? <a href="register.php" class="text-success text-decoration-none fw-bold">Register now</a></p>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var canvas = document.getElementById('bg-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var particles = [];

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    function createParticles() {
        particles = Array.from({ length: 40 }, function () {
            return {
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                size: Math.random() * 3 + 1,
                speedX: (Math.random() - 0.5) * 0.5,
                speedY: (Math.random() - 0.5) * 0.5,
                opacity: Math.random() * 0.5 + 0.2
            };
        });
    }

    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(function (particle) {
            particle.x += particle.speedX;
            particle.y += particle.speedY;
            if (particle.x < 0 || particle.x > canvas.width || particle.y < 0 || particle.y > canvas.height) {
                particle.x = Math.random() * canvas.width;
                particle.y = Math.random() * canvas.height;
            }
            ctx.fillStyle = 'rgba(52, 211, 153, ' + particle.opacity + ')';
            ctx.beginPath();
            ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
            ctx.fill();
        });
        window.requestAnimationFrame(animate);
    }

    resizeCanvas();
    createParticles();
    window.addEventListener('resize', function () {
        resizeCanvas();
        createParticles();
    });
    animate();

    document.querySelectorAll('.ks-role-pill').forEach(function (roleButton) {
        roleButton.addEventListener('click', function () {
            document.querySelectorAll('.ks-role-pill').forEach(function (button) {
                button.classList.remove('active');
            });
            roleButton.classList.add('active');
            var role = roleButton.getAttribute('data-role');
            var themes = {
                admin: ['#ef4444', 'rgba(239, 68, 68, 0.35)'],
                staff: ['#3b82f6', 'rgba(59, 130, 246, 0.35)'],
                customer: ['#10b981', 'rgba(16, 185, 129, 0.35)']
            };
            document.documentElement.style.setProperty('--login-accent', themes[role][0]);
            document.documentElement.style.setProperty('--login-glow', themes[role][1]);
        });
    });
})();
</script>
</body>
</html>