<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\BookingHold;

class AdminNav
{
    /**
     * Single source of truth for the admin nav so the desktop sidebar and
     * mobile topbar menu can't drift out of sync with each other.
     */
    public static function items(): \Illuminate\Support\Collection
    {
        // Representative bookings only, so a multi-session order pending
        // review counts as 1 item to act on - not one per session, which
        // would overcount relative to what the bookings list actually shows.
        $pendingBookingsCount = Booking::where('status', 'pending_payment')
            ->where(function ($q) {
                $q->whereNull('booking_order_id')
                    ->orWhereIn('id', function ($sub) {
                        $sub->selectRaw('MIN(id)')->from('bookings')->whereNotNull('booking_order_id')->groupBy('booking_order_id');
                    });
            })
            ->count();

        $activeHoldsCount = BookingHold::whereNull('resolved_at')->count();

        // $query lets a nav item point at a specific query string on a
        // route another item also links to (e.g. admin.bookings.index
        // with ?tab=history vs ?tab=bookings) without them all lighting up
        // "active" together - active also requires the current tab query
        // param (default 'bookings' when absent) to match this item's own.
        $navItem = function (string $routeName, string $label, string $icon, ?int $badge = null, array $query = []) {
            $active = request()->routeIs($routeName.'*')
                && ($query['tab'] ?? 'bookings') === request()->query('tab', 'bookings');

            return [
                'routeName' => $routeName,
                'query' => $query,
                'label' => $label,
                'icon' => $icon,
                'active' => $active,
                'badge' => $badge,
            ];
        };

        $navGroup = function (string $label, string $icon, array $children) {
            $children = collect($children)->map(function ($child) {
                $child['active'] = request()->routeIs($child['routeName'].'*');
                return $child;
            });

            return [
                'label' => $label,
                'icon' => $icon,
                'children' => $children,
                'active' => $children->contains('active', true),
            ];
        };

        $items = collect([
            $navItem('admin.dashboard', 'Dashboard', 'ph-squares-four'),
            $navItem('admin.bookings.index', 'Bookings', 'ph-calendar-check', $pendingBookingsCount, ['tab' => 'bookings']),
            $navItem('admin.bookings.index', 'History', 'ph-clock-counter-clockwise', null, ['tab' => 'history']),
            $navItem('admin.bookings.index', 'Cancelled', 'ph-x-circle', null, ['tab' => 'cancelled']),
            $navItem('admin.bookings.schedule', 'Day Schedule', 'ph-calendar-blank'),
            $navItem('admin.bookings.week-schedule', 'Week Schedule', 'ph-calendar-dots'),
            $navItem('admin.bookings.holds.index', 'Held Bookings', 'ph-pause-circle', $activeHoldsCount),
            // Hidden for now - re-add when check-in is ready to surface again.
            // $navItem('admin.checkin.index', 'Check-in', 'ph-qr-code'),
        ]);

        if (auth()->user()->isAdmin()) {
            $items->push($navItem('admin.courts.index', 'Courts', 'ph-tennis-ball'));
            $items->push($navItem('admin.payment-methods.index', 'Payment methods', 'ph-credit-card'));
            $items->push($navItem('admin.users.index', 'Users', 'ph-users'));
            $items->push($navGroup('Reports', 'ph-chart-bar', [
                ['routeName' => 'admin.reports.bookings', 'label' => 'Booking Reports'],
                ['routeName' => 'admin.reports.revenue', 'label' => 'Revenue & Finance Reports'],
                ['routeName' => 'admin.reports.clients', 'label' => 'Client Reports'],
            ]));
            $items->push($navGroup('Settings', 'ph-gear-six', [
                ['routeName' => 'admin.settings.edit', 'label' => 'General'],
                ['routeName' => 'admin.settings.hours', 'label' => 'Time-of-day Groups'],
                ['routeName' => 'admin.settings.rates.index', 'label' => 'Court Rates'],
                ['routeName' => 'admin.settings.location', 'label' => 'Location'],
                ['routeName' => 'admin.hero-images.index', 'label' => 'Hero images'],
                ['routeName' => 'admin.gallery-images.index', 'label' => 'Album'],
                ['routeName' => 'admin.settings.emails.index', 'label' => 'Emails'],
            ]));
        }

        return $items;
    }

    public static function pendingBookingsCount(): int
    {
        return Booking::where('status', 'pending_payment')
            ->where(function ($q) {
                $q->whereNull('booking_order_id')
                    ->orWhereIn('id', function ($sub) {
                        $sub->selectRaw('MIN(id)')->from('bookings')->whereNotNull('booking_order_id')->groupBy('booking_order_id');
                    });
            })
            ->count();
    }
}
