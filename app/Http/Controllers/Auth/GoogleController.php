<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            // Get avatar URL from Google user
            $avatarUrl = $googleUser->avatar ?? $googleUser->getAvatar();
            
            $user = User::where('google_id', $googleUser->id)->first();
            
            if ($user) {
                // Update avatar URL if it's missing or different
                if (empty($user->avatar_url) || $user->avatar_url !== $avatarUrl) {
                    $user->update(['avatar_url' => $avatarUrl]);
                }
                // Ensure user has at least one role
                if (!$user->hasAnyRole(Role::all())) {
                    $this->assignDefaultRole($user);
                }
                Auth::login($user);
                return redirect()->intended('/admin');
            }
            
            $existingUser = User::where('email', $googleUser->email)->first();
            
            if ($existingUser) {
                $existingUser->update([
                    'google_id' => $googleUser->id,
                    'avatar_url' => $avatarUrl,
                ]);
                // Ensure user has at least one role
                if (!$existingUser->hasAnyRole(Role::all())) {
                    $this->assignDefaultRole($existingUser);
                }
                Auth::login($existingUser);
                return redirect()->intended('/admin');
            }
            
            $newUser = User::create([
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'google_id' => $googleUser->id,
                'avatar_url' => $avatarUrl,
                'password' => null,
            ]);
            
            // Assign default role to new users BEFORE logging in
            $this->assignDefaultRole($newUser);
            
            // Verify user has a role before logging in
            if (!$newUser->hasAnyRole(Role::all())) {
                return redirect('/admin/login')->with('error', 'Account created but no role assigned. Please contact administrator.');
            }
            
            Auth::login($newUser);
            return redirect()->intended('/admin');
            
        } catch (\Exception $e) {
            return redirect('/admin/login')->with('error', 'Something went wrong with Google authentication.');
        }
    }

    /**
     * Assign default role to user (member or Employee, whichever exists)
     * Also assign panel_user role for Filament Shield access
     */
    private function assignDefaultRole(User $user): void
    {
        // Try 'member' first (from RoleSeeder), then 'Employee' as fallback
        // Use 'web' guard explicitly to avoid guard mismatch
        $defaultRole = Role::whereIn('name', ['member', 'Employee'])
            ->where('guard_name', 'web')
            ->first();
        
        if (!$defaultRole) {
            // If no role with 'web' guard exists, try to find any and update guard
            $defaultRole = Role::whereIn('name', ['member', 'Employee'])->first();
            if ($defaultRole && $defaultRole->guard_name !== 'web') {
                $defaultRole->guard_name = 'web';
                $defaultRole->save();
            }
        }
        
        if ($defaultRole) {
            $user->assignRole($defaultRole);
        }
        
        // Assign panel_user role for Filament Shield access
        $panelUserRole = Role::firstOrCreate(
            ['name' => 'panel_user', 'guard_name' => 'web']
        );
        if (!$user->hasRole('panel_user')) {
            $user->assignRole($panelUserRole);
        }
    }
}
