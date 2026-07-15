<?php
require_once 'config.php';

// Matikan semua error reporting ke output, agar tidak mengganggu JSON
error_reporting(0);
ini_set('display_errors', 0);

// Mendapatkan action dari parameter GET
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        // ========== MASTER SISWA ==========
        case 'getStudents':
            $stmt = $pdo->query("SELECT nama, kelas FROM siswa ORDER BY nama");
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse($students);
            break;

        // ========== GURU PIKET ==========
        case 'getGuruPiketList':
            $stmt = $pdo->query("SELECT DISTINCT guru_piket FROM guru_piket ORDER BY guru_piket");
            $guru = $stmt->fetchAll(PDO::FETCH_COLUMN);
            sendResponse($guru);
            break;

        case 'getJadwalGuruPiket':
            $stmt = $pdo->query("SELECT hari, guru_piket, petugas_absen, guru_bk FROM guru_piket ORDER BY FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu')");
            $jadwal = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse($jadwal);
            break;

        // ========== CRUD IZIN SISWA ==========
        case 'saveIzin':
            $data = json_decode(file_get_contents('php://input'), true);
            $type = $data['type'] ?? '';
            $formData = $data['data'] ?? [];

            if ($type == 'izinSiswa') {
                $sql = "INSERT INTO izin_siswa (tanggal, nama_siswa, kelas, keterangan, guru_piket) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $formData['Tanggal'],
                    $formData['Nama'],
                    $formData['Kelas'],
                    $formData['Keterangan'],
                    $formData['GuruPiket']
                ]);

                updateRekapSiswa($pdo, $formData['Nama'], $formData['Keterangan']);
            } elseif ($type == 'izinMeninggalkanKelas') {
                $sql = "INSERT INTO izin_meninggalkan_kelas 
                        (tanggal, nama_siswa, kelas, keterangan, jam_keluar, jam_kembali, guru_piket) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $formData['Tanggal'],
                    $formData['Nama'],
                    $formData['Kelas'],
                    $formData['Keterangan'],
                    $formData['JamKeluar'],
                    $formData['JamKembali'] ?: null,
                    $formData['GuruPiket']
                ]);
            }

            sendResponse(['success' => true, 'message' => 'Data berhasil disimpan']);
            break;

        case 'updateIzin':
            $data = json_decode(file_get_contents('php://input'), true);
            $type = $data['type'];
            $rowId = $data['rowId'];
            $formData = $data['data'];

            if ($type == 'izinSiswa') {
                $sql = "UPDATE izin_siswa SET 
                        tanggal = ?, nama_siswa = ?, kelas = ?, keterangan = ?, guru_piket = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $formData['Tanggal'],
                    $formData['Nama'],
                    $formData['Kelas'],
                    $formData['Keterangan'],
                    $formData['GuruPiket'],
                    $rowId
                ]);
            } else {
                $sql = "UPDATE izin_meninggalkan_kelas SET 
                        tanggal = ?, nama_siswa = ?, kelas = ?, keterangan = ?, 
                        jam_keluar = ?, jam_kembali = ?, guru_piket = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $formData['Tanggal'],
                    $formData['Nama'],
                    $formData['Kelas'],
                    $formData['Keterangan'],
                    $formData['JamKeluar'],
                    $formData['JamKembali'] ?: null,
                    $formData['GuruPiket'],
                    $rowId
                ]);
            }

            sendResponse(['success' => true]);
            break;

        case 'getIzin':
            $type = isset($_GET['type']) ? $_GET['type'] : '';

            if ($type == 'izinSiswa') {
                $stmt = $pdo->query("SELECT id, tanggal, nama_siswa, kelas, keterangan, guru_piket 
                                     FROM izin_siswa ORDER BY tanggal DESC, id DESC");
            } else {
                $stmt = $pdo->query("SELECT id, tanggal, nama_siswa, kelas, keterangan, 
                                     jam_keluar, jam_kembali, guru_piket 
                                     FROM izin_meninggalkan_kelas ORDER BY tanggal DESC, id DESC");
            }

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as $key => $row) {
                $data[$key]['rowIndex'] = $row['id'];
            }

            sendResponse($data);
            break;

        case 'getIzinRow':
            $type = isset($_GET['type']) ? $_GET['type'] : '';
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

            if ($type == 'izinSiswa') {
                $stmt = $pdo->prepare("SELECT tanggal, nama_siswa as Nama, kelas as Kelas, 
                                       keterangan as Keterangan, guru_piket as GuruPiket 
                                       FROM izin_siswa WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("SELECT tanggal, nama_siswa as Nama, kelas as Kelas, 
                                       keterangan as Keterangan, jam_keluar as JamKeluar, 
                                       jam_kembali as JamKembali, guru_piket as GuruPiket 
                                       FROM izin_meninggalkan_kelas WHERE id = ?");
            }

            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $data['Tanggal'] = date('Y-m-d', strtotime($data['tanggal']));
            }

            sendResponse($data);
            break;

        // ========== DASHBOARD STATS ==========
        case 'getDashboardStats':
            $today = date('Y-m-d');
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');

            // Hari ini
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM izin_siswa WHERE tanggal = ?");
            $stmt->execute([$today]);
            $todayTerlambat = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM izin_meninggalkan_kelas WHERE tanggal = ?");
            $stmt->execute([$today]);
            $todayPulang = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Bulan ini
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM izin_siswa WHERE tanggal BETWEEN ? AND ?");
            $stmt->execute([$monthStart, $monthEnd]);
            $monthTerlambat = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM izin_meninggalkan_kelas WHERE tanggal BETWEEN ? AND ?");
            $stmt->execute([$monthStart, $monthEnd]);
            $monthPulang = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Data harian untuk grafik
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(tanggal, '%d/%m/%Y') as day,
                    (SELECT COUNT(*) FROM izin_siswa WHERE tanggal = i.tanggal) as terlambat,
                    (SELECT COUNT(*) FROM izin_meninggalkan_kelas WHERE tanggal = i.tanggal) as pulang
                FROM (
                    SELECT tanggal FROM izin_siswa 
                    WHERE tanggal BETWEEN ? AND ?
                    UNION
                    SELECT tanggal FROM izin_meninggalkan_kelas 
                    WHERE tanggal BETWEEN ? AND ?
                ) as i
                GROUP BY tanggal
                ORDER BY tanggal ASC
            ");
            $stmt->execute([$monthStart, $monthEnd, $monthStart, $monthEnd]);
            $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendResponse([
                'todayTerlambat' => (int)$todayTerlambat,
                'todayPulang' => (int)$todayPulang,
                'monthTerlambat' => (int)$monthTerlambat,
                'monthPulang' => (int)$monthPulang,
                'monthlyData' => $monthlyData
            ]);
            break;

        // ========== CETAK SURAT ==========
        case 'getLetterHTML':
            $type = isset($_GET['type']) ? $_GET['type'] : '';
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

            if (empty($type) || $id <= 0) {
                sendResponse(['error' => 'Parameter tidak lengkap'], 400);
                break;
            }

            try {
                if ($type == 'izinSiswa') {
                    $stmt = $pdo->prepare("SELECT * FROM izin_siswa WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM izin_meninggalkan_kelas WHERE id = ?");
                }
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$data) {
                    sendResponse(['error' => 'Data tidak ditemukan'], 404);
                    break;
                }

                $html = generateLetterHTML($type, $data);
                sendResponse(['html' => $html]);
            } catch (Exception $e) {
                sendResponse(['error' => $e->getMessage()], 500);
            }
            break;

        // ========== CETAK REKAP ==========
        case 'getTodayRecap':
            $type = isset($_GET['type']) ? $_GET['type'] : '';
            $today = date('Y-m-d');

            if ($type == 'izinSiswa') {
                $stmt = $pdo->prepare("SELECT * FROM izin_siswa WHERE tanggal = ?");
            } else {
                $stmt = $pdo->prepare("SELECT * FROM izin_meninggalkan_kelas WHERE tanggal = ?");
            }
            $stmt->execute([$today]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendResponse($data);
            break;

        default:
            sendResponse(['error' => 'Action tidak ditemukan'], 404);
    }
} catch (Exception $e) {
    sendResponse(['error' => $e->getMessage()], 500);
}

// ============================================================
// FUNGSI PEMBANTU
// ============================================================
function updateRekapSiswa($pdo, $nama, $keterangan) {
    $field = ($keterangan == 'Sakit') ? 'sakit' : 'izin';
    $sql = "UPDATE siswa SET $field = $field + 1 WHERE nama = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nama]);
}

function generateLetterHTML($type, $data) {
    /**
     * Generate HTML untuk satu surat izin (per siswa)
     */
    function dapatkanHariDanTanggal($tanggalStr) {
        if (!$tanggalStr) return '';
        $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        try {
            $date = new DateTime($tanggalStr);
            return $namaHari[(int)$date->format('w')] . ', ' . $date->format('d/m/Y');
        } catch (Exception $e) {
            return $tanggalStr;
        }
    }

    $title = ($type == 'izinSiswa') ? 'SURAT IZIN SISWA' : 'SURAT IZIN MENINGGALKAN KELAS';
    $nama = $data['nama_siswa'] ?? '-';
    $kelas = $data['kelas'] ?? '-';
    $tgl = $data['tanggal'] ?? '';
    $tglDenganHari = dapatkanHariDanTanggal($tgl);
    $ket = $data['keterangan'] ?? '-';
    $guru = $data['guru_piket'] ?? '-';

    $extra = '';
    if ($type == 'izinMeninggalkanKelas') {
        $jamKeluar = isset($data['jam_keluar']) ? date('H:i', strtotime($data['jam_keluar'])) : '-';
        $jamKembali = isset($data['jam_kembali']) ? date('H:i', strtotime($data['jam_kembali'])) : '-';
        $extra = '<tr><td>Jam Keluar</td><td>: ' . $jamKeluar . ' WIB</td></tr>
                  <tr><td>Jam Kembali</td><td>: ' . $jamKembali . ' WIB</td></tr>';
    }

    $html = '<!DOCTYPE html><html><head><title>Cetak Surat Izin</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        body { font-family: "Times New Roman", Times, serif; color: #000; margin: 0; padding: 0; background: #fff; }
        .kupon-card { width: 90mm; height: 80mm; border: 1px dashed #000; padding: 10px; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; background: #fff; margin: auto; }
        .kop-surat { display: flex; align-items: center; border-bottom: 2px double #000; padding-bottom: 4px; margin-bottom: 4px; }
        .logo-area { flex: 0 0 42px; text-align: center; }
        .logo-area img { max-width: 35px; height: auto; }
        .kop-header { flex: 1; text-align: center; line-height: 1.1; }
        .kop-header h2 { margin: 0; font-size: 8px; font-weight: normal; }
        .kop-header h3 { margin: 0; font-size: 9px; font-weight: normal; }
        .kop-header h1 { margin: 1px 0; font-size: 11px; font-weight: bold; }
        .kop-header p { margin: 0; font-size: 6.5px; }
        .judul-surat { text-align: center; font-size: 10px; font-weight: bold; text-decoration: underline; margin: 4px 0; text-transform: uppercase; }
        .pembuka { margin: 0 0 2px 0; font-size: 9px; line-height: 1.1; }
        table { width: 100%; border-collapse: collapse; margin: 2px 0; }
        table tr td { padding: 1px 0 !important; font-size: 9px !important; line-height: 1.1 !important; font-weight: normal !important; }
        table tr td:first-child { width: 32%; font-weight: bold !important; }
        .penutup { margin: 2px 0 0 0; font-size: 8.5px; font-style: italic; line-height: 1.1; }
        .footer { margin-top: 4px; display: flex; justify-content: flex-end; }
        .signature { text-align: center; width: 55%; font-size: 8.5px; line-height: 1.1; }
        .signature .space { height: 22px; }
    </style>
    </head><body>
    <div class="kupon-card">
        <div class="kop-surat">
            <div class="logo-area">
                <img src="https://res.cloudinary.com/dptukmwku/image/upload/Logo_Provinsi_Jawa_Timur_hgoag1.jpg">
            </div>
            <div class="kop-header">
                <h2>PEMERINTAH PROVINSI JAWA TIMUR</h2>
                <h3>DINAS PENDIDIKAN</h3>
                <h1>SMA NEGERI JATIROGO</h1>
                <p>Jl. Raya Bader No. 20, Tuban, Jawa Timur 62362</p>
                <p>Web: www.smanjatirogo.sch.id | Email: smajatirogo@yahoo.co.id</p>
            </div>
        </div>
        <div class="judul-surat">' . $title . '</div>
        <div class="content">
            <p class="pembuka">Guru Piket SMAN Jatirogo memberikan izin kepada:</p>
            <table>
                <tr><td>Nama Siswa</td><td>: ' . htmlspecialchars($nama) . '</td></tr>
                <tr><td>Kelas</td><td>: ' . htmlspecialchars($kelas) . '</td></tr>
                <tr><td>Hari/Tanggal</td><td>: ' . htmlspecialchars($tglDenganHari) . '</td></tr>
                <tr><td>Keterangan</td><td>: ' . htmlspecialchars($ket) . '</td></tr>
                ' . $extra . '
            </table>
            <p class="penutup">Demikian surat izin ini dibuat untuk digunakan sebagaimana mestinya.</p>
        </div>
        <div class="footer">
            <div class="signature">
                <p>Jatirogo, ' . htmlspecialchars($tgl) . '<br>Guru Piket,</p>
                <div class="space"></div>
                <p><strong>' . htmlspecialchars($guru) . '</strong><br>___________________</p>
            </div>
        </div>
    </div>
    <script>window.onload = function() { window.print(); }</script>
    </body></html>';

    return $html;
}
?>