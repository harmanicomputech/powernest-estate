<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    /** Non-essential categories a user can opt out of email for. */
    public const CATEGORIES = [
        'visitors' => 'Visitor arrivals',
        'maintenance' => 'Maintenance updates',
        'billing' => 'Invoices and reminders',
        'announcements' => 'Estate announcements',
        'bookings' => 'Facility bookings',
        'operations' => 'Staff and operations alerts',
    ];

    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user(), 'categories' => self::CATEGORIES]);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $selected = (array) $request->input('mail', []);
        $preferences = [];

        foreach (array_keys(self::CATEGORIES) as $category) {
            $preferences[$category] = ['mail' => in_array($category, $selected, true)];
        }

        $request->user()->forceFill(['notification_preferences' => $preferences])->save();

        return back()->with('success', 'Notification preferences saved.');
    }
}
