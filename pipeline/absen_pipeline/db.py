"""Koneksi DB: absen_ai (lokal VPS) + MySQL produksi read-only via SSH tunnel.

Pola tunnel di-port dari ~/.hermes/scripts/absen_sync.py (paramiko forward),
tanpa satu pun kredensial inline — semua dari .env (lihat config.load_env).
Jalur produksi WAJIB user tifx3722_absen_ro (SELECT-only).
"""
import select
import socket
import threading

import paramiko
import pymysql

from .config import env


class SSHTunnel:
    """Forward port lokal acak -> 127.0.0.1:3306 di host produksi."""

    def __init__(self):
        self.client = None
        self.local_port = None
        self._server_sock = None
        self._threads = []
        self._stop = threading.Event()

    def start(self):
        self.client = paramiko.SSHClient()
        self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        self.client.connect(
            env("ABSEN_SSH_HOST"),
            port=int(env("ABSEN_SSH_PORT", "22")),
            username=env("ABSEN_SSH_USER"),
            key_filename=env("ABSEN_SSH_KEY_PATH"),
            passphrase=env("ABSEN_SSH_KEY_PASSPHRASE") or None,
            timeout=20,
        )
        transport = self.client.get_transport()
        self._server_sock = socket.socket()
        self._server_sock.bind(("127.0.0.1", 0))
        self._server_sock.listen(4)
        self.local_port = self._server_sock.getsockname()[1]
        t = threading.Thread(target=self._accept_loop, args=(transport,), daemon=True)
        t.start()
        self._threads.append(t)
        return self

    def _accept_loop(self, transport):
        while not self._stop.is_set():
            try:
                self._server_sock.settimeout(1.0)
                sock, _ = self._server_sock.accept()
            except socket.timeout:
                continue
            except OSError:
                break
            try:
                chan = transport.open_channel(
                    "direct-tcpip",
                    (env("ABSEN_PROD_DB_HOST", "127.0.0.1"), int(env("ABSEN_PROD_DB_PORT", "3306"))),
                    sock.getsockname(),
                )
            except Exception:
                sock.close()
                continue
            t = threading.Thread(target=self._pipe, args=(sock, chan), daemon=True)
            t.start()
            self._threads.append(t)

    @staticmethod
    def _pipe(sock, chan):
        try:
            while True:
                r, _, _ = select.select([sock, chan], [], [], 30)
                if sock in r:
                    data = sock.recv(16384)
                    if not data:
                        break
                    chan.sendall(data)
                if chan in r:
                    data = chan.recv(16384)
                    if not data:
                        break
                    sock.sendall(data)
        finally:
            sock.close()
            chan.close()

    def stop(self):
        self._stop.set()
        if self._server_sock:
            self._server_sock.close()
        if self.client:
            self.client.close()


def ai_conn():
    """Koneksi DB absen_ai (baca-tulis, lokal VPS)."""
    return pymysql.connect(
        host=env("ABSEN_AI_DB_HOST", "127.0.0.1"),
        port=int(env("ABSEN_AI_DB_PORT", "3306")),
        user=env("ABSEN_AI_DB_USER"),
        password=env("ABSEN_AI_DB_PASS"),
        database=env("ABSEN_AI_DB_NAME", "absen_ai"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
    )


def prod_conn(tunnel):
    """Koneksi produksi READ-ONLY via tunnel yang sudah start()."""
    return pymysql.connect(
        host="127.0.0.1",
        port=tunnel.local_port,
        user=env("ABSEN_PROD_DB_USER"),
        password=env("ABSEN_PROD_DB_PASS"),
        database=env("ABSEN_PROD_DB_NAME"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def replace_period(conn, table, period, rows, columns, extra_where="", extra_args=()):
    """Idempoten: hapus isi periode lalu insert batch. Return jumlah baris."""
    with conn.cursor() as cur:
        cur.execute(f"DELETE FROM {table} WHERE period=%s {extra_where}", (period, *extra_args))
        if rows:
            placeholders = ",".join(["%s"] * len(columns))
            collist = ",".join(columns)
            cur.executemany(
                f"INSERT INTO {table} ({collist}) VALUES ({placeholders})",
                [[r.get(c) for c in columns] for r in rows],
            )
    conn.commit()
    return len(rows)


def log_run(conn, stage, period, branch_id=None):
    """Catat mulai ETL; return closure utk menutup dgn status+detail."""
    import datetime as _dt
    import json as _json

    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO etl_runs (stage, period, branch_id, started_at, status) "
            "VALUES (%s,%s,%s,%s,'running')",
            (stage, period, branch_id, _dt.datetime.now()),
        )
        run_id = cur.lastrowid
    conn.commit()

    def finish(status="ok", detail=None):
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE etl_runs SET finished_at=%s, status=%s, detail=%s WHERE id=%s",
                (_dt.datetime.now(), status, _json.dumps(detail or {}, default=str), run_id),
            )
        conn.commit()

    return finish
