<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_required_tables_exist(): void
    {
        $tables = [
            'users',
            'personal_access_tokens',
            'entries',
            'quests',
            'characters',
            'quotes',
            'entry_quests',
            'entry_characters',
            'entry_attachments',
            'entry_audio',
            'entry_quest_tombstones',
            'entry_character_tombstones',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} should exist");
        }
    }

    public function test_users_table_has_uuid_primary_key_and_oauth_columns(): void
    {
        $this->assertSame('uuid', Schema::getColumnType('users', 'id'));
        $this->assertTrue(Schema::hasColumns('users', ['email', 'password', 'apple_id', 'google_id', 'email_verified_at']));
        $this->assertFalse(Schema::hasColumn('users', 'name'));
    }
}
