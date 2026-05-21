<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $user = $request->user();
        $this->deleteStoredAvatar($user->avatar_url);

        $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
        $path = $data['avatar']->store("users/{$user->id}/avatar", $disk);
        $url = Storage::disk($disk)->url($path);

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            $url = '/storage/' . ltrim($path, '/');
        }

        $user->forceFill(['avatar_url' => $url])->save();

        return Redirect::route('profile.edit')->with('success', 'Profile image updated.');
    }

    public function deleteAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->deleteStoredAvatar($user->avatar_url);
        $user->forceFill(['avatar_url' => null])->save();

        return Redirect::route('profile.edit')->with('success', 'Profile image removed.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function deleteStoredAvatar(?string $url): void
    {
        if (! $url) {
            return;
        }

        $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $path = preg_replace('#^/storage/#', '', $path);

        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk($disk)->delete(ltrim($path, '/'));
        }
    }
}
