<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class TambahWhatsappGateway extends Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS whatsapp_gateway_config (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            session_id CHAR(36) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'DISCONNECTED',
            phone VARCHAR(80) NULL,
            webhook_token CHAR(64) NOT NULL,
            webhook_url VARCHAR(500) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down() { $this->forge->dropTable('whatsapp_gateway_config', true); }
}
