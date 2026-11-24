<section class="section">
    <div class="container" style="max-width:580px">
        <form class="panel stack" data-action="reset-password">
            <h2>Choose a new password</h2>
            <div class="field">
                <label for="new-email">Email</label>
                <input id="new-email" name="email" type="email" required>
            </div>
            <input type="hidden" name="token">
            <div class="field">
                <label for="new-password">New password</label>
                <input id="new-password" name="password" type="password" minlength="8" required>
            </div>
            <div class="field">
                <label for="new-confirm">Confirm password</label>
                <input id="new-confirm" name="password_confirmation" type="password" minlength="8" required>
            </div>
            <button class="btn">Reset Password</button>
            <div class="form-message" role="alert">
            </div>
        </form>
    </div>
</section>
