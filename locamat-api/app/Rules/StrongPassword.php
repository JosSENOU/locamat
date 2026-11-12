<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règles de mot de passe de la plateforme : longueur minimale, majuscule, minuscule,
 * chiffre et caractère spécial, sans espace.
 */
class StrongPassword implements ValidationRule
{
    public const LONGUEUR_MAXIMALE = 64;

    public function __construct(private readonly int $longueurMinimale = 8) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Le mot de passe est invalide.');

            return;
        }

        if (mb_strlen($value) > self::LONGUEUR_MAXIMALE) {
            $fail('Le mot de passe ne doit pas dépasser '.self::LONGUEUR_MAXIMALE.' caractères.');

            return;
        }

        if (preg_match('/\s/u', $value) === 1) {
            $fail('Le mot de passe ne doit pas contenir d’espace.');

            return;
        }

        $manques = [];

        if (mb_strlen($value) < $this->longueurMinimale) {
            $manques[] = "au moins {$this->longueurMinimale} caractères";
        }

        if (preg_match('/\p{Lu}/u', $value) !== 1) {
            $manques[] = 'une majuscule';
        }

        if (preg_match('/\p{Ll}/u', $value) !== 1) {
            $manques[] = 'une minuscule';
        }

        if (preg_match('/\d/', $value) !== 1) {
            $manques[] = 'un chiffre';
        }

        if (preg_match('/[^\p{L}\d\s]/u', $value) !== 1) {
            $manques[] = 'un caractère spécial (@ # $ % ! ? …)';
        }

        if ($manques !== []) {
            $fail('Le mot de passe doit contenir : '.implode(', ', $manques).'.');
        }
    }
}
