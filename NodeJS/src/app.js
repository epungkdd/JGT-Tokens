require('dotenv').config();
const express = require('express');
const { pool, initDB } = require('./db');
const Blockchain = require('./blockchain');
const P2PServer = require('./p2p');
const crypto = require('crypto');

const app = express();
app.use(express.json());

const JGTChain = new Blockchain();
const p2p = new P2PServer();

// --- API ENDPOINTS ---

app.get('/', (req, res) => {
    res.json({
        message: 'Jagat Arsy Node.js API',
        version: '12.0.0',
        endpoints: {
            status: 'GET /status',
            send_transaction: 'POST /send_tx',
            submit_block: 'POST /submit_block'
        }
    });
});

app.get('/status', async (req, res) => {
    const [blocks] = await pool.query("SELECT COUNT(*) as count FROM blocks");
    const [mempool] = await pool.query("SELECT COUNT(*) as count FROM mempool");
    
    res.json({
        status: 'active',
        version: '12.0.0 (NodeJS WebSockets)',
        total_blocks: blocks[0].count,
        mempool_size: mempool[0].count,
        difficulty: JGTChain.difficulty
    });
});

app.post('/send_tx', async (req, res) => {
    try {
        const tx = req.body;
        await JGTChain.validateTransaction(tx);
        
        const txid = crypto.createHash('sha256').update(tx.sender + tx.recipient + tx.amount + tx.timestamp + tx.signature + (tx.data||'')).digest('hex');
        
        await pool.query("INSERT INTO mempool VALUES (?, ?, ?, ?, ?, ?, ?, ?)", 
            [txid, tx.sender, tx.recipient, tx.amount, tx.fee || 0.01, tx.timestamp, tx.signature, tx.data || '']);

        // BROADCAST REAL-TIME VIA WEBSOCKETS!
        tx.txid = txid;
        p2p.broadcast('NEW_TRANSACTION', { tx });

        res.json({ status: 'success', txid });
    } catch (error) {
        res.status(400).json({ status: 'error', message: error.message });
    }
});

app.post('/submit_block', async (req, res) => {
    try {
        const { block, miner_address } = req.body;
        const result = await JGTChain.submitBlock(block, miner_address);
        
        // BROADCAST BLOK BARU KE SEMUA MINER!
        p2p.broadcast('NEW_BLOCK', { block });

        res.json(result);
    } catch (error) {
        res.status(400).json({ status: 'error', message: error.message });
    }
});

// --- SERVER START ---
const startServer = async () => {
    await initDB(); // Migrasi tabel otomatis sebelum server nyala
    
    const port = process.env.PORT || 3000;
    const p2pPort = process.env.P2P_PORT || 5050;

    app.listen(port, () => console.log(`[HTTP] REST API menyala di port ${port}`));
    p2p.listen(p2pPort);
};

startServer();require('dotenv').config();
const express = require('express');
const { pool, initDB } = require('./db');
const Blockchain = require('./blockchain');
const P2PServer = require('./p2p');
const crypto = require('crypto');

const app = express();
app.use(express.json());

const JGTChain = new Blockchain();
const p2p = new P2PServer();

// --- API ENDPOINTS ---

app.get('/', (req, res) => {
    res.json({
        message: 'Jagat Arsy Node.js API',
        version: '12.0.0',
        endpoints: {
            status: 'GET /status',
            send_transaction: 'POST /send_tx',
            submit_block: 'POST /submit_block'
        }
    });
});

app.get('/status', async (req, res) => {
    const [blocks] = await pool.query("SELECT COUNT(*) as count FROM blocks");
    const [mempool] = await pool.query("SELECT COUNT(*) as count FROM mempool");
    
    res.json({
        status: 'active',
        version: '12.0.0 (NodeJS WebSockets)',
        total_blocks: blocks[0].count,
        mempool_size: mempool[0].count,
        difficulty: JGTChain.difficulty
    });
});

app.post('/send_tx', async (req, res) => {
    try {
        const tx = req.body;
        await JGTChain.validateTransaction(tx);
        
        const txid = crypto.createHash('sha256').update(tx.sender + tx.recipient + tx.amount + tx.timestamp + tx.signature + (tx.data||'')).digest('hex');
        
        await pool.query("INSERT INTO mempool VALUES (?, ?, ?, ?, ?, ?, ?, ?)", 
            [txid, tx.sender, tx.recipient, tx.amount, tx.fee || 0.01, tx.timestamp, tx.signature, tx.data || '']);

        // BROADCAST REAL-TIME VIA WEBSOCKETS!
        tx.txid = txid;
        p2p.broadcast('NEW_TRANSACTION', { tx });

        res.json({ status: 'success', txid });
    } catch (error) {
        res.status(400).json({ status: 'error', message: error.message });
    }
});

app.post('/submit_block', async (req, res) => {
    try {
        const { block, miner_address } = req.body;
        const result = await JGTChain.submitBlock(block, miner_address);
        
        // BROADCAST BLOK BARU KE SEMUA MINER!
        p2p.broadcast('NEW_BLOCK', { block });

        res.json(result);
    } catch (error) {
        res.status(400).json({ status: 'error', message: error.message });
    }
});

// --- SERVER START ---
const startServer = async () => {
    await initDB(); // Migrasi tabel otomatis sebelum server nyala
    
    const port = process.env.PORT || 3000;
    const p2pPort = process.env.P2P_PORT || 5050;

    app.listen(port, () => console.log(`[HTTP] REST API menyala di port ${port}`));
    p2p.listen(p2pPort);
};

startServer();