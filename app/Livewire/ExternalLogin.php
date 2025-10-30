<?php

namespace App\Livewire;

use App\Models\ExternalAccess;
use Livewire\Component;
use Illuminate\Support\Facades\Session;

class ExternalLogin extends Component
{
    public $token;
    public $password;
    public $error;

    public function mount($token)
    {
        $this->token = $token;
        
        if (Session::get('external_authenticated_' . $token)) {
            return redirect()->route('external.dashboard', $token);
        }

        $externalAccess = ExternalAccess::where('access_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$externalAccess) {
            abort(404, 'External access not found');
        }
    }

    public function authenticate()
    {
        $this->login();
    }

    public function login()
    {
        $this->error = null;
        
        $externalAccess = ExternalAccess::where('access_token', $this->token)
            ->where('is_active', true)
            ->first();

        if (!$externalAccess || $externalAccess->password !== $this->password) {
            $this->error = 'Invalid password';
            return;
        }

        $externalAccess->updateLastAccessed();

        // Set session
        Session::put([
            'external_project_id' => $externalAccess->project_id,
            'external_authenticated' => true
        ]);
        Session::put('external_authenticated_' . $this->token, true);

        return redirect()->route('external.dashboard', $this->token);
    }

    public function render()
    {
        return view('livewire.external-login')
            ->layout('layouts.external');
    }
}