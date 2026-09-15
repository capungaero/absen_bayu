// Kompres/kecilkan foto di browser SEBELUM upload -- foto asli dari kamera HP
// (bisa 3-10MB) dikirim mentah tanpa ini, jadi upload terasa lama terutama di
// sinyal lemah di lokasi toko. Server sudah resize ke 1200x1200@80% (lihat
// Temuan.php::_upload_photo()), tapi itu terjadi SETELAH file penuh terkirim --
// mengecilkan di HP dulu memangkas waktu kirimnya, bukan cuma ukuran simpannya.
export async function compressImage(file, { maxDim = 1600, quality = 0.8 } = {}) {
  if (!file || !file.type || !file.type.startsWith('image/') || file.type === 'image/svg+xml') {
    return file;
  }

  try {
    const bitmap = await loadBitmap(file);
    const { width, height } = bitmap;
    const scale = Math.min(1, maxDim / Math.max(width, height));
    const targetW = Math.max(1, Math.round(width * scale));
    const targetH = Math.max(1, Math.round(height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = targetW;
    canvas.height = targetH;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(bitmap, 0, 0, targetW, targetH);
    if (typeof bitmap.close === 'function') bitmap.close();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    if (!blob || blob.size >= file.size) return file; // sudah kecil -- pakai asli

    const name = file.name.replace(/\.\w+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg' });
  } catch (e) {
    return file; // gagal kompres -> tetap upload foto asli, jangan blokir laporan
  }
}

function loadBitmap(file) {
  if (typeof createImageBitmap === 'function') {
    return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(() => loadViaImageElement(file));
  }
  return loadViaImageElement(file);
}

function loadViaImageElement(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = (e) => { URL.revokeObjectURL(url); reject(e); };
    img.src = url;
  });
}
