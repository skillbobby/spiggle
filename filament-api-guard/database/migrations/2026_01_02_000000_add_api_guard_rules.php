<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('api-guard.database.connection');
        $schema = Schema::connection($connection);
        $requestsTable = config('api-guard.database.requests_table', 'api_guard_requests');
        $rulesTable = config('api-guard.database.rules_table', 'api_guard_rules');

        if ($schema->hasTable($requestsTable)) {
            $schema->table($requestsTable, function (Blueprint $table) use ($schema, $requestsTable): void {
                if (! $schema->hasColumn($requestsTable, 'guard_action')) {
                    $table->string('guard_action', 20)->nullable()->index()->after('status_text');
                }

                if (! $schema->hasColumn($requestsTable, 'guard_rule_id')) {
                    $table->string('guard_rule_id', 26)->nullable()->index()->after('guard_action');
                }

                if (! $schema->hasColumn($requestsTable, 'guard_rule_name')) {
                    $table->string('guard_rule_name', 255)->nullable()->after('guard_rule_id');
                }
            });
        }

        if (! $schema->hasTable($rulesTable)) {
            $schema->create($rulesTable, function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('type', 20)->index();
                $table->boolean('enabled')->default(true)->index();
                $table->integer('priority')->default(0)->index();

                $table->string('path', 512)->nullable();
                $table->json('methods')->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('user_id', 64)->nullable();

                $table->unsignedInteger('throttle_limit')->nullable();
                $table->unsignedInteger('throttle_window')->default(60);
                $table->string('throttle_key', 20)->default('ip');

                $table->unsignedSmallInteger('response_status')->nullable();
                $table->text('response_message')->nullable();

                $table->unsignedBigInteger('hit_count')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->timestamps();

                $table->index(['enabled', 'priority']);
            });
        }
    }

    public function down(): void
    {
        $connection = config('api-guard.database.connection');
        $schema = Schema::connection($connection);
        $requestsTable = config('api-guard.database.requests_table', 'api_guard_requests');
        $rulesTable = config('api-guard.database.rules_table', 'api_guard_rules');

        $schema->dropIfExists($rulesTable);

        if ($schema->hasTable($requestsTable)) {
            $schema->table($requestsTable, function (Blueprint $table) use ($schema, $requestsTable): void {
                foreach (['guard_action', 'guard_rule_id', 'guard_rule_name'] as $column) {
                    if ($schema->hasColumn($requestsTable, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
