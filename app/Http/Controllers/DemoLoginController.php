<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Connexion de démonstration : on choisit l'utilisateur dans une liste, sans
 * mot de passe, pour tester les droits de l'agent avec Alice puis Chloé.
 *
 * Simplification du TP, pas une authentification : les routes ne sont
 * déclarées qu'en environnement local et en test (voir routes/web.php).
 */
class DemoLoginController extends Controller
{
    public function show(): View
    {
        return view('auth.demo-login', ['users' => User::with('projects')->orderBy('name')->get()]);
    }

    public function login(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('agent.show');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();

        return redirect()->route('login');
    }
}
