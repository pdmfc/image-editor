<?php

namespace PDMFC\ImageEditor\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use PDMFC\ImageEditor\Services\CameraService;
use PDMFC\ImageEditor\Services\ImageService;
use PDMFC\ImageEditor\Services\QrCodeService;
use PDMFC\ImageEditor\Services\UserPhotoStorage;
use PDMFC\ImageEditor\Support\ActionButtons;

class ImageEditorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/image-editor.php', 'image-editor');
        $this->mergeConfigFrom(__DIR__.'/../Config/image.php', 'image');

        $this->normalizeActionButtonsConfig();

        $this->app->singleton(UserPhotoStorage::class);
        $this->app->singleton(CameraService::class);
        $this->app->singleton(QrCodeService::class);
        $this->app->singleton(ImageService::class);
    }

    public function boot(): void
    {
        $this->registerBroadcastChannels();
        $this->shareInertiaConfig();

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'image-editor');

        $this->publishes([
            __DIR__.'/../Config/image-editor.php' => config_path('image-editor.php'),
        ], 'image-editor-config');

        $this->publishes([
            __DIR__.'/../stubs/echo-bootstrap.js' => base_path('stubs/image-editor-echo-bootstrap.js'),
        ], 'image-editor-echo');

        $this->publishes([
            __DIR__.'/../stubs/routes-demo.php' => base_path('routes/image-editor-demo.php'),
        ], 'image-editor-demo-routes');

        if (config('image-editor.demo_routes', false)) {
            Route::middleware('web')->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../Routes/demo.php');
            });
        }

        Route::prefix('api')->middleware('api')->group(function () {
            $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        });
    }

    protected function normalizeActionButtonsConfig(): void
    {
        $value = $this->app['config']->get('image-editor.action_buttons');

        if (is_array($value)) {
            return;
        }

        $this->app['config']->set(
            'image-editor.action_buttons',
            ActionButtons::parse(is_string($value) ? $value : null)
        );
    }

    protected function registerBroadcastChannels(): void
    {
        if (! $this->app->runningInConsole() && $this->app->bound('Illuminate\Broadcasting\BroadcastManager')) {
            require __DIR__.'/../Routes/channels.php';
        }
    }

    protected function shareInertiaConfig(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share([
            'imageEditor' => fn (): array => [
                'actionButtons' => config('image-editor.action_buttons', []),
            ],
        ]);
    }
}
