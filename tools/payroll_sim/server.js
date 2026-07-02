import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import mysql from 'mysql2/promise';
import { calculateSalary } from './calculator.js';

const app  = express();
const PORT = process.env.API_PORT || 3011;

app.use(cors());
app.use(express.json());

const db = mysql.createPool({
  host:     process.env.DB_HOST || '127.0.0.1',
  port:     Number(process.env.DB_PORT) || 3306,
  user:     process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'newtiffa_timesheet',
  waitForConnections: true,
  connectionLimit: 5,
});

// Test koneksi saat startup
db.query('SELECT 1')
  .then(() => console.log(`✓ DB connected (${process.env.DB_NAME || 'newtiffa_timesheet'})`))
  .catch(e => console.error('✗ DB connection failed:', e.message));

// GET /api/employees?q=nama_atau_kode
app.get('/api/employees', async (req, res) => {
  const q = (req.query.q || '').trim();
  if (q.length < 2) return res.json([]);
  try {
    const like = `%${q}%`;
    const [rows] = await db.query(`
      SELECT u.id, u.first_name, u.last_name, u.employee_code,
             p.position_name, b.branch_name, u.salary
      FROM users u
      JOIN position p ON p.id = u.position_id
      JOIN branch   b ON b.id = p.branch_id
      WHERE u.active = 1
        AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.employee_code LIKE ?)
      ORDER BY u.first_name LIMIT 20
    `, [like, like, like]);
    res.json(rows.map(r => ({
      id: r.id,
      name: `${r.first_name} ${r.last_name || ''}`.trim(),
      code: r.employee_code,
      position: r.position_name,
      branch: r.branch_name,
      salary: r.salary,
    })));
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// POST /api/salary
app.post('/api/salary', async (req, res) => {
  const { employeeId, month, year, customItems = [] } = req.body;
  if (!employeeId || !month || !year) {
    return res.status(400).json({ error: 'employeeId, month, year required' });
  }
  try {
    const result = await calculateSalary(db, employeeId, Number(month), Number(year), customItems);
    res.json(result);
  } catch (e) {
    console.error('salary calc error:', e);
    res.status(500).json({ error: e.message });
  }
});

// GET /api/insentif?branch_id=1
app.get('/api/insentif', async (req, res) => {
  try {
    const where = req.query.branch_id ? "WHERE branch_id = ? AND is_active = '1'" : "WHERE is_active = '1'";
    const params = req.query.branch_id ? [req.query.branch_id] : [];
    const [rows] = await db.query(
      `SELECT i.id, i.insentif_name AS name, i.formula, i.nominal, b.branch_name
       FROM insentif i JOIN branch b ON b.id = i.branch_id ${where} ORDER BY branch_name, name`,
      params
    );
    res.json(rows);
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// GET /api/deduction?branch_id=1
app.get('/api/deduction', async (req, res) => {
  try {
    const where = req.query.branch_id ? "WHERE branch_id = ? AND is_active = '1'" : "WHERE is_active = '1'";
    const params = req.query.branch_id ? [req.query.branch_id] : [];
    const [rows] = await db.query(
      `SELECT d.id, d.deduction_name AS name, b.branch_name
       FROM deduction d JOIN branch b ON b.id = d.branch_id ${where} ORDER BY branch_name, name`,
      params
    );
    res.json(rows);
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// GET /api/branches
app.get('/api/branches', async (req, res) => {
  try {
    const [rows] = await db.query('SELECT id, branch_name FROM branch WHERE is_active=1 ORDER BY branch_name');
    res.json(rows);
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.listen(PORT, () => console.log(`Payroll Sim API → http://localhost:${PORT}`));
