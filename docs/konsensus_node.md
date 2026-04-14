🌐 Panduan Desentralisasi & Konsensus Node JGT

Dokumen ini menjelaskan bagaimana arsitektur terdesentralisasi Jagat Arsy (JGT) bekerja, cara komunitas dapat berpartisipasi dengan menjalankan Master Node independen, dan bagaimana mekanisme konsensus melindungi jaringan dari berbagai bentuk manipulasi.

Agar Jagat Arsy (JGT) menjadi blockchain yang 100% terdesentralisasi dan trustless (tanpa perlu percaya pada satu pihak terpusat), sistem jaringan dan validasi transaksi diserahkan sepenuhnya kepada komunitas.

🛠️ 1. Menjalankan Node Komunitas Independen

Perangkat lunak inti jaringan (Core Node) JGT bersifat Open Source (Kode Terbuka). Setiap individu atau organisasi di seluruh dunia diundang untuk menjadi bagian dari tulang punggung jaringan.

Langkah Siklus Hidup Node Komunitas:

Inisiasi: Seorang pengguna (Sebut saja: Alice) menyewa Hosting, VPS, atau menyiapkan Local Server (misal: node-alice.com).

Pemasangan: Alice mengunduh file rilis resmi index.php (Core) dan config.php ke servernya.

Sinkronisasi Awal (Sync): Saat Node diaktifkan, Node Alice akan menghubungi Seed Node di jaringan dan menjalankan perintah ?action=sync.

Validasi Historis: Server Alice akan mengunduh seluruh blok riwayat transaksi (Full Ledger) dari Genesis Block hingga blok terkini. Selama proses ini, Node Alice tidak sekadar menyalin, melainkan memvalidasi ulang setiap hash dan tanda tangan Ed25519 di setiap transaksi secara mandiri.

Berdaulat: Setelah sinkronisasi selesai, Node Alice resmi menjadi Validator Independen di jaringan Jagat Arsy.

🛡️ 2. Mekanisme Konsensus: Mencegah Aktor Jahat

Kekuatan utama blockchain terletak pada kemampuannya menolak data yang dimanipulasi melalui konsensus (kesepakatan mayoritas).

Skenario Serangan (Eksploitasi Sistem):
Misalkan seorang peretas (atau bahkan Developer asli yang bertindak curang) memodifikasi kode di servernya sendiri agar Block Reward (Hadiah Penambangan) menjadi 500 JGT (padahal aturan protokol menetapkan 50 JGT). Peretas tersebut menambang blok baru dan menyiarkannya ke jaringan.

Mekanisme Pertahanan Konsensus:

Penerimaan Blok: Server peretas menyiarkan blok palsu tersebut ke Node Alice dan node-node komunitas lainnya.

Inspeksi Aturan: Node Alice membedah blok tersebut dan mencocokkannya dengan source code protokol JGT murni miliknya.

Deteksi Anomali: Node Alice mendeteksi adanya transaksi pencetakan uang (minting) sebesar 500 JGT yang menyalahi batas Reward di dalam kode.

Penolakan Mutlak (Invalidation): Node Alice (dan seluruh Node jujur lainnya) akan MENOLAK Keras blok tersebut.

Hasil Akhir: Karena blok peretas ditolak oleh mayoritas jaringan, node milik peretas tersebut akan terisolasi dari rantai utama (Hard Forked). Uang 500 JGT palsu yang dicetak hanya akan valid di server peretas itu sendiri, dan tidak akan diakui oleh ekosistem (Wallet, Explorer, maupun Marketplace resmi).

🔗 3. Komunikasi P2P & Auto-Discovery

Sistem Jagat Arsy menggunakan arsitektur jaringan P2P asinkron dengan fitur penemuan otomatis (Auto-Discovery).

Tabel Peers: Setiap Node menyimpan daftar URL dari node lain yang aktif di dalam tabel database lokal (peers).

Registrasi Otomatis: Saat node baru dihidupkan, node tersebut dapat mendaftarkan diri (?action=register_node). Node penerima akan melakukan Ping balik. Jika node baru terbukti sah dan memiliki tinggi blok yang relevan, URL-nya akan disebarkan ke seluruh jaringan.

Desentralisasi Penuh: Aplikasi peramban (Wallet/Miner) milik pengguna biasa tidak harus terhubung ke satu server utama. Pengguna dapat memilih untuk terhubung ke Node Alice, Node Bob, atau menjalankan Node Lokal (Python/Go) di komputer mereka sendiri.

Selama 51% dari total Node di seluruh dunia menjalankan perangkat lunak JGT yang mematuhi aturan asli, maka jaringan Jagat Arsy tidak akan pernah bisa diretas, diubah aturannya secara paksa, atau dimatikan oleh entitas mana pun. Inilah esensi dari Konsensus Terdesentralisasi.