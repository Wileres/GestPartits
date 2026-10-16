<?php

namespace App\Providers;

use App\Domain\Imaging\ScheduleImageRenderer;
use App\Infrastructure\Imaging\ChromiumRenderer;
use App\Infrastructure\Imaging\FakeRenderer;
use App\Infrastructure\Imaging\GdRenderer;
use App\Infrastructure\WhatsApp\LogProvider;
use App\Infrastructure\WhatsApp\ManualProvider;
use App\Infrastructure\WhatsApp\MetaCloudProvider;
use App\Infrastructure\WhatsApp\TwilioProvider;
use App\Infrastructure\WhatsApp\WebGatewayProvider;
use App\Infrastructure\WhatsApp\WhatsAppProviderRegistry;
use App\Mail\PhpMailerTransport;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Proveïdors de WhatsApp disponibles segons el .env. El manual sempre ho és.
        $this->app->singleton(WhatsAppProviderRegistry::class, function (): WhatsAppProviderRegistry {
            $config = config('gestpartits.whatsapp');
            $registry = new WhatsAppProviderRegistry;
            $registry->register(new ManualProvider);

            if ($config['log_enabled']) {
                $registry->register(new LogProvider);
            }
            if ($config['gateway']['enabled']) {
                $registry->register(new WebGatewayProvider($config['gateway']['url'], $config['gateway']['token']));
            }
            if ($config['meta']['enabled']) {
                $registry->register(new MetaCloudProvider($config['meta']['phone_number_id'], $config['meta']['token']));
            }
            if ($config['twilio']['enabled']) {
                $registry->register(new TwilioProvider($config['twilio']['account_sid'], $config['twilio']['auth_token'], $config['twilio']['from']));
            }

            return $registry;
        });

        // Estratègia de generació de les imatges.
        $this->app->bind(ScheduleImageRenderer::class, fn ($app) => match (config('gestpartits.images.renderer')) {
            'gd' => new GdRenderer(config('gestpartits.images.gd_font_regular'), config('gestpartits.images.gd_font_bold')),
            'fake' => $app->make(FakeRenderer::class),
            default => new ChromiumRenderer($app['view'], config('gestpartits.images.chromium_binary')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Mail::extend('phpmailer', fn (array $config) => new PhpMailerTransport($config));

        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    private function configureAuthorization(): void
    {
        // L'administrador té tots els permisos del gestor i els de configuració.
        Gate::before(fn (User $user): ?bool => $user->isAdmin() ? true : null);

        Gate::define('manage-settings', fn (User $user): bool => false);
        Gate::define('view-audit', fn (User $user): bool => false);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)->by($request->user()?->id ?: $request->ip()));

        // Límit global per IP. El límit per usuari + IP s'aplica a LoginRequest.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute((int) config('gestpartits.auth.max_login_attempts_per_ip'))->by($request->ip()));

        // Operacions costoses o amb efectes externs (proposta, imatges, enviament d'avisos).
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(20)->by('sensitive:'.($request->user()?->id ?: $request->ip())));

        RateLimiter::for('password-email', fn (Request $request) => [
            Limit::perMinute(3)->by('email:'.mb_strtolower((string) $request->input('email'))),
            Limit::perMinute(10)->by('ip:'.$request->ip()),
        ]);
    }
}
