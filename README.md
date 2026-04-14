<div align="center">

🌐 JAGAT ARSY (JGT) BLOCKCHAIN CORE

Sovereign Layer-1 Blockchain Ecosystem built on Hybrid PHP-Python Architecture.

Memajukan Desentralisasi. Mengembalikan Kepemilikan Data.

Website Resmi • Whitepaper • Explorer • Komunitas

</div>

📖 Tentang Jagat Arsy

Jagat Arsy (JGT) adalah proyek blockchain Layer-1 berdaulat (Sovereign) yang dirancang dari nol untuk mendemokratisasi ekosistem penambangan kripto. Dibangun dengan pendekatan hibrida yang unik (PHP untuk Master Node Validator dan Python untuk Miner Client), Jagat Arsy membuktikan bahwa jaringan terdesentralisasi dapat berjalan dengan cepat, ringan, dan sangat aman tanpa memerlukan infrastruktur yang membebani.

Jagat Arsy memperkenalkan terobosan Tokenomics "80/20 Fee Split", di mana biaya jaringan (Gas Fee) tidak dimonopoli oleh penemu blok, melainkan dibagikan merata kepada seluruh penambang yang aktif menjaga jaringan.

✨ Fitur Utama

🛡️ Military-Grade Security (v11.4): Menggunakan kriptografi Ed25519 murni di sisi klien dengan verifikasi ketat via libsodium di sisi server. Mendukung integrasi FIDO2/WebAuthn (Biometrik).

⚖️ Dynamic Difficulty Adjustment (DDA): Tingkat kesulitan penambangan (Proof-of-Work) menyesuaikan diri secara otomatis setiap 10 blok untuk menjaga target waktu pencetakan blok.

⛏️ Demokratisasi Mining: Klien penambangan super ringan. Bisa dilakukan melalui Browser Web (JavaScript) maupun Dedicated PC Miner (Python/Executable).

🔄 Auto-Discovery P2P: Fitur otomatis yang mendaftarkan Node baru ke dalam jaringan global saat first-boot.

📦 Built-in DApps Ecosystem: Dilengkapi dengan Web Wallet, NFT Marketplace, Block Explorer, dan P2P Fiat Exchange terintegrasi.

🪙 Tokenomics

Sistem ekonomi Jagat Arsy mengadopsi standar deflasi ketat menyerupai Bitcoin:

Ticker / Simbol: JGT

Maximum Supply: 21.000.000 JGT

Block Reward (Awal): 50 JGT

Siklus Halving: Setiap 210.000 Blok

Gas Fee Base: 0.01 JGT (Dinamic berdasarkan Smart Contract Payload)

🚀 Memulai (Getting Started)

1. Menjalankan Server Node (Validator P2P)

Bantu amankan jaringan dengan menjalankan Master Node Anda sendiri. Anda hanya membutuhkan Hosting atau VPS dasar (Linux/Windows).

Persyaratan: PHP 8.x, Ekstensi libsodium & pdo_mysql, Database MySQL/MariaDB.

Instalasi:

Unduh isi dari folder /core-node/.

Unggah index.php dan config.php ke public_html server Anda.

Sesuaikan detail database di config.php.

Akses URL Node Anda melalui browser. Sistem akan melakukan migrasi database dan registrasi ke jaringan P2P secara otomatis!

2. Menjalankan PC Miner (Penambang JGT)

Manfaatkan CPU menganggur Anda untuk mendulang JGT.

Unduh Binari: Buka tab Releases dan unduh mining.exe (Windows).

Via Source Code (Python):

git clone [https://github.com/username/Jagat-Arsy-Core.git](https://github.com/username/Jagat-Arsy-Core.git)
cd Jagat-Arsy-Core/miner-client
pip install requests
python jagat_miner_pc.py


🔒 Laporan Keamanan Terkini (v11.4 Ironclad)

Kami menanggapi keamanan dengan sangat serius. Pembaruan v11.4 telah menambal berbagai celah tingkat tinggi (Audit by Independent Security Researchers):

[PATCHED] Bypass Signature / Trust, Don't Verify Exploit.

[PATCHED] Double-Spending intra-block (Transaksional Database).

[PATCHED] Longest Chain Attack (Validasi kriptografi ketat saat P2P Sync).

[IMPLEMENTED] Mempool Garbage Collection & Anti-Spam Rate Limiting (Max 2000 TX).

Menemukan bug keamanan? Mohon jangan buka Issue publik. Kirimkan laporan ke email: security@jagat-arsy.org

🤝 Kontribusi (Contributing)

Proyek ini adalah milik komunitas. Kami sangat menyambut Pull Requests (PR) untuk:

Peningkatan performa kode (Optimasi Query MySQL, Refactoring PHP/Python).

Migrasi Core Backend menggunakan Golang / Rust (Jagat Arsy v2.0).

Desain UI/UX untuk DApps ekosistem.

Terjemahan dokumentasi ke berbagai bahasa.

Silakan baca Panduan Kontribusi sebelum memulai.

📄 Lisensi

Jagat Arsy Core dirilis di bawah MIT License. Anda bebas untuk menggunakan, memodifikasi, dan mendistribusikan perangkat lunak ini secara komersial maupun privat.

<div align="center">
<b>© 2026 Jagat Arsy Foundation</b>




<i>"Code is Law. Decentralization is Freedom."</i>
</div>
