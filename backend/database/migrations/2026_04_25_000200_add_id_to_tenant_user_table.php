<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE tenant_user DROP CONSTRAINT tenant_user_pkey');
        DB::statement('ALTER TABLE tenant_user ADD COLUMN id BIGINT');
        DB::statement('CREATE SEQUENCE tenant_user_id_seq OWNED BY tenant_user.id');
        DB::statement("UPDATE tenant_user SET id = nextval('tenant_user_id_seq')");
        DB::statement("ALTER TABLE tenant_user ALTER COLUMN id SET DEFAULT nextval('tenant_user_id_seq')");
        DB::statement('ALTER TABLE tenant_user ALTER COLUMN id SET NOT NULL');
        DB::statement('ALTER TABLE tenant_user ADD CONSTRAINT tenant_user_pkey PRIMARY KEY (id)');
        DB::statement('ALTER TABLE tenant_user ADD CONSTRAINT tenant_user_tenant_id_user_id_unique UNIQUE (tenant_id, user_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE tenant_user DROP CONSTRAINT tenant_user_tenant_id_user_id_unique');
        DB::statement('ALTER TABLE tenant_user DROP CONSTRAINT tenant_user_pkey');
        DB::statement('ALTER TABLE tenant_user DROP COLUMN id');
        DB::statement('DROP SEQUENCE IF EXISTS tenant_user_id_seq');
        DB::statement('ALTER TABLE tenant_user ADD CONSTRAINT tenant_user_pkey PRIMARY KEY (tenant_id, user_id)');
    }
};
