<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue des réponses de l'API, par ordre de priorité :
 *  1. en-tête Accept-Language (posé par le proxy Next d'après la langue
 *     choisie dans l'interface) ;
 *  2. préférence enregistrée de l'utilisateur connecté (users.locale) ;
 *  3. langue par défaut (config app.locale).
 */
class SetLocale
{
    public const SUPPORTED = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;

        // getLanguages() vide = en-tête absent ou vide : on passe à la
        // préférence de l'utilisateur.
        if ($request->getLanguages() !== []) {
            $preferred = $request->getPreferredLanguage(self::SUPPORTED);
            $locale = $preferred ? substr($preferred, 0, 2) : null;
        }

        $locale ??= auth('sanctum')->user()?->locale;

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
