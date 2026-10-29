<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL DDL is not transactional: users may remain after a later
        // CREATE TABLE failed. Preserve a complete base table on retry.
        if (Schema::hasTable('users')) {
            if (!Schema::hasColumns('users', [
                'id', 'name', 'email', 'email_verified_at', 'password',
                'remember_token', 'created_at', 'updated_at',
            ])) {
                throw new RuntimeException('The existing users table is incomplete. Review its schema before retrying; no data was removed.');
            }
            if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $tableName = DB::connection()->getTablePrefix() . 'users';
                $engine = DB::selectOne(
                    'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$tableName]
                );
                if (strcasecmp((string) ($engine->engine ?? ''), 'InnoDB') !== 0) {
                    $quotedTable = '`' . str_replace('`', '``', $tableName) . '`';
                    DB::statement('ALTER TABLE ' . $quotedTable . ' ENGINE=InnoDB');
                }
            }
        } else {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
