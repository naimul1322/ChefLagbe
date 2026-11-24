<section class="page-head">
    <div class="container">
        <div class="crumb">
            <a href="/chefs" data-route>Chefs</a> / Booking / Payment
        </div>
        <h1>Complete Your Booking</h1>
        <p>Review your booking and choose a demonstration payment method.</p>
    </div>
</section>
<section class="section">
    <div class="container layout-split">
        <form class="panel stack" id="payment-form" data-action="payment">
            <div>
                <div class="eyebrow">
                    Step 2 of 2
                </div>
                <h2>Payment Method</h2>
                <div class="notice">
                    <strong>Simulation only.</strong> No actual money will be charged. Never enter a real card number, PIN, OTP or mobile wallet credentials.
                </div>
            </div>
            <div class="payment-options">
                <label class="payment-choice"><input name="method" type="radio" value="card" checked><strong>Card</strong><small>Demo card</small></label>
                <label class="payment-choice"><input name="method" type="radio" value="bkash"><strong>bKash</strong><small>Simulation</small></label>
                <label class="payment-choice"><input name="method" type="radio" value="nagad"><strong>Nagad</strong><small>Simulation</small></label>
                <label class="payment-choice"><input name="method" type="radio" value="rocket"><strong>Rocket</strong><small>Simulation</small></label>
                <label class="payment-choice"><input name="method" type="radio" value="cash"><strong>Cash</strong><small>Demo cash</small></label>
            </div>
            <div id="method-description" class="notice">
                Card simulation uses a test result only; no card information is collected.
            </div>
            <div class="field">
                <label for="outcome">Simulation outcome</label>
                <select name="outcome" id="outcome">
                    <option value="success">Successful payment</option>
                    <option value="failed">Failed payment (test)</option>
                </select>
            </div>
            <button class="btn" type="submit" disabled>Simulate Payment</button>
            <div class="form-message" role="alert">
            </div>
            <div id="payment-receipt" aria-live="polite">
            </div>
        </form>
        <aside class="panel sticky-card" id="payment-summary">
            <p>Loading booking...</p>
        </aside>
    </div>
</section>
