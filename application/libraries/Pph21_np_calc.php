<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once __DIR__.'/Pph21_workbook.php';

/**
 * Pph21_np_calc — mesin hitung PPh 21 non-pegawai-tetap + builder XML Bp21Bulk.
 *
 * Aturan (Buku PPh 21/26 DJP 2024, PP 58/2023, PMK 168/2023):
 *  - TER    : upah pegawai tidak tetap dibayar bulanan → TER bulanan (kategori
 *             PTKP), gross-up iteratif 3 tahap bila PPh ditanggung perusahaan.
 *  - HARIAN : upah harian/mingguan/satuan/borongan ≤ 2,5 jt/hari → rata-rata
 *             sehari ≤ 450 rb = 0%; sisanya 0,5%. (> 2,5 jt/hari wajib pindah
 *             kode 21-100-30, jenis PS17.)
 *  - PS17   : DPP = deemed% × bruto, tarif Pasal 17 lapisan I (5%). DPP > 60 jt
 *             (lapisan II) tidak didukung format 1-rate Coretax → warning.
 *  - Gross-up terbukti dari pelaporan Juni 2026: Gross XML = neto ÷ (1 − tarif
 *             efektif), mis. 5.000.000 ÷ 0,975 = 5.128.205,128205…
 *
 * Skema XML Bp21Bulk & format angka (15 digit signifikan ala Excel) mengikuti
 * sampel resmi yang diterima Coretax: sample_coretax/06 Juni - PPH21 TDK TETAP
 * CV Brilliant.xml (folder kerja PAJAK_TIFFANY).
 */
class Pph21_np_calc {

    /** Kode objek pajak (sheet REF template Coretax): kode => [deemed, jenis, nama]. */
    public static $REF = [
        '21-100-35' => [100, 'TER',    'Upah Pegawai Tidak Tetap yang Dibayarkan secara Bulanan'],
        '21-100-10' => [100, 'TER',    'Honorarium/Imbalan Anggota Dewan Komisaris/Pengawas yang Tidak Teratur'],
        '21-100-27' => [100, 'TER',    'Upah Pegawai Tidak Tetap Bulanan (Fasilitas Daerah Tertentu)'],
        '21-100-37' => [100, 'TER',    'Pegawai Tetap di Daerah Tertentu Tidak Memenuhi Syarat Fasilitas'],
        '21-100-24' => [100, 'HARIAN', 'Upah Pegawai Tidak Tetap Harian/Mingguan/Satuan/Borongan ≤ Rp2,5 jt/hari'],
        '21-100-29' => [100, 'HARIAN', 'Upah Pegawai Tidak Tetap Harian ≤ Rp2,5 jt/hari (Fasilitas Daerah Tertentu)'],
        '21-100-30' => [50,  'PS17',   'Upah Pegawai Tidak Tetap Harian/Mingguan/Satuan/Borongan > Rp2,5 jt/hari'],
        '21-100-31' => [50,  'PS17',   'Upah Pegawai Tidak Tetap Harian > Rp2,5 jt/hari (Fasilitas Daerah Tertentu)'],
        '21-100-07' => [50,  'PS17',   'Imbalan Tenaga Ahli (Pengacara, Akuntan, Arsitek, Dokter, Konsultan, Notaris, PPAT, Penilai, Aktuaris)'],
        '21-100-18' => [50,  'PS17',   'Imbalan Penasihat, Pengajar, Pelatih, Penceramah, Penyuluh, Moderator'],
        '21-100-19' => [50,  'PS17',   'Imbalan Pengarang, Peneliti, Penerjemah'],
        '21-100-20' => [50,  'PS17',   'Imbalan Pemberi Jasa dalam Segala Bidang'],
        '21-100-21' => [50,  'PS17',   'Imbalan Agen Iklan'],
        '21-100-22' => [50,  'PS17',   'Imbalan Pengawas atau Pengelola Proyek'],
        '21-100-23' => [50,  'PS17',   'Imbalan Pembawa Pesanan/Penemu Langganan/Perantara'],
        '21-100-06' => [50,  'PS17',   'Imbalan Petugas Penjaja Barang Dagangan'],
        '21-100-05' => [50,  'PS17',   'Imbalan Agen Asuransi'],
        '21-100-04' => [50,  'PS17',   'Imbalan Distributor Pemasaran Berjenjang/Penjualan Langsung'],
        '21-100-33' => [50,  'PS17',   'Imbalan Pemain Musik, MC, Penyanyi, Artis, Kru Film, Influencer, Seniman Lainnya'],
        '21-100-34' => [50,  'PS17',   'Imbalan Olahragawan'],
        '21-100-12' => [100, 'PS17',   'Uang Manfaat Pensiun Diambil Sebagian (Masih Berstatus Pegawai)'],
        '21-100-36' => [100, 'PS17',   'Imbalan Peserta Perlombaan dalam Segala Bidang'],
        '21-100-14' => [100, 'PS17',   'Imbalan Peserta Rapat/Konferensi/Sidang/Seminar/Kegiatan Tertentu'],
        '21-100-15' => [100, 'PS17',   'Imbalan Peserta/Anggota Kepanitiaan Penyelenggara Kegiatan'],
        '21-100-16' => [100, 'PS17',   'Imbalan Peserta Pendidikan, Pelatihan, dan Magang'],
        '21-100-17' => [100, 'PS17',   'Imbalan Peserta Kegiatan Lainnya'],
        '21-100-25' => [100, 'PS17',   'Pesangon/Manfaat Pensiun/THT/JHT Dibayar Tahun Ketiga dst.'],
        '21-402-04' => [100, '0',      'Honor APBN/APBD PNS Gol I-II, TNI/POLRI Tamtama-Bintara, Pensiunannya'],
        '21-402-02' => [100, '5',      'Honor APBN/APBD PNS Gol III, TNI/POLRI Perwira Pertama, Pensiunannya'],
        '21-402-03' => [100, '15',     'Honor APBN/APBD Pejabat Negara, PNS Gol IV, Perwira Menengah/Tinggi'],
    ];

    /** Tarif TER bulanan dari tabel bersama (Pph21_workbook). Batas eksklusif: > limit. */
    private function ter_monthly($gross, $cat) {
        $tables = Pph21_workbook::ter_tables();
        $rate = 0;
        foreach ($tables[$cat] as $row) {
            if ($row[0] == 0 || $gross > $row[0]) $rate = $row[1];
            else break;
        }
        return $rate;
    }

    /**
     * Hitung satu baris.
     *
     * @param array $in ['kode','ptkp','hari','neto','gross_up']
     * @return array ['ok'=>bool,'error'=>?,'warnings'=>[], 'deemed','tarif','bruto','pph','jenis']
     */
    public function calc($in) {
        $kode = trim((string)$in['kode']);
        if (!isset(self::$REF[$kode])) {
            return ['ok' => false, 'error' => "Kode objek '$kode' tidak dikenal"];
        }
        list($deemed, $jenis) = self::$REF[$kode];
        $neto = round((float)$in['neto'], 2);
        $gu   = !isset($in['gross_up']) || $in['gross_up'];
        $warnings = [];
        if ($neto <= 0) return ['ok' => false, 'error' => 'Pembayaran (neto) harus > 0'];

        if ($jenis === 'TER') {
            $cat = Pph21_workbook::ter_category(isset($in['ptkp']) ? $in['ptkp'] : 'TK/0');
            if ($gu) {
                $t1 = $this->ter_monthly($neto, $cat);
                $t2 = $this->ter_monthly($neto / (1 - $t1 / 100), $cat);
                $tarif = $this->ter_monthly($neto / (1 - $t2 / 100), $cat);
            } else {
                $tarif = $this->ter_monthly($neto, $cat);
            }
            $bruto = $gu && $tarif > 0 ? $neto / (1 - $tarif / 100) : $neto;
            $pph = $bruto * $tarif / 100;
        } elseif ($jenis === 'HARIAN') {
            $hari = (int)$in['hari'];
            if ($hari < 1) return ['ok' => false, 'error' => 'Jumlah hari wajib diisi utk upah harian'];
            $rata = $neto / $hari;
            if ($rata > 2500000) {
                return ['ok' => false, 'error' => 'Rata-rata > Rp2,5 jt/hari — gunakan kode 21-100-30 (PS17)'];
            }
            $tarif = $rata > 450000 ? 0.5 : 0;
            $bruto = $gu && $tarif > 0 ? $neto / (1 - $tarif / 100) : $neto;
            $pph = $bruto * $tarif / 100;
        } else { // PS17 atau tarif tetap (21-402-*)
            $tarif = $jenis === 'PS17' ? 5.0 : (float)$jenis;
            $eff = $tarif * $deemed / 10000;   // tarif efektif atas bruto
            $bruto = $gu && $eff > 0 ? $neto / (1 - $eff) : $neto;
            $dpp = $bruto * $deemed / 100;
            if ($jenis === 'PS17' && $dpp > 60000000) {
                $warnings[] = 'DPP > Rp60 jt (lapisan Pasal 17 ke-2) — format 1-rate Coretax tidak memadai, hitung manual';
            }
            $pph = $bruto * $eff;
        }

        return ['ok' => true, 'warnings' => $warnings, 'jenis' => $jenis,
                'deemed' => $deemed, 'tarif' => $tarif,
                'bruto' => $bruto, 'pph' => round($pph, 2)];
    }

    /**
     * Bangun XML Bp21Bulk (1 file per CV, gabungan semua kategori non-pegawai-tetap).
     *
     * @param string $npwp  NPWP CV pemotong (16 digit)
     * @param int    $month @param int $year
     * @param array  $rows  masing2: nik, ptkp, kode_objek, bruto, deemed, tarif,
     *                      doc_number, doc_date (Y-m-d)
     * @return string XML
     */
    public function xml_bulk($npwp, $month, $year, $rows) {
        $last = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        $x = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
        $x .= "<Bp21Bulk xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">\n";
        $x .= "\t<TIN>".$this->esc($npwp)."</TIN>\n";
        $x .= "\t<ListOfBp21>\n";
        foreach ($rows as $r) {
            $nik = trim((string)$r['nik']);
            $x .= "\t\t<Bp21>\n";
            $x .= "\t\t\t<TaxPeriodMonth>".(int)$month."</TaxPeriodMonth>\n";
            $x .= "\t\t\t<TaxPeriodYear>".(int)$year."</TaxPeriodYear>\n";
            $x .= "\t\t\t<CounterpartTin>".$this->esc($nik)."</CounterpartTin>\n";
            $x .= "\t\t\t<IDPlaceOfBusinessActivityOfIncomeRecipient>".$this->esc($nik.'000000')."</IDPlaceOfBusinessActivityOfIncomeRecipient>\n";
            $x .= "\t\t\t<StatusTaxExemption>".$this->esc($r['ptkp'])."</StatusTaxExemption>\n";
            $x .= "\t\t\t<TaxCertificate>N/A</TaxCertificate>\n";
            $x .= "\t\t\t<TaxObjectCode>".$this->esc($r['kode_objek'])."</TaxObjectCode>\n";
            $x .= "\t\t\t<Gross>".$this->xf($r['bruto'])."</Gross>\n";
            $x .= "\t\t\t<Deemed>".(int)$r['deemed']."</Deemed>\n";
            $x .= "\t\t\t<Rate>".$this->xf($r['tarif'])."</Rate>\n";
            $x .= "\t\t\t<Document>PaymentProof</Document>\n";
            $x .= "\t\t\t<DocumentNumber>".$this->esc($r['doc_number'])."</DocumentNumber>\n";
            $x .= "\t\t\t<DocumentDate>".$this->esc($r['doc_date'])."</DocumentDate>\n";
            $x .= "\t\t\t<IDPlaceOfBusinessActivity>".$this->esc($npwp.'000000')."</IDPlaceOfBusinessActivity>\n";
            $x .= "\t\t\t<WithholdingDate>".$last."</WithholdingDate>\n";
            $x .= "\t\t</Bp21>\n";
        }
        $x .= "\t</ListOfBp21>\n";
        $x .= "</Bp21Bulk>\n";
        return $x;
    }

    /** Format angka XML ala Excel: 15 digit signifikan, tanpa trailing zero. */
    public function xf($v) {
        $v = (float)$v;
        if (abs($v - round($v)) < 1e-9) return (string)(int)round($v);
        $dec = max(0, 15 - strlen((string)(int)abs($v)));
        return rtrim(rtrim(number_format($v, $dec, '.', ''), '0'), '.');
    }

    private function esc($s) {
        return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
