<?php

namespace App\Parameters\MailTypes;

class Welcome extends MailType
{
    public function identifier()
    {
        return 'welcome';
    }

    public function description()
    {
        $gas = currentAbsoluteGas();
        $manual = $gas->hasFeature('public_registrations') && $gas->public_registrations['manual'] == true;

        if ($manual) {
            return _i('Messaggio inviato ai nuovi iscritti registrati sulla piattaforma, in attesa di approvazione.');
        }
        else {
            return _i('Messaggio inviato ai nuovi iscritti registrati sulla piattaforma.');
        }
    }

    public function params()
    {
        return [
            'username' => _i('Username assegnato al nuovo utente'),
            'gas_login_link' => _i('Link della pagina di login'),
        ];
    }

    public function enabled($gas)
    {
        return $gas->hasFeature('public_registrations');
    }
}
