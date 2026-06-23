from PIL import Image, ImageDraw, ImageFont


W, H = 1240, 1754
OUT = "docs/payroll_simulasi_poster.png"

COLORS = {
    "bg": (247, 248, 251),
    "ink": (23, 32, 51),
    "muted": (101, 112, 132),
    "line": (216, 222, 234),
    "blue": (32, 90, 214),
    "green": (22, 131, 95),
    "green_bg": (233, 247, 242),
    "red": (201, 60, 74),
    "red_bg": (253, 236, 239),
    "amber": (184, 117, 18),
    "amber_bg": (255, 248, 232),
    "dark": (23, 32, 51),
    "white": (255, 255, 255),
}


def font(size, bold=False):
    base = "C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf"
    return ImageFont.truetype(base, size)


F = {
    "title": font(46, True),
    "h1": font(38, True),
    "h2": font(34, True),
    "body": font(26),
    "body_b": font(26, True),
    "small": font(22),
    "small_b": font(22, True),
}


def text(draw, xy, s, fill="ink", f="body", anchor=None):
    draw.text(xy, s, fill=COLORS[fill], font=F[f], anchor=anchor)


def wrapped(draw, xy, s, width, fill="muted", f="body", line_gap=10):
    words = s.split()
    lines = []
    current = ""
    for word in words:
        candidate = word if not current else current + " " + word
        if draw.textlength(candidate, font=F[f]) <= width:
            current = candidate
        else:
            lines.append(current)
            current = word
    if current:
        lines.append(current)
    x, y = xy
    for line in lines:
        text(draw, (x, y), line, fill, f)
        y += F[f].size + line_gap
    return y


def card(draw, box, fill="white", outline="line", radius=18, width=2):
    draw.rounded_rectangle(box, radius=radius, fill=COLORS[fill], outline=COLORS[outline], width=width)


def row(draw, x, y, label, value, w=436):
    text(draw, (x, y), label, "muted", "body")
    text(draw, (x + w, y), value, "ink", "body_b", anchor="ra")


def arrow(draw, start, end):
    draw.line([start, end], fill=(154, 167, 188), width=5)
    x1, y1 = start
    x2, y2 = end
    if x2 >= x1:
        pts = [(x2, y2), (x2 - 18, y2 - 10), (x2 - 18, y2 + 10)]
    else:
        pts = [(x2, y2), (x2 + 18, y2 - 10), (x2 + 18, y2 + 10)]
    draw.polygon(pts, fill=(154, 167, 188))


img = Image.new("RGB", (W, H), COLORS["bg"])
d = ImageDraw.Draw(img)

card(d, (64, 54, 1176, 1700), fill="white", outline="white", radius=28, width=0)
text(d, (94, 126), "Simulasi Gaji Final Karyawan", "ink", "title")
text(d, (96, 190), "Contoh perhitungan sederhana: gaji pokok, bonus, denda, potongan, sampai THP.", "muted", "body")
d.rectangle((94, 220, 1146, 224), fill=(231, 235, 243))

top_cards = [
    ((94, 252, 422, 440), "blue", "1", "Data Karyawan", [("Full", "Rp 3.000.000"), ("Min", "Rp 2.500.000")]),
    ((456, 252, 784, 440), "green", "2", "Income", [("Pokok", "Rp 3.000.000"), ("Bonus", "Rp 495.000")]),
    ((818, 252, 1146, 440), "red", "3", "Potongan", [("Denda", "Rp 475.000"), ("Lain", "Rp 505.385")]),
]

for box, color, num, title, rows in top_cards:
    card(d, box, radius=18)
    x1, y1, x2, _ = box
    d.ellipse((x1 + 20, y1 + 28, x1 + 60, y1 + 68), fill=COLORS[color])
    text(d, (x1 + 40, y1 + 49), num, "white", "small_b", anchor="mm")
    text(d, (x1 + 72, y1 + 58), title, "ink", "body_b")
    row(d, x1 + 28, y1 + 106, rows[0][0], rows[0][1], w=270)
    row(d, x1 + 28, y1 + 146, rows[1][0], rows[1][1], w=270)

arrow(d, (424, 346), (452, 346))
arrow(d, (786, 346), (814, 346))

card(d, (94, 488, 1146, 746), fill="white", outline="line", radius=22)
d.rounded_rectangle((94, 488, 1146, 746), radius=22, fill=(243, 247, 255), outline=(205, 217, 243), width=2)
text(d, (126, 542), "Rumus yang Dipakai Sistem", "ink", "h1")
text(d, (126, 604), "THP = Income - Potongan", "muted", "body")
text(d, (126, 642), "Income = Gaji pokok + lembur + insentif", "muted", "small")
text(d, (126, 670), "Potongan = denda + potongan pribadi + BPJS + bersama + kekurangan hari kerja", "muted", "small")
card(d, (126, 704, 1114, 738), radius=14)
text(d, (150, 729), "Rp 3.495.000 - Rp 980.385 = Rp 2.514.615", "ink", "small_b")

card(d, (94, 790, 594, 1168), radius=22)
text(d, (126, 846), "Income", "green", "h2")
d.line((126, 870, 562, 870), fill=COLORS["line"], width=2)
row(d, 126, 924, "Gaji pokok", "Rp 3.000.000")
row(d, 126, 980, "Lembur 8 jam", "Rp 160.000")
row(d, 126, 1036, "Insentif + komisi", "Rp 335.000")
d.rounded_rectangle((126, 1076, 562, 1136), radius=12, fill=COLORS["green_bg"])
text(d, (150, 1115), "Total Income", "green", "body_b")
text(d, (542, 1115), "Rp 3.495.000", "green", "body_b", anchor="ra")

card(d, (646, 790, 1146, 1168), radius=22)
text(d, (678, 846), "Potongan", "red", "h2")
d.line((678, 870, 1114, 870), fill=COLORS["line"], width=2)
row(d, 678, 924, "Alpha weekday + weekend", "Rp 400.000")
row(d, 678, 980, "Telat + pulang awal", "Rp 75.000")
row(d, 678, 1036, "Lain + hari kerja", "Rp 505.385")
d.rounded_rectangle((678, 1076, 1114, 1136), radius=12, fill=COLORS["red_bg"])
text(d, (702, 1115), "Total Potongan", "red", "body_b")
text(d, (1094, 1115), "Rp 980.385", "red", "body_b", anchor="ra")

arrow(d, (344, 1174), (610, 1234))
arrow(d, (896, 1174), (630, 1234))

d.rounded_rectangle((244, 1254, 996, 1438), radius=28, fill=COLORS["dark"])
text(d, (620, 1314), "Gaji Final / Take Home Pay", "white", "h2", anchor="mm")
text(d, (620, 1382), "Rp 2.514.615", "white", "title", anchor="mm")
text(d, (620, 1420), "Income dikurangi semua potongan. THP negatif ditampilkan minimal Rp 0.", "white", "small", anchor="mm")

d.rounded_rectangle((94, 1486, 1146, 1660), radius=20, fill=COLORS["amber_bg"], outline=(236, 209, 157), width=2)
text(d, (126, 1538), "Catatan Error yang Perlu Diperbaiki", "amber", "body_b")
text(d, (126, 1586), "Kode sekarang menghitung salary_debt setelah THP dipaksa 0.", "ink", "small")
text(d, (126, 1626), "Solusi: hitung THP mentah dulu, simpan debt jika negatif, baru tampilkan THP final minimal Rp 0.", "ink", "small")

text(d, (94, 1688), "Simulasi edukasi. Tidak menyimpan database. Berdasarkan alur Payroll.php dan Presence_model.php.", "muted", "small")

img.save(OUT, quality=95)
print(OUT)
