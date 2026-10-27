<?php

declare(strict_types=1);

namespace Spiggle\ApiInspector\Tests;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;
use Spiggle\ApiInspector\ApiInspectorServiceProvider;
use Spiggle\ApiInspector\Tests\Fixtures\AdminPanelProvider;
use Spiggle\ApiInspector\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    protected User $user;

    protected function getPackageProviders($app): array
    {
        return [
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            \Filament\QueryBuilder\QueryBuilderServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
            ApiInspectorServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('api-inspector.authorization.emails', []);
        $app['config']->set('api-inspector.register_global_middleware', true);
        $app['config']->set('api-inspector.enabled', true);
        $app['config']->set('api-inspector.live.interval', 5);
        $app['config']->set('api-inspector.replay.allowed_hosts', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--force' => true])->run();

        $this->user = User::query()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => bcrypt('secret'),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('api')->group(function (): void {
            Route::get('/api/orders', fn () => response()->json([
                'data' => [
                    ['id' => 1, 'reference' => 'ORD-1', 'total' => 1299],
                    ['id' => 2, 'reference' => 'ORD-2', 'total' => 2499],
                ],
                'meta' => ['count' => 2],
            ]));

            Route::post('/api/orders', fn (Request $request) => response()->json([
                'id' => 99,
                'received' => $request->all(),
                'content_type' => $request->header('Content-Type'),
            ], 201));

            Route::get('/api/orders/{order}', fn (string $order) => response()->json([
                'id' => (int) $order,
                'status' => 'paid',
            ]));

            Route::delete('/api/orders/{order}', fn (string $order) => response()->json([
                'deleted' => (int) $order,
            ]));

            Route::get('/api/boom', fn () => response()->json([
                'message' => 'Server exploded',
            ], 500));

            Route::get('/api/explode', function (): void {
                throw new \RuntimeException('Kaboom from the test route');
            });

            Route::get('/api/slow', function () {
                usleep(25_000);

                return response()->json(['ok' => true]);
            });

            Route::get('/api/plain', fn () => response('hello from the api', 200, [
                'Content-Type' => 'text/plain',
            ]));

            Route::get('/api/binary', fn () => response("\x00\x01\x02binary", 200, [
                'Content-Type' => 'application/octet-stream',
            ]));

            Route::get('/api/redirect', fn () => redirect('/api/orders'));

            Route::get('/api/ip', fn (Request $request) => response()->json([
                'ip' => $request->ip(),
            ]));

            Route::get('/api/headers', fn (Request $request) => response()->json([
                'authorization' => $request->header('Authorization'),
                'accept' => $request->header('Accept'),
            ]));

            Route::post('/api/echo', fn (Request $request) => response()->json([
                'json' => $request->json()->all(),
                'input' => $request->all(),
                'method' => $request->method(),
            ]));

            Route::post('/api/probes', function (Request $request) {
                DB::table('replay_probes')->insert([
                    'note' => (string) $request->input('note', 'created'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return response()->json([
                    'created' => true,
                    'note' => $request->input('note'),
                ], 201);
            });
        });

        Route::middleware(['api', 'auth'])->get('/api/me', fn (Request $request) => response()->json([
            'id' => $request->user()?->getAuthIdentifier(),
            'email' => $request->user()?->email,
        ]));
    }
}
