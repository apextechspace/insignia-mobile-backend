<?php

namespace Tests\Feature;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class InitialMigrationRecoveryTest extends TestCase
{
    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/0001_01_01_000000_create_users_table.php');
    }

    public function test_initial_migration_can_finish_after_users_table_was_created_without_losing_data(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        DB::table('users')->insert(['name' => 'Existing guest', 'email' => 'existing@example.test', 'password' => 'existing-hash']);
        $this->migration()->up();
        $this->migration()->up();

        $this->assertDatabaseHas('users', ['email' => 'existing@example.test', 'password' => 'existing-hash']);
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
        $this->assertTrue(Schema::hasTable('sessions'));
    }

    public function test_incomplete_existing_user_table_is_not_silently_adopted_or_deleted(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->id());
        DB::table('users')->insert(['id' => 42]);
        try {
            $this->migration()->up();
            $this->fail('Expected incomplete-schema validation');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existing users table is incomplete', $exception->getMessage());
        }
        $this->assertDatabaseHas('users', ['id' => 42]);
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    public function test_mysql_table_creation_explicitly_uses_innodb_instead_of_the_host_default(): void
    {
        foreach (['mysql', 'mariadb'] as $driver) {
            $settings = config('database.connections.' . $driver);
            $this->assertSame('InnoDB', $settings['engine']);
            $connection = new MySqlConnection(function () {
                throw new RuntimeException('SQL compilation must not connect to a real server.');
            }, 'migration_test', '', $settings);
            $connection->useDefaultSchemaGrammar();
            $blueprint = new Blueprint('password_reset_tokens', function (Blueprint $table): void {
                $table->create();
                $table->string('email')->primary();
                $table->string('token');
            });
            $sql = implode('; ', $blueprint->toSql($connection, $connection->getSchemaGrammar()));
            $this->assertMatchesRegularExpression('/engine\s*=\s*InnoDB/i', $sql);
            $this->assertStringContainsString('utf8mb4', $sql);
        }
    }
}
