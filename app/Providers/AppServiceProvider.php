<?php

namespace App\Providers;

use App\Listeners\UpdateEmailDeliveryFromResend;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Resend\Laravel\Events\EmailBounced;
use Resend\Laravel\Events\EmailComplained;
use Resend\Laravel\Events\EmailDelivered;
use Resend\Laravel\Events\EmailDeliveryDelayed;
use Resend\Laravel\Events\EmailFailed;
use Resend\Laravel\Events\EmailSent;
use Resend\Laravel\Events\EmailSuppressed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureEmailDelivery();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(6)->letters()->numbers());
    }

    private function configureEmailDelivery(): void
    {
        RateLimiter::for('resend-email', fn (): Limit => Limit::perSecond(4)->by('resend-team'));

        Event::listen([
            EmailSent::class,
            EmailDelivered::class,
            EmailDeliveryDelayed::class,
            EmailFailed::class,
            EmailBounced::class,
            EmailSuppressed::class,
            EmailComplained::class,
        ], UpdateEmailDeliveryFromResend::class);
    }
}
