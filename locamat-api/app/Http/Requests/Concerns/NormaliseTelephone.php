<?php

namespace App\Http\Requests\Concerns;

use App\Rules\NumeroTelephone;

trait NormaliseTelephone
{
    protected function prepareForValidation(): void
    {
        if ($this->has('telephone')) {
            $this->merge(['telephone' => NumeroTelephone::normaliser($this->input('telephone'))]);
        }
    }
}
