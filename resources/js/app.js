import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import bookingCalendar from './booking-calendar';
import availabilityGrid from './availability-grid';
import matchScoreboard from './match-scoreboard';
import bookingsList from './bookings-list';
import adminBookingForm from './admin-booking-form';
import galleryUpload from './gallery-upload';
import openPlayDashboard from './open-play-dashboard';

window.Alpine = Alpine;
window.Swal = Swal;

// Shared helper for "are you sure?" prompts on plain POST forms (admin booking
// actions, etc). Usage: <form onsubmit="return confirmSubmit(this, {...})">
// Always returns false to block the native synchronous submit; the form is
// submitted programmatically once the user confirms the SweetAlert2 dialog.
// Pages that auto-refresh in the background (e.g. the admin bookings list
// polling for new/updated bookings) check this before reloading, so an
// in-flight "are you sure?" prompt never gets yanked out from under the
// admin mid-decision - the reload just waits until the dialog is resolved.
window.__confirmDialogOpen = false;

window.confirmSubmit = function (form, options = {}) {
    const {
        title = 'Are you sure?',
        text = '',
        icon = 'question',
        confirmButtonText = 'Yes',
        confirmButtonColor = '#111827',
    } = options;

    window.__confirmDialogOpen = true;

    Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText,
        confirmButtonColor,
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) {
            // Page is navigating away regardless - no need to clear the flag.
            form.submit();
            return;
        }

        window.__confirmDialogOpen = false;
    });

    return false;
};

Alpine.data('bookingCalendar', bookingCalendar);
Alpine.data('availabilityGrid', availabilityGrid);
Alpine.data('matchScoreboard', matchScoreboard);
Alpine.data('bookingsList', bookingsList);
Alpine.data('adminBookingForm', adminBookingForm);
Alpine.data('galleryUpload', galleryUpload);
Alpine.data('openPlayDashboard', openPlayDashboard);
Alpine.start();
