<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('api-inspector.database.connection');
        $schema = Schema::connection($connection);

        $requestsTable = config('api-inspector.database.requests_table', 'api_inspector_requests');
        $replaysTable = config('api-inspector.database.replays_table', 'api_inspector_replays');
        $settingsTable = config('api-inspector.database.settings_table', 'api_inspector_settings');

        if (! $schema->hasTable($requestsTable)) {
            $schema->create($requestsTable, function (Blueprint $table): void {
                $table->ulid('id')->primary();

                $table->string('method', 10)->index();
                $table->string('scheme', 12)->nullable();
                $table->string('host', 255)->nullable();
                $table->string('path', 512)->index();
                $table->text('uri')->nullable();
                $table->string('route_name', 255)->nullable()->index();
                $table->text('controller_action')->nullable();
                $table->json('route_middleware')->nullable();

                $table->unsignedSmallInteger('status_code')->nullable()->index();
                $table->string('status_text', 64)->nullable();
                $table->float('duration_ms')->nullable()->index();
                $table->unsignedBigInteger('memory_bytes')->nullable();
                $table->unsignedInteger('request_size')->nullable();
                $table->unsignedInteger('response_size')->nullable();

                $table->string('ip', 45)->nullable()->index();
                $table->text('user_agent')->nullable();
                $table->text('referer')->nullable();

                $table->string('user_id', 64)->nullable()->index();
                $table->string('user_type', 255)->nullable();
                $table->string('user_label', 255)->nullable();
                $table->string('session_id', 255)->nullable();

                $table->json('request_headers')->nullable();
                $table->json('query')->nullable();
                $table->longText('request_payload')->nullable();
                $table->string('payload_type', 20)->nullable();
                $table->boolean('payload_truncated')->default(false);

                $table->json('response_headers')->nullable();
                $table->longText('response_body')->nullable();
                $table->boolean('response_truncated')->default(false);
                $table->boolean('response_binary')->default(false);

                $table->text('exception_class')->nullable();
                $table->text('exception_message')->nullable();
                $table->longText('exception_trace')->nullable();

                $table->boolean('is_replay')->default(false)->index();
                $table->string('replayed_from_id', 26)->nullable()->index();
                $table->unsignedInteger('replay_count')->default(0);
                $table->timestamp('last_replayed_at')->nullable();

                $table->timestamp('captured_at')->index();
                $table->timestamps();

                $table->index(['captured_at', 'status_code']);
                $table->index(['method', 'captured_at']);
            });
        }

        if (! $schema->hasTable($replaysTable)) {
            $schema->create($replaysTable, function (Blueprint $table) use ($requestsTable): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('api_request_id')
                    ->nullable()
                    ->constrained($requestsTable)
                    ->nullOnDelete();

                $table->string('mode', 20)->index();
                $table->string('method', 10);
                $table->text('uri');
                $table->string('host', 255)->nullable();

                $table->json('request_headers')->nullable();
                $table->longText('request_payload')->nullable();
                $table->string('payload_type', 20)->nullable();

                $table->unsignedSmallInteger('status_code')->nullable()->index();
                $table->float('duration_ms')->nullable();
                $table->json('response_headers')->nullable();
                $table->longText('response_body')->nullable();
                $table->boolean('response_truncated')->default(false);

                $table->json('plan')->nullable();
                $table->text('error')->nullable();
                $table->string('user_id', 64)->nullable();
                $table->string('user_label', 255)->nullable();
                $table->boolean('rolled_back')->default(false);

                $table->timestamps();
                $table->index(['created_at', 'mode']);
            });
        }

        if (! $schema->hasTable($settingsTable)) {
            $schema->create($settingsTable, function (Blueprint $table): void {
                $table->string('key', 100)->primary();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $connection = config('api-inspector.database.connection');
        $schema = Schema::connection($connection);

        $schema->dropIfExists(config('api-inspector.database.replays_table', 'api_inspector_replays'));
        $schema->dropIfExists(config('api-inspector.database.requests_table', 'api_inspector_requests'));
        $schema->dropIfExists(config('api-inspector.database.settings_table', 'api_inspector_settings'));
    }
};
