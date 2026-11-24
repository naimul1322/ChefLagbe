<section class="page-head">
    <div class="container">
        <div class="crumb">
            <a href="/" data-route>Home</a> / <a href="/chefs" data-route>Chefs</a> / Booking
        </div>
        <h1>Book Your Chef</h1>
        <p>Tell us about your occasion. Get a transparent estimate before you confirm.</p>
    </div>
</section>
<section class="section">
    <div class="container layout-split">
        <form class="panel stack" id="booking-form" data-action="booking">
            <div>
                <div class="eyebrow">
                    Step 1 of 2
                </div>
                <h2>Booking Details</h2>
                <p class="muted">Select your service and preferred date and time.</p>
            </div>
            <div class="field">
                <label for="service_id">Service</label>
                <select id="service_id" name="service_id" required>
                    <option value="">Loading services...</option>
                </select>
            </div>
            <div class="grid2">
                <div class="field">
                    <label for="booking_date">Date</label>
                    <input id="booking_date" name="booking_date" type="date" required>
                </div>
                <div class="field">
                    <label for="booking_time">Start time (Bangladesh)</label>
                    <input id="booking_time" name="booking_time" type="time" required>
                </div>
            </div>
            <div class="grid2">
                <div class="field">
                    <label for="duration_hours">Duration (hours)</label>
                    <input id="duration_hours" name="duration_hours" type="number" min="1" max="12" step="1" value="3" required>
                </div>
                <div class="field">
                    <label for="guests">Number of guests</label>
                    <input id="guests" name="guests" type="number" min="1" max="300" value="4" required>
                </div>
            </div>
            <div class="field">
                <label for="cuisine">Cuisine preference</label>
                <input id="cuisine" name="cuisine" placeholder="e.g. Bengali, Italian, vegetarian" maxlength="120">
            </div>
            <div class="field">
                <label for="dietary_notes">Dietary notes and allergies</label>
                <textarea id="dietary_notes" name="dietary_notes" maxlength="1000" placeholder="Please discuss allergies directly with your chef before the event."></textarea>
            </div>
            <div class="field">
                <label for="special_instructions">Event location and special instructions</label>
                <textarea id="special_instructions" name="special_instructions" maxlength="1500" required placeholder="Your event address, occasion, kitchen facilities..."></textarea>
            </div>
            <div class="notice">
                A request is not a reservation until your chef confirms it. If another overlapping request is confirmed first, yours is cancelled and any simulated payment is refunded. Customer cancellation requires at least 48 hours before the scheduled start.
            </div>
            <div id="booking-auth-note" hidden>
            </div>
            <div id="availability-message" class="form-message" aria-live="polite">
            </div>
            <div class="inline-actions">
                <button type="button" class="btn btn-outline" id="check-availability" disabled>Check Availability</button><button type="submit" class="btn" disabled>Continue to Payment</button>
            </div>
            <div class="form-message" role="alert">
            </div>
        </form>
        <aside class="panel sticky-card" id="booking-summary">
            <p>Loading chef details...</p>
        </aside>
    </div>
</section>
