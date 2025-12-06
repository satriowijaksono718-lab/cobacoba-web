<?php
include 'koneksi.php';

$sukses = "";
$error  = "";
$daftar_pasien = [];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'terbaru';
$filter_jk = isset($_GET['filter_jk']) ? $_GET['filter_jk'] : '';

// Proses Edit Data
if (isset($_POST['edit'])) {
    $id      = intval($_POST['id'] ?? 0);
    $nik     = trim($_POST['nik'] ?? '');
    $nama    = trim($_POST['nama'] ?? '');
    $jk      = trim($_POST['jenis_kelamin'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $keluhan = trim($_POST['keluhan'] ?? '');

    if (empty($nik) || empty($nama) || empty($jk) || empty($alamat) || empty($keluhan)) {
        $error = "⚠️ Silakan masukkan semua data.";
    } elseif (strlen($nik) !== 16 || !ctype_digit($nik)) {
        $error = "⚠️ NIK harus 16 digit angka.";
    } else {
        $sql = "UPDATE pasien SET nik=?, nama=?, jenis_kelamin=?, alamat=?, keluhan=? WHERE id=?";
        if ($stmt = $koneksi->prepare($sql)) {
            $stmt->bind_param("sssssi", $nik, $nama, $jk, $alamat, $keluhan, $id);
            if ($stmt->execute()) {
                $sukses = "✅ Data pasien berhasil diperbarui.";
            } else {
                $error = "❌ Gagal memperbarui data.";
            }
            $stmt->close();
        }
    }
}

// Proses Hapus Data
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    $sql = "DELETE FROM pasien WHERE id = ?";
    if ($stmt = $koneksi->prepare($sql)) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $sukses = "✅ Data pasien berhasil dihapus.";
        } else {
            $error = "❌ Gagal menghapus data.";
        }
        $stmt->close();
    }
}

// Proses Simpan Data Pasien
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $nik     = trim($_POST['nik'] ?? '');
    $nama    = trim($_POST['nama'] ?? '');
    $jk      = trim($_POST['jenis_kelamin'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $keluhan = trim($_POST['keluhan'] ?? '');

    // Validasi Input
    if (empty($nik) || empty($nama) || empty($jk) || empty($alamat) || empty($keluhan)) {
        $error = "⚠️ Silakan masukkan semua data.";
    } elseif (strlen($nik) !== 16 || !ctype_digit($nik)) {
        $error = "⚠️ NIK harus 16 digit angka.";
    } elseif (strlen($nama) < 3) {
        $error = "⚠️ Nama minimal 3 karakter.";
    } elseif (strlen($alamat) < 5) {
        $error = "⚠️ Alamat minimal 5 karakter.";
    } else {
        // Cek duplikat NIK
        $sql_check = "SELECT id FROM pasien WHERE nik = ?";
        if ($stmt_check = $koneksi->prepare($sql_check)) {
            $stmt_check->bind_param("s", $nik);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $error = "⚠️ NIK sudah terdaftar dalam sistem.";
            } else {
                $sql = "INSERT INTO pasien (nik, nama, jenis_kelamin, alamat, keluhan) VALUES (?, ?, ?, ?, ?)";
                if ($stmt = $koneksi->prepare($sql)) {
                    $stmt->bind_param("sssss", $nik, $nama, $jk, $alamat, $keluhan);
                    
                    if ($stmt->execute()) {
                        $sukses = "✅ Data pasien berhasil disimpan.";
                        // Reset form
                        $_POST = array();
                    } else {
                        $error = "❌ Gagal menyimpan data.";
                    }
                    $stmt->close();
                } else {
                    $error = "❌ Error SQL: " . $koneksi->error;
                }
            }
            $stmt_check->close();
        }
    }
}

// Ambil Data Pasien dengan sorting dan filter
$where_clause = "";
$bind_params = [];
$bind_types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $where_clause = "WHERE (nik LIKE ? OR nama LIKE ? OR alamat LIKE ?)";
    $bind_params = [$search_param, $search_param, $search_param];
    $bind_types = "sss";
}

if (!empty($filter_jk)) {
    if (empty($where_clause)) {
        $where_clause = "WHERE jenis_kelamin = ?";
        $bind_params = [$filter_jk];
        $bind_types = "s";
    } else {
        $where_clause .= " AND jenis_kelamin = ?";
        $bind_params[] = $filter_jk;
        $bind_types .= "s";
    }
}

$order_clause = ($sort_by === 'nama') ? "ORDER BY nama ASC" : "ORDER BY id DESC";
$sql = "SELECT * FROM pasien $where_clause $order_clause";

if (!empty($bind_params)) {
    if ($stmt = $koneksi->prepare($sql)) {
        $stmt->bind_param($bind_types, ...$bind_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $daftar_pasien = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    $result = $koneksi->query($sql);
    if ($result) {
        $daftar_pasien = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Hitung total pasien dan statistik
$sql_count = "SELECT COUNT(*) as total FROM pasien";
$result_count = $koneksi->query($sql_count);
$total_pasien = $result_count ? $result_count->fetch_assoc()['total'] : 0;

// Hitung statistik jenis kelamin
$sql_jk = "SELECT jenis_kelamin, COUNT(*) as count FROM pasien GROUP BY jenis_kelamin";
$result_jk = $koneksi->query($sql_jk);
$stats_jk = ['Laki-laki' => 0, 'Perempuan' => 0];
while ($row = $result_jk->fetch_assoc()) {
    $stats_jk[$row['jenis_kelamin']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teknologi Informasi Pasien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --accent: #f5576c;
            --success: #48dbfb;
            --light-bg: #f8f9fa;
        }
        
        html, body {
            height: 100%;
        }
        
        body {
            background: linear-gradient(-45deg, #667eea 0%, #764ba2 25%, #f5576c 50%, #ff9a56 75%, #667eea 100%);
            background-size: 400% 400%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 40px 0 60px;
            position: relative;
            overflow-x: hidden;
            transition: all 0.3s ease;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        body.dark-mode {
            background: linear-gradient(-45deg, #1a1a2e 0%, #16213e 25%, #0f3460 50%, #1a1a2e 75%, #0d1b2a 100%);
            background-size: 400% 400%;
            animation: gradientShiftDark 15s ease infinite;
        }
        
        @keyframes gradientShiftDark {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><filter id="blur"><feGaussianBlur in="SourceGraphic" stdDeviation="40" /></filter><pattern id="dots" width="50" height="50" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="2" fill="rgba(255,255,255,0.1)" /></pattern></defs><rect width="1000" height="1000" fill="url(%23dots)" /><circle cx="200" cy="200" r="300" fill="rgba(255,255,255,0.05)" filter="url(%23blur)" /><circle cx="800" cy="800" r="250" fill="rgba(255,255,255,0.03)" filter="url(%23blur)" /></svg>');
            pointer-events: none;
            z-index: 0;
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .container {
            max-width: 1200px;
            position: relative;
            z-index: 1;
        }
        
        /* Dark Mode Toggle */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            padding: 12px 18px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2), 0 0 20px rgba(255, 255, 255, 0.1);
        }
        
        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3), 0 0 30px rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .theme-toggle i {
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .theme-toggle:active i {
            transform: rotate(360deg) scale(1.2);
        }
            color: white;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            position: relative;
            overflow: hidden;
        }
        
        .theme-toggle::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .theme-toggle:hover::before {
            opacity: 1;
        }
        
        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 12px 40px rgba(31, 38, 135, 0.5);
        }
        
        .theme-toggle i {
            transition: transform 0.4s ease;
        }
        
        .theme-toggle:active i {
            transform: rotate(180deg) scale(1.2);
        }
        
        /* Header Section */
        .header-section {
            text-align: center;
            color: white;
            margin-bottom: 50px;
            animation: fadeInDown 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            padding: 20px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .header-section h1 {
            font-size: 48px;
            font-weight: 900;
            margin-bottom: 12px;
            text-shadow: 0 8px 16px rgba(0, 0, 0, 0.3), 0 0 30px rgba(255, 255, 255, 0.2);
            letter-spacing: -1px;
            background: linear-gradient(135deg, #fff 0%, #f0f0f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleGlow 3s ease-in-out infinite;
        }
        
        @keyframes titleGlow {
            0%, 100% { text-shadow: 0 8px 16px rgba(0, 0, 0, 0.3), 0 0 30px rgba(255, 255, 255, 0.2); }
            50% { text-shadow: 0 8px 16px rgba(0, 0, 0, 0.3), 0 0 60px rgba(255, 255, 255, 0.4); }
        }
        
        body.dark-mode .header-section h1 {
            background: linear-gradient(135deg, #48dbfb 0%, #0abde3 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header-section p {
            font-size: 18px;
            opacity: 0.95;
            font-weight: 500;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25), 0 0 40px rgba(102, 126, 234, 0.1);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            animation: slideUp 0.8s ease;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        body.dark-mode .card {
            background: rgba(30, 30, 46, 0.8);
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(72, 219, 251, 0.1);
            border: 1px solid rgba(72, 219, 251, 0.1);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            z-index: 1;
        }
        
        .card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
            z-index: 0;
        }
        
        .card:hover::after {
            left: 100%;
        }
        
        .card:hover {
            transform: translateY(-12px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3), 0 0 60px rgba(102, 126, 234, 0.2);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f5576c 100%);
            border: none;
            color: white;
            padding: 28px;
            border-radius: 20px 20px 0 0;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.2) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 8s infinite;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .card-header h5 i {
            animation: iconPulse 2s ease-in-out infinite;
        }
        
        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .card-header.header-secondary {
            background: linear-gradient(135deg, #f5576c 0%, #e84a5f 100%);
        }
        
        .card-body {
            padding: 36px;
        }
        
        body.dark-mode .card-body {
            color: #e0e0e0;
        }
        
        /* Alerts */
        .alert {
            border: none;
            border-radius: 14px;
            padding: 18px 24px;
            font-weight: 500;
            animation: slideInDown 0.5s ease;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-left: 5px solid;
            position: relative;
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%);
            border-radius: 14px;
            pointer-events: none;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            border-left-color: #ff5252;
            box-shadow: 0 8px 24px rgba(255, 107, 107, 0.4), 0 0 20px rgba(255, 107, 107, 0.2);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
            border-left-color: #2f9e44;
            box-shadow: 0 8px 24px rgba(81, 207, 102, 0.4), 0 0 20px rgba(81, 207, 102, 0.2);
        }
        
        /* Form Elements */
        .form-control, .form-select, .search-box input {
            border-radius: 12px;
            border: 2px solid #e8e8e8;
            padding: 14px 18px;
            font-size: 15px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            background-color: #fafafa;
            font-weight: 500;
            position: relative;
            color: #333;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        body.dark-mode .form-control,
        body.dark-mode .form-select,
        body.dark-mode .search-box input {
            background-color: #2a2a3e;
            border-color: #444;
            color: #ffffff;
        }
        
        .form-control:focus, .form-select:focus, .search-box input:focus {
            border-color: var(--primary);
            background-color: white;
            box-shadow: 0 0 0 6px rgba(102, 126, 234, 0.15), 0 12px 24px rgba(102, 126, 234, 0.2);
            transform: translateY(-4px);
        }
        
        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus,
        body.dark-mode .search-box input:focus {
            background-color: #1a1a2e;
            box-shadow: 0 0 0 6px rgba(72, 219, 251, 0.15), 0 12px 24px rgba(72, 219, 251, 0.2);
        }
        
        .form-label {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        body.dark-mode .form-label {
            color: #48dbfb;
        }
        
        .text-danger {
            color: var(--accent) !important;
        }
        
        /* Buttons */
        .btn {
            border-radius: 12px;
            padding: 14px 28px;
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 13px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            transform: translate(-50%, -50%);
            transition: width 0.8s cubic-bezier(0.34, 1.56, 0.64, 1), height 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }
        
        .btn:active::before {
            width: 300px;
            height: 300px;
            box-shadow: 0 0 0 rgba(255, 255, 255, 0);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.35), 0 0 20px rgba(102, 126, 234, 0.1);
        }
        
        .btn-primary:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(102, 126, 234, 0.5), 0 0 30px rgba(102, 126, 234, 0.2);
            color: white;
            filter: brightness(1.1);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 8px 24px rgba(108, 117, 125, 0.3), 0 0 20px rgba(108, 117, 125, 0.1);
        }
        
        .btn-secondary:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(108, 117, 125, 0.4), 0 0 30px rgba(108, 117, 125, 0.2);
            filter: brightness(1.1);
        }
        
        .btn-sm {
            padding: 8px 14px;
            font-size: 12px;
        }
        
        .btn-danger-soft {
            background: linear-gradient(135deg, #ffe5e5 0%, #ffd9d9 100%);
            color: var(--accent);
            border: 2px solid #ffcccc;
            font-weight: 700;
        }
        
        .btn-danger-soft:hover {
            background: linear-gradient(135deg, var(--accent) 0%, #e84a5f 100%);
            color: white;
            transform: translateY(-3px);
        }
        
        /* Table */
        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 8px 24px rgba(102, 126, 234, 0.1);
        }
        
        body.dark-mode .table {
            background: #1e1e2e;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3), 0 8px 24px rgba(72, 219, 251, 0.1);
        }
        
        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 900;
            border: none;
            padding: 24px 18px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 14px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2);
            line-height: 1.8;
        }
        
        body.dark-mode .table thead th {
            background: linear-gradient(135deg, #48dbfb 0%, #0abde3 100%);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
        }
        }
        
        .table tbody td {
            padding: 24px 18px;
            border-color: #f0f0f0;
            vertical-align: middle;
            font-weight: 700;
            color: #2c3e50;
            font-size: 14px;
            line-height: 1.8;
            letter-spacing: 0.3px;
            word-spacing: 2px;
        }
        
        body.dark-mode .table tbody td {
            border-color: #333;
            color: #000000;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.1);
            background: linear-gradient(90deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
        }
        
        .table tbody tr {
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            border-bottom: 2px solid #e8e8e8;
            animation: staggerRow 0.6s ease forwards;
            background: linear-gradient(90deg, #ffffff 0%, #f8fbff 50%, #ffffff 100%);
            height: auto;
            position: relative;
            cursor: pointer;
        }
        
        .table tbody tr:hover {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1) 0%, rgba(102, 126, 234, 0.05) 100%);
            transform: translateX(8px);
            box-shadow: -4px 0 12px rgba(102, 126, 234, 0.3);
        }
        
        .table tbody tr::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 50%, #f5576c 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .table tbody tr:hover::before {
            opacity: 1;
        }
        
        .table tbody tr:hover {
            background: linear-gradient(90deg, #f0f4ff 0%, #f8fbff 50%, #f0f4ff 100%);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateX(4px);
        }
        
        @keyframes staggerRow {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .table tbody tr:nth-child(1) { animation-delay: 0.05s; }
        .table tbody tr:nth-child(2) { animation-delay: 0.1s; }
        .table tbody tr:nth-child(3) { animation-delay: 0.15s; }
        .table tbody tr:nth-child(4) { animation-delay: 0.2s; }
        .table tbody tr:nth-child(5) { animation-delay: 0.25s; }
        .table tbody tr:nth-child(n+6) { animation-delay: 0.3s; }
        
        body.dark-mode .table tbody tr {
            background: #1e1e2e;
            border-bottom: 2px solid #333;
        }
        
        .table tbody tr:hover {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.08) 0%, transparent 100%);
            transform: scale(1.01);
        }
        
        body.dark-mode .table tbody tr {
            background: linear-gradient(90deg, #3a3a4e 0%, #424456 50%, #3a3a4e 100%);
            border-bottom: 2px solid #333;
        }
        
        body.dark-mode .table tbody tr:hover {
            background: linear-gradient(90deg, #4a4a5e 0%, #525466 50%, #4a4a5e 100%);
            box-shadow: 0 4px 12px rgba(72, 219, 251, 0.2);
        }
        
        /* Badges */
        .badge {
            padding: 12px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent) 0%, #e84a5f 100%);
            color: white;
            box-shadow: 0 6px 16px rgba(245, 87, 108, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }
        
        .badge-info {
            background: linear-gradient(135deg, var(--success) 0%, #0abde3 100%);
            box-shadow: 0 6px 16px rgba(72, 219, 251, 0.4);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 28px;
            border-radius: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.3), 0 0 40px rgba(102, 126, 234, 0.2);
            animation: scaleIn 0.8s ease, cardPulse 3s ease-in-out infinite, glowPulse 4s ease-in-out infinite;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
        }
        
        @keyframes cardPulse {
            0%, 100% { box-shadow: 0 12px 40px rgba(102, 126, 234, 0.3), 0 0 40px rgba(102, 126, 234, 0.2); transform: scale(1); }
            50% { box-shadow: 0 12px 60px rgba(102, 126, 234, 0.6), 0 0 60px rgba(102, 126, 234, 0.4); transform: scale(1.02); }
        }
        
        @keyframes glowPulse {
            0%, 100% { filter: brightness(1); }
            50% { filter: brightness(1.1); }
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.5), 0 0 80px rgba(102, 126, 234, 0.3);
        }
        
        body.dark-mode .stat-card {
            background: linear-gradient(135deg, #48dbfb 0%, #0abde3 100%);
            box-shadow: 0 12px 40px rgba(72, 219, 251, 0.3);
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 50px rgba(102, 126, 234, 0.4);
        }
        
        .stat-card.stat-danger {
            background: linear-gradient(135deg, #f5576c 0%, #e84a5f 100%);
            box-shadow: 0 12px 40px rgba(245, 87, 108, 0.3);
        }
        
        .stat-card.stat-danger:hover {
            box-shadow: 0 16px 50px rgba(245, 87, 108, 0.4);
        }
        
        .stat-card.stat-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            box-shadow: 0 12px 40px rgba(81, 207, 102, 0.3);
        }
        
        .stat-card.stat-success:hover {
            box-shadow: 0 16px 50px rgba(81, 207, 102, 0.4);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 8s infinite;
        }
        
        .stat-card .number {
            font-size: 42px;
            font-weight: 900;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .stat-card .label {
            font-size: 14px;
            opacity: 0.95;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            z-index: 2;
        }
        
        .stat-card i {
            font-size: 24px;
            margin-bottom: 10px;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        
        /* Stats Box */
        .stats-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 32px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.3);
            animation: scaleIn 0.8s ease;
        }
        
        body.dark-mode .stats-box {
            background: linear-gradient(135deg, #48dbfb 0%, #0abde3 100%);
        }
        
        .stats-box::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 8s infinite;
        }
        
        .stats-box .number {
            font-size: 48px;
            font-weight: 900;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .stats-box .label {
            font-size: 15px;
            opacity: 0.95;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            z-index: 2;
        }
        
        /* Search Box */
        .search-box {
            position: relative;
            margin-bottom: 28px;
        }
        
        .search-box input {
            padding-left: 48px;
            width: 100%;
        }
        
        .search-box input::placeholder {
            color: #ffffff;
            opacity: 1;
        }
        
        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-weight: 700;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #999;
        }
        
        body.dark-mode .empty-state {
            color: #666;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.4;
            animation: float 4s ease-in-out infinite;
        }
        
        .empty-state p {
            font-size: 18px;
            margin: 0;
            font-weight: 600;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Footer */
        .footer {
            background: rgba(0, 0, 0, 0.3);
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 12px;
            margin-top: 40px;
            font-size: 14px;
            font-weight: 500;
        }
        
        body.dark-mode .footer {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .footer i {
            color: #f5576c;
            margin: 0 4px;
        }
        
        /* Quick Info Badge */
        .quick-info {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            margin: 4px;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Export Button */
        .export-section {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #48dbfb 0%, #0abde3 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 700;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-export:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(72, 219, 251, 0.4);
        }
        
        /* Header Top Bar */
        .top-bar {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        body.dark-mode .top-bar {
            background: rgba(72, 219, 251, 0.1);
        }
        
        .top-bar-title {
            color: white;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .top-bar-info {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes shimmer {
            0%, 100% { transform: translate(-50%, -50%); }
            50% { transform: translate(-40%, -40%); }
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .small-text {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
            display: block;
            font-weight: 500;
        }
        
        body.dark-mode .small-text {
            color: #888;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-section h1 {
                font-size: 32px;
            }
            
            .header-section p {
                font-size: 14px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .stats-box {
                padding: 24px;
            }
            
            .stats-box .number {
                font-size: 36px;
            }
            
            .theme-toggle {
                top: 10px;
                right: 10px;
                padding: 10px 14px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

<!-- Dark Mode Toggle -->
<div class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
    <i class="fas fa-moon"></i>
    <span id="theme-text">Dark</span>
</div>

<div class="container mt-5 mb-5">
    <!-- Top Bar Info -->
    <div class="top-bar">
        <div class="top-bar-title">
            <i class="fas fa-info-circle"></i>
            Status Sistem
        </div>
        <div class="top-bar-info">
            <span class="quick-info">
                <i class="fas fa-database"></i> Database: Aktif
            </span>
            <span class="quick-info">
                <i class="fas fa-cloud"></i> Server: Online
            </span>
            <span class="quick-info">
                <i class="fas fa-clock"></i> <?= date('d M Y - H:i') ?>
            </span>
        </div>
    </div>

    <div class="header-section">
        <h1><i class="fas fa-clinic-medical"></i> Teknologi Informasi Pasien</h1>
        <p>Teknologi Input Data Pasien</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($sukses)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($sukses) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Form Input -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-file-medical"></i> Input Data Pasien Baru</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nik" class="form-label">NIK <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nik" name="nik" 
                               placeholder="3201234567890123" required maxlength="16" 
                               value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>">
                        <small class="small-text"><i class="fas fa-info-circle"></i> 16 digit nomor identitas</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" 
                               placeholder="Nama pasien" required 
                               value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
                        <small class="small-text"><i class="fas fa-info-circle"></i> Minimal 3 karakter</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="jenis_kelamin" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                        <select class="form-select" name="jenis_kelamin" id="jenis_kelamin" required>
                            <option value="">-- Pilih Jenis Kelamin --</option>
                            <option value="Laki-laki" <?= ($_POST['jenis_kelamin'] ?? '') === 'Laki-laki' ? 'selected' : '' ?>>
                                <i class="fas fa-mars"></i> Laki-laki
                            </option>
                            <option value="Perempuan" <?= ($_POST['jenis_kelamin'] ?? '') === 'Perempuan' ? 'selected' : '' ?>>
                                <i class="fas fa-venus"></i> Perempuan
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="alamat" name="alamat" 
                               placeholder="Alamat domisili lengkap" required 
                               value="<?= htmlspecialchars($_POST['alamat'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="keluhan" class="form-label">Keluhan <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="keluhan" name="keluhan" 
                              rows="3" placeholder="Jelaskan keluhan pasien..." required><?= htmlspecialchars($_POST['keluhan'] ?? '') ?></textarea>
                </div>
                
                <div class="d-flex gap-2 justify-content-end">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Bersihkan
                    </button>
                    <button type="submit" name="simpan" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Tabel -->
    <div class="card">
        <div class="card-header header-secondary">
            <h5><i class="fas fa-list-check"></i> Data Pasien Terdaftar</h5>
        </div>
        <div class="card-body">
            <!-- Statistik Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="number"><?= $total_pasien ?></div>
                    <div class="label">Total Pasien</div>
                </div>
                <div class="stat-card stat-danger">
                    <i class="fas fa-mars"></i>
                    <div class="number"><?= $stats_jk['Laki-laki'] ?></div>
                    <div class="label">Laki-laki</div>
                </div>
                <div class="stat-card stat-success">
                    <i class="fas fa-venus"></i>
                    <div class="number"><?= $stats_jk['Perempuan'] ?></div>
                    <div class="label">Perempuan</div>
                </div>
            </div>
            
            <!-- Search & Sort & Export Bar -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Cari berdasarkan NIK, Nama, atau Alamat..." 
                                   value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search) || !empty($filter_jk)): ?>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Reset
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" id="filterJK" onchange="filterByGender()">
                        <option value="">📋 Semua Jenis Kelamin</option>
                        <option value="Laki-laki" <?= $filter_jk === 'Laki-laki' ? 'selected' : '' ?>>♂️ Laki-laki</option>
                        <option value="Perempuan" <?= $filter_jk === 'Perempuan' ? 'selected' : '' ?>>♀️ Perempuan</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" id="sortSelect" onchange="changeSortAndSearch()">
                        <option value="terbaru" <?= $sort_by === 'terbaru' ? 'selected' : '' ?>>↓ Terbaru</option>
                        <option value="nama" <?= $sort_by === 'nama' ? 'selected' : '' ?>>↑ Nama A-Z</option>
                    </select>
                </div>
            </div>
            
            <!-- Export Section -->
            <div class="export-section">
                <button onclick="exportToCSV()" class="btn-export">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button onclick="printTable()" class="btn-export" style="background: linear-gradient(135deg, #f5576c 0%, #e84a5f 100%); box-shadow: 0 12px 40px rgba(245, 87, 108, 0.3);">
                    <i class="fas fa-print"></i> Print
                </button>
                <span class="quick-info">
                    <i class="fas fa-info-circle"></i> Total Data: <strong><?= $total_pasien ?></strong>
                </span>
            </div>

            <!-- Tabel Data -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><i class="fas fa-hashtag"></i> #</th>
                            <th><i class="fas fa-id-card"></i> NIK</th>
                            <th><i class="fas fa-user"></i> Nama</th>
                            <th><i class="fas fa-person"></i> L/P</th>
                            <th><i class="fas fa-map-marker-alt"></i> Alamat</th>
                            <th><i class="fas fa-stethoscope"></i> Keluhan</th>
                            <th><i class="fas fa-calendar"></i> Tanggal</th>
                            <th style="width: 120px;"><i class="fas fa-cogs"></i> Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daftar_pasien)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Belum ada data pasien</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftar_pasien as $no => $row): ?>
                                <tr>
                                    <td class="text-center"><strong><?= $no + 1 ?></strong></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($row['nik']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['jenis_kelamin']) ?></td>
                                    <td><?= htmlspecialchars($row['alamat']) ?></td>
                                    <td><span class="badge"><?= htmlspecialchars($row['keluhan']) ?></span></td>
                                    <td><?= htmlspecialchars($row['tanggal_daftar']) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($row['nik']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['jenis_kelamin']) ?></td>
                                    <td><?= htmlspecialchars($row['alamat']) ?></td>
                                    <td><span class="badge"><?= htmlspecialchars($row['keluhan']) ?></span></td>
                                    <td><?= htmlspecialchars($row['tanggal_daftar']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm" style="background: linear-gradient(135deg, #48dbfb 0%, #0abde3 100%); color: white; border: none; border-radius: 8px; padding: 8px 12px; font-weight: 700;" onclick="editData(<?= htmlspecialchars(json_encode($row)) ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?hapus=<?= $row['id'] ?>" 
                                               class="btn btn-danger-soft btn-sm"
                                               onclick="return confirm('Yakin ingin menghapus data ini?')">
                                                <i class="fas fa-trash"></i> Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border: none; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); background: white;">
                <div class="modal-header" style="background: linear-gradient(135deg, #48dbfb 0%, #0abde3 100%); color: white; border: none; border-radius: 16px 16px 0 0;">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Data Pasien</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <form id="editForm" method="POST">
                        <input type="hidden" name="id" id="editId">
                        <input type="hidden" name="edit" value="1">
                        
                        <div class="mb-3">
                            <label for="editNik" class="form-label">NIK <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editNik" name="nik" maxlength="16" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editNama" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editNama" name="nama" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editJK" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select class="form-select" id="editJK" name="jenis_kelamin" required>
                                <option value="">-- Pilih --</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editAlamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editAlamat" name="alamat" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editKeluhan" class="form-label">Keluhan <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="editKeluhan" name="keluhan" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #f0f0f0;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="submitEditForm()">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <p>
            <i class="fas fa-heart"></i> 
            Teknologi Informasi Pasien v1.0 
            <i class="fas fa-heart"></i>
            <br>
            <small>© <?= date('Y') ?> - Sistem Manajemen Data Pasien | Dikembangkan dengan <i class="fas fa-code"></i> & <i class="fas fa-heart"></i></small>
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-dismiss alerts setelah 5 detik
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Validasi input NIK hanya angka
    document.getElementById('nik')?.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
    });
    
    // Fungsi untuk perubahan sorting dengan preserving search
    function changeSortAndSearch() {
        const sortValue = document.getElementById('sortSelect').value;
        const searchValue = new URLSearchParams(window.location.search).get('search') || '';
        
        let url = '?sort=' + sortValue;
        if (searchValue) {
            url += '&search=' + encodeURIComponent(searchValue);
        }
        window.location.href = url;
    }
    
    // Fungsi Filter by Gender
    function filterByGender() {
        const filterValue = document.getElementById('filterJK').value;
        const searchValue = new URLSearchParams(window.location.search).get('search') || '';
        const sortValue = new URLSearchParams(window.location.search).get('sort') || 'terbaru';
        
        let url = '?sort=' + sortValue;
        if (searchValue) {
            url += '&search=' + encodeURIComponent(searchValue);
        }
        if (filterValue) {
            url += '&filter_jk=' + encodeURIComponent(filterValue);
        }
        window.location.href = url;
    }
    
    // Fungsi Edit Data
    function editData(data) {
        document.getElementById('editId').value = data.id;
        document.getElementById('editNik').value = data.nik;
        document.getElementById('editNama').value = data.nama;
        document.getElementById('editJK').value = data.jenis_kelamin;
        document.getElementById('editAlamat').value = data.alamat;
        document.getElementById('editKeluhan').value = data.keluhan;
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }
    
    // Fungsi Dark Mode Toggle
    function toggleDarkMode() {
        const html = document.documentElement;
        const body = document.body;
        const themeText = document.getElementById('theme-text');
        const isDarkMode = body.classList.toggle('dark-mode');
        
        // Simpan preference ke localStorage
        localStorage.setItem('darkMode', isDarkMode ? 'true' : 'false');
        
        // Update icon dan text
        const icon = document.querySelector('.theme-toggle i');
        if (isDarkMode) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
            themeText.textContent = 'Light';
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
            themeText.textContent = 'Dark';
        }
    }
    
    // Load dark mode preference saat halaman dimuat
    window.addEventListener('DOMContentLoaded', function() {
        const darkModeEnabled = localStorage.getItem('darkMode') === 'true';
        if (darkModeEnabled) {
            document.body.classList.add('dark-mode');
            const icon = document.querySelector('.theme-toggle i');
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
            document.getElementById('theme-text').textContent = 'Light';
        }
        
        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.animation = 'slideInDown 0.3s ease reverse';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });
    
    // Fungsi Submit Edit Form
    function submitEditForm() {
        const form = document.getElementById('editForm');
        
        // Validasi NIK
        const nik = document.getElementById('editNik').value;
        if (nik.length !== 16 || !/^\d+$/.test(nik)) {
            alert('NIK harus 16 digit angka');
            return;
        }
        
        form.submit();
    }
    
    // Fungsi Export ke CSV
    function exportToCSV() {
        const table = document.querySelector('table');
        if (!table) {
            alert('Tidak ada data untuk diexport');
            return;
        }
        
        let csv = [];
        let rows = table.querySelectorAll('tr');
        
        rows.forEach(row => {
            let cols = row.querySelectorAll('td, th');
            let csvRow = [];
            cols.forEach((col, index) => {
                // Skip kolom aksi
                if (index !== cols.length - 1) {
                    csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
                }
            });
            csv.push(csvRow.join(','));
        });
        
        // Create blob and download
        const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
        const link = document.createElement('a');
        link.setAttribute('href', encodeURI(csvContent));
        link.setAttribute('download', 'data_pasien_' + new Date().getTime() + '.csv');
        link.click();
    }
    
    // Fungsi Print
    function printTable() {
        const table = document.querySelector('table');
        if (!table) {
            alert('Tidak ada data untuk dicetak');
            return;
        }
        
        const printWindow = window.open('', '', 'height=400,width=800');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Laporan Data Pasien</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { text-align: center; color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #333; padding: 10px; text-align: left; }
                    th { background-color: #f5f5f5; font-weight: bold; }
                    .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <h1>Laporan Data Pasien</h1>
                <p><strong>Tanggal Cetak:</strong> ${new Date().toLocaleDateString('id-ID')}</p>
                ${table.outerHTML}
                <div class="footer">
                    <p>Teknologi Informasi Pasien © ${new Date().getFullYear()}</p>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
</script>
</body>
</html>