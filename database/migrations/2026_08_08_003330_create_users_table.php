<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'employee') DEFAULT 'employee',
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    status ENUM('active', 'inactive') DEFAULT 'active',
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default admin account (password: admin123)
INSERT IGNORE INTO users (username, password, full_name, email, phone, role, status) VALUES
('admin', '$2y$12$nlNmKVZzUCrvRyopjC9VmePzIetArCFrKaTA7FIn6OVjzINOiWQ8S', 'System Administrator', 'admin@jftech.com', '09123456789', 'admin', 'active');

SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS users');
    }
};
