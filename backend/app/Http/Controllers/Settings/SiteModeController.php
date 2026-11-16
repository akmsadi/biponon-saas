<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SiteModeUpdateRequest;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteModeController extends Controller
{
    /**
     * Switch the whole site between production and demo mode.
     */
    public function update(SiteModeUpdateRequest $request): RedirectResponse
    {
        $demoMode = $request->boolean('demo_mode');

        PlatformSetting::put('demo_mode', $demoMode);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $demoMode ? __('Demo mode is on.') : __('Production mode is on.'),
        ]);

        return back();
    }
}
