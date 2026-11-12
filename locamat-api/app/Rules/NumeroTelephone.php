<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro de téléphone international. Le Bénin (+229) suit son plan de numérotation à
 * 10 chiffres (01 XX XX XX XX) ; les autres pays sont validés au format E.164 (8 à 15 chiffres).
 *
 * `normaliser()` ramène ce que l'utilisateur saisit (espaces, points, « 00 » à la place de « + »)
 * à la forme stockée : `+229 01 42 12 34 56` pour le Bénin, `+33612345678` ailleurs.
 */
class NumeroTelephone implements ValidationRule
{
    private const PREFIXES_BENIN = [
        '20', '21', '22', '23', '24', '28', '29',
        '40', '41', '42', '43', '44', '45', '46', '47', '48', '49',
        '50', '51', '52', '53', '54', '55', '56', '57', '58', '59',
        '60', '61', '62', '63', '64', '65', '66', '67', '68', '69',
        '90', '91', '92', '93', '94', '95', '96', '97', '98', '99',
    ];

    public static function normaliser(mixed $valeur): mixed
    {
        if (! is_string($valeur) || trim($valeur) === '') {
            return $valeur;
        }

        $brut = trim($valeur);
        $chiffres = preg_replace('/\D/', '', $brut) ?? '';
        $estInternational = str_starts_with($brut, '+') || str_starts_with($chiffres, '00');

        if (! $estInternational) {
            return $brut;
        }

        if (str_starts_with($chiffres, '00')) {
            $chiffres = substr($chiffres, 2);
        }

        if (str_starts_with($chiffres, '229')) {
            $local = substr($chiffres, 3);

            if (preg_match('/^01(\d{2})(\d{2})(\d{2})(\d{2})$/', $local, $groupes) === 1) {
                return sprintf('+229 01 %s %s %s %s', $groupes[1], $groupes[2], $groupes[3], $groupes[4]);
            }
        }

        return '+'.$chiffres;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_starts_with($value, '+')) {
            $fail('Saisissez le numéro avec l’indicatif du pays (par exemple +229 01 42 12 34 56 pour le Bénin).');

            return;
        }

        if (str_starts_with($value, '+229')) {
            $pattern = '/^\+229 01 (?:'.implode('|', self::PREFIXES_BENIN).') \d{2} \d{2} \d{2}$/';

            if (preg_match($pattern, $value) !== 1) {
                $fail('Un numéro béninois doit avoir la forme +229 01 XX XX XX XX, avec un préfixe attribué au Bénin.');
            }

            return;
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $value) !== 1) {
            $fail('Le numéro de téléphone est invalide : vérifiez l’indicatif du pays et le nombre de chiffres.');
        }
    }
}
