<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar la respuesta personalizada de login
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Personalizar vistas de Fortify
        Fortify::loginView(function () {
            return view('auth.login');
        });

        // Deshabilitar el registro público
        Fortify::registerView(function () {
            // Redirigir al login si intentan acceder al registro
            return redirect()->route('login')->with('error', 'El registro público está deshabilitado. Contacte al administrador.');
        });

        // Personalizar vista de verificación de email
        Fortify::verifyEmailView(function () {
            return view('auth.verify-email');
        });

        // Personalizar vista de confirmación de contraseña
        Fortify::confirmPasswordView(function () {
            return view('auth.confirm-password');
        });

        // Personalizar vista de solicitud de reset de contraseña
        Fortify::requestPasswordResetLinkView(function () {
            return view('auth.forgot-password');
        });

        // Personalizar vista de reset de contraseña
        Fortify::resetPasswordView(function (Request $request) {
            return view('auth.reset-password', ['request' => $request]);
        });

        // Personalizar vista de autenticación de dos factores
        Fortify::twoFactorChallengeView(function () {
            return view('auth.two-factor-challenge');
        });

        // Configurar autenticación personalizada (opcional)
        Fortify::authenticateUsing(function (Request $request) {
            $user = \App\Models\User::where('email', $request->email)->first();

            if ($user && \Hash::check($request->password, $user->password)) {
                // Verificar si el usuario está activo
                if (!$user->active) {
                    return null;
                }

                // Si es una relación polimórfica, verificar que la entidad relacionada esté activa
                if ($user->userable) {
                    if (method_exists($user->userable, 'active') && !$user->userable->active) {
                        return null;
                    }
                }

                return $user;
            }

            return null;
        });
    }
}
