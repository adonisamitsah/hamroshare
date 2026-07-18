-- CREATE TABLE sqlite_sequence(name,seq);
CREATE TABLE IF NOT EXISTS "constant" (
	"id"	INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	"key"	TEXT,
	"value"	TEXT
);
CREATE TABLE IF NOT EXISTS "ipo_result" (
	"scrip"	TEXT,
	"dmat_num"	INTEGER,
	"log"	TEXT
);
CREATE TABLE ipo_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    dmat_num TEXT,
    scrip TEXT,
    companyName TEXT,
    companyShareId INTEGER,
    applicantFormId INTEGER UNIQUE, -- Unique to prevent duplicate entries
    statusName TEXT DEFAULT 'Never Checked',
    receivedKitta INTEGER DEFAULT 0,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE system_crons (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cron_key TEXT UNIQUE NOT NULL,      -- Unique identifier (e.g., ipo_scanner)
    display_name TEXT NOT NULL,         -- Human readable name
    description TEXT,                   -- What the job does
    status TEXT DEFAULT 'enabled',      -- 'enabled' or 'disabled'
    frequency_minutes INTEGER NOT NULL, -- Intended interval
    last_run_at DATETIME,               -- Timestamp of last execution
    last_status TEXT,                   -- 'SUCCESS' or 'FAILED'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS "system_logs" (
	"id"	INTEGER,
	"user_id"	TEXT,
	"log_type"	TEXT,
	"status"	TEXT,
	"step"	TEXT,
	"message"	TEXT,
	"created_at"	DATETIME,
	"is_notified"	INTEGER DEFAULT 0,
	PRIMARY KEY("id" AUTOINCREMENT)
);
CREATE TABLE IF NOT EXISTS "users" (
	"id"	INTEGER NOT NULL,
	"clientId"	TEXT NOT NULL,
	"username"	NUMERIC NOT NULL,
	"password"	TEXT NOT NULL,
	"dmat_num"	TEXT NOT NULL,
	"name"	TEXT NOT NULL,
	"Authorization"	TEXT,
	"accountNumber"	TEXT,
	"customerId"	TEXT,
	"accountBranchId"	TEXT,
	"crnNumber"	TEXT,
	"transactionPIN"	TEXT,
	"bankId"	TEXT,
	"bankName"	TEXT,
	"dpName"	TEXT,
	"lastLogin"	TEXT,
	"lastStatusLog"	TEXT,
	"lastStatusLogTime"	TEXT,
	"myshare"	TEXT,
	"myshare_time"	TEXT,
	"pl_json"	TEXT,
	"pl_log"	TEXT,
	"captchaIdentifier"	TEXT,
	"captcha_base64"	TEXT,
	"captcha_solved"	TEXT,
	"accountTypeId"	TEXT,
	"ownDetails"	TEXT,
	"last_updated_owndetails"	DATETIME,
	"bankPosition"	INTEGER DEFAULT 0, profit_dist_split_para TEXT DEFAULT '{"manager_pct": 80.00, "client_pct": 20.00, "agent_pct": 0.00}',
	PRIMARY KEY("id" AUTOINCREMENT)
);
CREATE TABLE ledgers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    dmat_num TEXT NOT NULL,         -- Links to the active profile's DEMAT
    date TEXT NOT NULL,
    particular TEXT NOT NULL,
    deposit_amt REAL DEFAULT 0.00,
    withdraw_amt REAL DEFAULT 0.00,
    balance REAL NOT NULL,
    
    -- Active commission split allocations for this transaction period
    manager_pct REAL DEFAULT 80.00, -- Amit's cut configuration
    client_pct REAL DEFAULT 20.00,  -- Client's cut configuration
    agent_pct REAL DEFAULT 0.00,    -- Dinesh's cut configuration
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE profit_distributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    dmat_num TEXT NOT NULL,
    date TEXT NOT NULL,
    scrip_name TEXT NOT NULL,
    invest_amt REAL NOT NULL,
    net_receivable REAL NOT NULL,
    
    -- Frozen historical splits snapshot (immutable once written)
    manager_pct REAL NOT NULL,
    client_pct REAL NOT NULL,
    agent_pct REAL NOT NULL,
    
    manager_profit REAL NOT NULL,
    client_profit REAL NOT NULL,
    agent_profit REAL NOT NULL DEFAULT 0.00,
    
    status TEXT DEFAULT 'W&D',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
