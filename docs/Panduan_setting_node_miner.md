⛏️ Panduan Penambangan (Mining) & Node Komunitas Jagat Arsy (JGT)

Selamat datang di panduan resmi penambangan dan desentralisasi koin Jagat Arsy (JGT). Anda dapat berkontribusi pada ekosistem JGT dengan dua cara utama: Menjadi Penambang (Miner) untuk memecahkan blok dan mendapatkan hadiah, atau Menjalankan Node Komunitas untuk mengamankan riwayat transaksi jaringan.

BAGIAN A: MENJALANKAN PC MINER

JGT menggunakan algoritma konsensus Proof-of-Work (PoW) dengan sistem pembagian hasil (Gas Fee Split) 80/20, yang berarti setiap penambang yang aktif akan mendapatkan imbalan, baik yang menemukan blok maupun yang sekadar berpartisipasi menjaga jaringan. Aplikasi dirancang ringan agar tidak membuat perangkat cepat panas.

📥 1. Unduh Aplikasi Miner (JGT PC Miner)

Aplikasi JGT PC Miner dikompilasi secara khusus untuk pengguna Windows (64-bit). Aplikasi ini tidak memerlukan instalasi yang rumit, cukup unduh dan langsung jalankan.

👉 Klik di Sini untuk Mengunduh mining.exe

(Catatan: Pastikan Anda selalu mengunduh dari sumber resmi GitHub Jagat Arsy Core untuk menghindari perangkat lunak palsu).

🛠️ 2. Persiapan Sebelum Menambang

Sebelum menjalankan aplikasi miner, pastikan Anda sudah memiliki alamat dompet (Wallet) JGT untuk menampung hasil penambangan Anda.

Buka KCLN Secure Wallet di peramban web Anda.

Masuk menggunakan Frasa Rahasia (12 kata) atau buat dompet baru jika belum punya.

Salin Alamat Publik Anda (bermula dengan jgt1q...).

🚀 3. Cara Menjalankan PC Miner

Ikuti langkah-langkah mudah berikut untuk mulai menambang:

Buka Aplikasi: Klik ganda (Double-Click) file mining.exe yang sudah Anda unduh.

Tips Keamanan Windows: Jika muncul peringatan layar biru "Windows protected your PC" (Windows SmartScreen), klik tulisan "More info", lalu klik tombol "Run anyway". Peringatan ini wajar terjadi pada aplikasi kripto Open Source yang baru dirilis.

Masukkan Alamat Dompet: Saat layar terminal hitam terbuka, sistem akan meminta Anda memasukkan alamat dompet. Tempel (Paste) alamat jgt1q... yang sudah Anda salin sebelumnya, lalu tekan Enter.

Otomatisasi: Alamat dompet Anda akan disimpan secara otomatis di dalam file jagat_wallet_config.txt. Anda tidak perlu mengetiknya berulang kali saat membuka aplikasi di kemudian hari.

Mulai Menambang: Mesin akan otomatis menghubungi jaringan utama (Master Node) untuk mengambil pekerjaan (Job). Biarkan terminal tetap terbuka di latar belakang.

Selama aplikasi berjalan dan Anda melihat tulisan "hashing berjalan...", komputer Anda sedang bekerja keras memecahkan teka-teki kriptografi untuk mengamankan jaringan Jagat Arsy!

💰 4. Skema Hadiah (Rewards)

Di Jagat Arsy, Anda tidak akan pernah bekerja sia-sia. Jaringan sangat menghargai kontribusi daya komputasi Anda melalui skema berikut:

🏆 Menemukan Blok (Block Finder): Jika PC Anda berhasil memecahkan Hash blok (BINGO!), Anda akan mendapatkan 50 JGT (Block Reward Utama) ditambah 80% dari total Biaya Jaringan (Gas Fee) transaksi di blok tersebut.

🤝 Partisipasi (Participation Reward): Selama Anda membiarkan mining.exe berjalan dan terhubung ke jaringan (aktif dalam 5 menit terakhir), Anda akan mendapatkan porsi dari 20% sisa Gas Fee yang dibagikan secara merata kepada seluruh penambang di dunia setiap kali ada blok baru yang ditemukan.

BAGIAN B: MENJALANKAN NODE KOMUNITAS (LOCAL FULL NODE)

Selain menambang, Anda juga dapat membantu mengamankan jaringan Jagat Arsy dari manipulasi dan peretasan dengan menjalankan Local Full Node.

Berbeda dengan Miner yang bertugas mencari teka-teki kriptografi, Local Full Node bertugas menyimpan buku besar (Ledger) dan memverifikasi keabsahan setiap transaksi secara independen.

📥 1. Unduh Aplikasi Local Node

Aplikasi ini berjalan senyap di latar belakang dan menyediakan gerbang API lokal untuk aplikasi DApps (seperti Wallet) Anda.

👉 Klik di Sini untuk Mengunduh node_master.exe

🚀 2. Cara Menjalankan Local Full Node

Buka Aplikasi: Letakkan file node_master.exe di dalam folder khusus (karena aplikasi ini akan membuat file database secara otomatis). Klik ganda untuk membukanya.

Sinkronisasi (Syncing): Saat pertama kali dijalankan, layar terminal akan menampilkan proses sinkronisasi. Node Anda sedang mengunduh seluruh riwayat ribuan blok dari awal jaringan berdiri. Proses ini mungkin memakan waktu beberapa menit tergantung kecepatan internet Anda.

Standby: Setelah tulisan Sync Selesai muncul, node akan masuk ke mode Standby. Node Anda akan memeriksa blok baru setiap 15 detik.

API Lokal Aktif: Node Anda kini telah menyediakan jalur pribadi di http://localhost:5050. Jika Anda membuka KCLN Wallet atau Explorer dari komputer tersebut, aplikasi web dapat mengambil data secara langsung dari database PC Anda secara super cepat dan rahasia!

Biarkan aplikasi ini tetap terbuka agar Anda resmi berpartisipasi menjaga Desentralisasi Sejati Jagat Arsy! 🛡️🌐