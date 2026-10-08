<?php

namespace App\Http\Controllers\Backend\Hms\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * AJAX forms (hms-admin.js) get JSON for SweetAlert; plain posts get a
 * redirect with a flash message, which the layout also shows via SweetAlert.
 */
trait RespondsWithAlert
{
    protected function done(Request $request, string $message, ?string $redirect = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => $message, 'redirect' => $redirect]);
        }

        return ($redirect ? redirect($redirect) : back())->with('success', $message);
    }

    protected function fail(Request $request, string $message, int $status = 422): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['status' => 'error', 'message' => $message], $status);
        }

        return back()->with('error', $message);
    }
}
