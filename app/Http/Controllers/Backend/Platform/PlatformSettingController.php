<?php

namespace App\Http\Controllers\Backend\Platform;

use App\Http\Controllers\Backend\Hms\Concerns\RespondsWithAlert;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/** Marketplace-wide settings: where guests transfer money, default commission. */
class PlatformSettingController extends Controller
{
    use RespondsWithAlert;

    public function edit()
    {
        return view('backend.platform.settings', [
            'bankAccounts' => Setting::get('platform_bank_accounts', ''),
            'defaultCommission' => Setting::get('platform_default_commission', '10'),
            'autoApprove' => Setting::get('platform_auto_approve_hotels', '0') === '1',
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'bank_accounts' => ['nullable', 'string', 'max:1000'],
            'default_commission' => ['required', 'numeric', 'between:0,100'],
        ], [], ['bank_accounts' => 'rekening platform', 'default_commission' => 'komisi default']);

        Setting::set('platform_bank_accounts', (string) ($data['bank_accounts'] ?? ''));
        Setting::set('platform_default_commission', (string) $data['default_commission']);
        Setting::set('platform_auto_approve_hotels', $request->boolean('auto_approve') ? '1' : '0');

        return $this->done($request, 'Pengaturan platform disimpan.');
    }
}
