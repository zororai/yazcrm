<?php

namespace App\Http\Middleware;

use App\Models\DistressDomain;
use App\Models\LookupItem;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()?->load('extension')?->loadCount('subordinates'),
            ],
            'unreadNotificationsCount' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'distressDomains' => fn () => DistressDomain::active()->pluck('name'),
            // Shared globally (not just on /tickets) so the "Call ended —
            // log a ticket" popup, which can appear on any page, has the
            // same lookup lists as the full New Ticket form.
            'keyPops'                 => fn () => LookupItem::where('type', 'key_pops')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'modesOfCommunication'    => fn () => LookupItem::where('type', 'mode_of_communication')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'projects'                => fn () => LookupItem::where('type', 'project')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'servicesRequested'       => fn () => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'secondServicesRequested' => fn () => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'referredTo'              => fn () => LookupItem::where('type', 'referred_to')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'serviceCategories'       => fn () => LookupItem::where('type', 'service_requested')->where('is_active', true)->get(['name', 'classification_categories'])->mapWithKeys(fn ($i) => [$i->name => $i->classification_categories ?? []]),
        ]);
    }
}
