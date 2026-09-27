<?php
// admin/process_product.php
session_start();

// Database සම්බන්ධතාවය ලබාගැනීම
require_once '../db.php';

function resolveImageUrlFromRemotePage(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $imageExtensions = '/\.(jpe?g|png|gif|webp|svg|bmp)(\?|$)/i';
    if (preg_match($imageExtensions, $url)) {
        return $url;
    }

    $headers = @get_headers($url, 1);
    if ($headers && isset($headers['Content-Type'])) {
        $contentType = is_array($headers['Content-Type']) ? end($headers['Content-Type']) : $headers['Content-Type'];
        if (stripos($contentType, 'image/') === 0) {
            return $url;
        }
    }

    $html = '';
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; KSupermarket/1.0)');
        $html = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $html = @file_get_contents($url);
    }

    if (!$html) {
        return $url;
    }

    $imageCandidates = [];
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
        $imageCandidates[] = trim($matches[1]);
    }
    if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
        $imageCandidates[] = trim($matches[1]);
    }
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
        foreach ($matches[1] as $src) {
            $src = trim($src);
            if ($src !== '') {
                $imageCandidates[] = $src;
            }
        }
    }

    foreach ($imageCandidates as $candidate) {
        if (strpos($candidate, 'data:') === 0) {
            continue;
        }
        if (parse_url($candidate, PHP_URL_SCHEME) === null) {
            $base = $url;
            if (strpos($candidate, '/') === 0) {
                $parsed = parse_url($url);
                if (isset($parsed['scheme'], $parsed['host'])) {
                    $candidate = $parsed['scheme'] . '://' . $parsed['host'] . $candidate;
                }
            } else {
                $candidate = rtrim(dirname($base), '/') . '/' . ltrim($candidate, '/');
            }
        }
        if (preg_match($imageExtensions, $candidate)) {
            return $candidate;
        }
    }

    return $url;
}

function saveDataUriImage(string $dataUri): string {
    if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,(.+)$/s', $dataUri, $matches)) {
        throw new RuntimeException('Please provide a valid image URL or a supported image data.');
    }

    $extension = strtolower($matches[1]);
    if ($extension === 'jpeg') {
        $extension = 'jpg';
    }

    $imageData = base64_decode($matches[2], true);
    if ($imageData === false || $imageData === '') {
        throw new RuntimeException('The image data is invalid or incomplete.');
    }
    if (strlen($imageData) > 5 * 1024 * 1024) {
        throw new RuntimeException('The image must be smaller than 5 MB.');
    }

    $imageDirectory = __DIR__ . '/../assets/images';
    if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true)) {
        throw new RuntimeException('The image upload directory could not be created.');
    }

    $filename = 'product_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filePath = $imageDirectory . '/' . $filename;
    if (file_put_contents($filePath, $imageData) === false) {
        throw new RuntimeException('The image could not be saved.');
    }

    return $filename;
}

// Admin කෙනෙක්ද කියා පරීක්ෂා කිරීම
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
                $stmt->execute([':id' => $product_id]);
            } catch (PDOException $e) {
                die("Database Error: " . $e->getMessage());
            }
        }
        header("Location: dashboard.php");
        exit();
    }

    // Form එකෙන් එන දත්ත අරගන්නවා
    $product_id    = intval($_POST['product_id'] ?? 0);
    $product_name  = trim($_POST['product_name'] ?? '');
    $category_id   = intval($_POST['category_id'] ?? 0);
    $image_url     = trim($_POST['image'] ?? '');
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $stock_qty     = intval($_POST['stock_qty'] ?? 0);

    try {
        if ($image_url !== '') {
            if (strpos($image_url, 'data:image/') === 0) {
                $image_url = saveDataUriImage($image_url);
            } else {
                $image_url = resolveImageUrlFromRemotePage($image_url);
            }
        }

        if (strlen($image_url) > 255) {
            throw new RuntimeException('Image URL is too long. Please use a direct image URL or paste a smaller image.');
        }
    } catch (RuntimeException $e) {
        die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }

    if (!empty($product_name) && $category_id > 0 && $selling_price >= 0 && $stock_qty >= 0) {
        try {
            if ($product_id > 0) {
                $sql = "UPDATE products SET name = :name, price = :price, stock = :stock, category_id = :category_id, image = :image WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'      => $product_name,
                    ':price'     => $selling_price,
                    ':stock'     => $stock_qty,
                    ':category_id'=> $category_id,
                    ':image'     => $image_url,
                    ':id'        => $product_id,
                ]);
            } else {
                $sql = "INSERT INTO products (name, price, stock, category_id, image) 
                        VALUES (:name, :price, :stock, :category_id, :image)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name'      => $product_name,
                    ':price'     => $selling_price,
                    ':stock'     => $stock_qty,
                    ':category_id'=> $category_id,
                    ':image'     => $image_url,
                ]);
            }

            // සාර්ථක නම් Dashboard එකට රීඩිරෙක්ට් කරනවා
            header("Location: dashboard.php");
            exit();

        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    } else {
        echo "Please fill in all fields with valid data.";
    }
} else {
    header("Location: dashboard.php");
    exit();
}