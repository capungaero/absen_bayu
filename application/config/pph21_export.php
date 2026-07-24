<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Konfigurasi Export PPh 21 (tools/pph21_export).
 * Nilai di sini boleh diedit tanpa menyentuh kode.
 */

// Premi BPJS yang DIBAYAR PERUSAHAAN (penambah bruto, Tabel 8.1 buku DJP).
// Diisikan otomatis untuk karyawan yang bulan itu punya potongan BPJS (> 0).
// Nilai JKM 8.548,90 dikonfirmasi user 9 Jul 2026 (kertas kerja Mei sempat memakai 13.548,87).
// CATATAN: jkk & jkm di sini hanya DEFAULT — bila sudah pernah disimpan lewat form
// di halaman Export PPh21 (tabel pph21_settings), nilai tabel itu yang dipakai.
$config['pph21_premi'] = [
    'jkk' => 7639.10,
    'jkm' => 8548.90,
    'kes' => 127318.00,
];

// Nama potongan yang DITAMBAHKAN KEMBALI ke bruto sebagai "Tunjangan/Cash Bon"
// (pinjaman / bukan pengurang penghasilan). Harus sama dengan deduction_name di DB.
$config['pph21_addback_deductions'] = ['CASHBON', 'PIUTANG KANVAS'];

// Nama potongan penanda kepesertaan BPJS (bila salah satu > 0 → premi perusahaan diisi).
$config['pph21_bpjs_markers'] = ['BPJS KESEHATAN', 'BPJS KETENAGAKERJAAN'];

// Kode penempatan per branch (kolom PENEMPATAN pada export gabungan Semua CV).
$config['pph21_penempatan'] = [
    1 => 'HW-SDR',  // TIFFANY HOUSEWARE SDR
    2 => 'HW-GBR',  // TIFFANY HOUSEWARE GBR
];

// NPWP pemotong per subdivision (CV). ID TKU = NPWP + "000000".
// Sumber: npwp.xlsx (8 Jul 2026). Key = subdivision_id.
$config['pph21_npwp'] = [
    6  => '0854470671204000', // CV.BRILLIANT HOUSEWARE
    7  => '0137964250204000', // CV KENZIE CLARISSA TIFFANY
    8  => '1000000000540854', // CV DIRGA MARKET GLOBAL
    9  => '0627286842204000', // CV.MOISSO
    12 => '0080146111204000', // CV.REYBI GLOBAL RITEL
    13 => '1000000002698397', // CV.REYGA RETAIL INDONESIA
    14 => '1000000008041548', // CV.CLARISSA HOUSEWARE INDONESIA
    15 => '1000000004394194', // CV TFNY HOME AND KITCHEN
    16 => '1091031211364399', // CV KIANO RETAIL GLOBAL
];
