import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import bookingCalendar from './booking-calendar';
import availabilityGrid from './availability-grid';
import matchScoreboard from './match-scoreboard';
import bookingsList from './bookings-list';
import adminBookingForm from './admin-booking-form';
import galleryUpload from './gallery-upload';

window.Alpine = Alpine;
window.Swal = Swal;

// Shared helper for "are you sure?" prompts on plain POST forms (admin booking
// actions, etc). Usage: <form onsubmit="return confirmSubmit(this, {...})">
// Always returns false to block the native synchronous submit; the form is
// submitted programmatically once the user confirms the SweetAlert2 dialog.
window.confirmSubmit = function (form, options = {}) {
    const {
        title = 'Are you sure?',
        text = '',
        icon = 'question',
        confirmButtonText = 'Yes',
        confirmButtonColor = '#111827',
    } = options;

    Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText,
        confirmButtonColor,
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });

    return false;
};

Alpine.data('bookingCalendar', bookingCalendar);
Alpine.data('availabilityGrid', availabilityGrid);
Alpine.data('matchScoreboard', matchScoreboard);
Alpine.data('bookingsList', bookingsList);
Alpine.data('adminBookingForm', adminBookingForm);
Alpine.data('galleryUpload', galleryUpload);
Alpine.start();
