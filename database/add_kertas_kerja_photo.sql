-- Kertas Kerja: bukti pengisian sekarang FOTO kertas tulisan tangan, bukan
-- input teks checklist. Kolom lama (notes, kertas_kerja_item) dibiarkan ada
-- utk data historis, tidak lagi diisi dari form baru.
ALTER TABLE kertas_kerja
  ADD COLUMN photo_path VARCHAR(255) NULL AFTER notes;
