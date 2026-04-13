<?php
// 1. Reset data pasien
file_put_contents('results.json', '[]');

// 2. Reset memori riwayat yang dihapus
file_put_contents('deleted_sessions.json', '[]');

// 3. Reset nomor urut Vaksinasi Server
file_put_contents('max_session.txt', '1');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Resetting...</title>
</head>
<body>
    <script>
        // 4. Terpenting: Hapus memori angka Vaksinasi di Browser (Local Storage)
        localStorage.removeItem('activeVaksinasiId');
        
        // 5. Tendang balik ke beranda simulasi
        window.location.href = 'index.php';
    </script>
</body>
</html>
