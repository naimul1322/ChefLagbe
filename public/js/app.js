/* ChefLagbe: vanilla ES6, shared between Laravel pages and the offline UI preview. */
(() => {
    'use strict';
    const $ = (s, el = document) => el.querySelector(s), $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
    const tk = v => '৳' + Number(v || 0).toLocaleString('en-BD', { maximumFractionDigits: 2 });
    const title = v => String(v || '').replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    const cfg = () => window.CHEFLAGBE;
    const isDemo = () => cfg().mode === 'demo';
    const query = () => new URLSearchParams(isDemo() ? (cfg().query || '') : location.search);
    const route = (p) => {
        if (isDemo()) {
            window.demoNavigate(p);
        }
        else {
            location.assign(p);
        }
    };
    const current = () => isDemo() ? cfg().path : location.pathname + location.search;
    const asset = p => isDemo() ? (window.DEMO_ASSETS[p] || p) : p;
    let me = null, chef = null, dashTab = 'overview', toastTimer = null, renderRun = 0, searchTimer = null, availabilityRun = 0;
    const dhakaDate = (date = new Date()) => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Dhaka', year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
    function clearAvailability() {
        availabilityRun++;
        const el = $('#availability-message');
        if (el) {
            el.textContent = '';
            el.className = 'form-message';
        }
    }
    function passwordToggles(root = document) {
        $$('input[type=password]', root).forEach(input => {
            if (input.dataset.toggleReady) {
                return;
            }
            input.dataset.toggleReady = 'true';
            input.maxLength = 72;
            const wrap = input.parentElement;
            wrap.classList.add('password-wrap');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'password-toggle';
            btn.textContent = 'Show';
            btn.setAttribute('aria-label', 'Show password');
            btn.setAttribute('aria-pressed', 'false');
            wrap.append(btn);
            btn.addEventListener('click', () => {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Hide' : 'Show';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.setAttribute('aria-pressed', String(show));
            });
        });
    }
    function receipt(b) {
        const p = b.payment;
        return p ? `<div class="notice"><strong>${esc(title(p.status))} · Demo transaction</strong><p>${esc(p.transaction_id)} · ${tk(p.amount)} · ${esc(title(p.method))}</p><p>No real money was transferred.</p></div>` : '';
    }
    function toast(msg) {
        if (!isDemo() && window.Swal) {
            Swal.fire({
                toast: true, position: 'bottom-end', text: String(msg), timer: 4000, showConfirmButton: false
            });
            return;
        }
        const el = $('#toast');
        if (!el) {
            return;
        }
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.hidden = true, 4000);
    }
    function modal(html) {
        const dialog = $('#modal');
        $('#modal-content').innerHTML = html;
        if (!dialog.open) {
            dialog.showModal();
        }
    }
    function closeModal() {
        $('#modal')?.close();
    }

    // Send requests to the Laravel API
    async function api(path, method = 'GET', data = null) {
        if (isDemo()) {
            return window.demoApi(path, method, data);
        }
        let headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const csrf = $('meta[name=csrf-token]')?.content;
        if (csrf) {
            headers['X-CSRF-TOKEN'] = csrf;
        }
        const options = { method, headers, credentials: 'same-origin' };
        if (data instanceof FormData) {
            options.body = data;
        }
        else if (data !== null) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }
        let res;
        try {
            res = await fetch('/api' + path, options);
        }
        catch {
            throw new Error('Could not reach the server. Check your internet or local Laravel server.');
        }
        let body;
        try {
            body = await res.json();
        }
        catch {
            body = { message: 'The server returned an unexpected response.' };
        }
        if (!res.ok) {
            let error = new Error(body.errors ? Object.values(body.errors).flat().join('\n') : (res.status === 419 ? 'Session expired. Refresh this page and try again.' : body.message || `Request failed (${res.status})`));
            error.status = res.status;
            throw error;
        }
        return body;
    }
    const getForm = form => Object.fromEntries(new FormData(form).entries());
    const message = (form, text, ok = false) => {
        let el = $('.form-message', form);
        if (!el) {
            el = document.createElement('div');
            el.className = 'form-message';
            el.setAttribute('role', 'alert');
            form.append(el);
        }
        el.className = 'form-message ' + (ok ? 'success' : 'error');
        el.textContent = text;
    };
    async function busy(form, fn) {
        if (form.dataset.busy === '1' || form.dataset.locked === '1') {
            return;
        }
        const btn = $('button[type=submit],button:not([type])', form);
        if (btn?.disabled) {
            return;
        }
        form.dataset.busy = '1';
        form.setAttribute('aria-busy', 'true');
        const label = btn?.innerHTML;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spin"></span> Processing...';
        }
        message(form, '');
        try {
            await fn();
        }
        catch (e) {
            if (form.isConnected) {
                message(form, e.message || 'Unexpected error.');
            }
        }
        finally {
            form.dataset.busy = '0';
            form.removeAttribute('aria-busy');
            if (btn?.isConnected) {
                btn.disabled = form.dataset.locked === '1';
                btn.innerHTML = label;
            }
        }
    }

    // Chef cards and navigation
    function avatar(c) {
        const initials = (c.name || 'Chef').replace(/^Chef /, '').split(/\s+/).map(a => a[0]).slice(0, 2).join('');
        const sample = (!c.profile_image && String(c.bio || '').startsWith('Fictional sample chef')) ? ['chef-portrait', 'chef-hero', 'training', 'kitchen', 'login', 'chef-illustration'][((Number(c.id) || 1) - 1) % 6] : null;
        const path = c.profile_image ? '/storage/' + c.profile_image : sample ? '/images/' + sample + '.webp' : null;
        return `<div class="chef-avatar" data-tone="${Number(c.id || 0) % 3}">${path ? `<img src="${esc(asset(path))}" alt="${esc(sample ? 'Illustrative culinary photograph' : c.name)}" loading="lazy">${sample ? '<small class="sample-photo-label">Illustrative photo</small>' : ''}` : `<span aria-label="Illustrated profile">${esc(initials)}</span>`}</div>`;
    }
    const pill = (v) => `<span class="pill ${['confirmed', 'completed', 'success', 'active', 'verified'].includes(v) ? 'green' : ['cancelled', 'failed', 'refunded', 'inactive'].includes(v) ? 'red' : 'neutral'}">${esc(title(v))}</span>`;
    function chefCard(c, featured = false) {
        const name = c.name.startsWith('Chef ') ? c.name : 'Chef ' + c.name;
        const service = query().get('service');
        return `<article class="chef-card"><div class="chef-img">${avatar(c)}<span class="chef-badge">${c.is_verified ? 'Verified' : 'Pending'}</span></div><div class="chef-info"><h3 class="chef-name">${esc(name)}</h3><p class="chef-specialty">${esc(c.specialty)}</p><div class="chef-rating"><span style="color:#b16c06;font-weight:700">★ ${Number(c.rating).toFixed(1)}</span><span>(${c.total_reviews} reviews)</span></div><p class="chef-location">⌖ ${esc(c.location)}, Bangladesh · ${c.experience_years} yrs</p>${featured ? '' : `<p class="chef-description">${esc(c.bio || 'Professional chef.')}</p>`}<div class="chef-footer"><div class="chef-price">${tk(c.price_per_hour)} <small>/hour</small></div><a class="btn" data-route href="/chefs/${c.id}${service ? '?service=' + encodeURIComponent(service) : ''}">View Profile</a></div></div></article>`;
    }
    function setNav() {
        const path = cfg().path || location.pathname;
        $$('.nav-links a').forEach(a => {
            const h = a.getAttribute('href');
            if (path.split('?')[0] === h || (h === '/chefs' && path.startsWith('/chefs/'))) {
                a.setAttribute('aria-current', 'page');
            }
            else {
                a.removeAttribute('aria-current');
            }
        });
        const box = $('#auth-links');
        if (me) {
            box.innerHTML = `<a data-route class="btn btn-secondary" href="${me.user_type === 'admin' ? '/admin/dashboard' : '/dashboard'}">Dashboard</a><button type="button" class="btn btn-outline" data-action="logout">Logout</button>`;
        }
        else {
            box.innerHTML = '<a data-route href="/login" class="btn btn-secondary">Login</a><a data-route href="/signup" class="btn">Sign Up</a>';
        }
    }
    async function getMe(run = null) {
        let user = null;
        try {
            const data = await api('/auth/me');
            user = data.user;
        }
        catch {
        }
        if (run !== null && run !== renderRun) {
            return null;
        }
        me = user;
        setNav();
        return me;
    }

    // Booking prices and search
    function moneyList(c) {
        return `<div class="money-list"><div><span>Subtotal</span><strong>${tk(c.subtotal)}</strong></div><div><span>Platform fee (10%)</span><span>${tk(c.platform_fee)}</span></div><div><span>Tax (15%)</span><span>${tk(c.tax)}</span></div><div class="total"><span>Total</span><span>${tk(c.total_amount)}</span></div></div>`;
    }
    function calc(hourly, hours) {
        const subtotal = Math.round(hourly * hours * 100) / 100, platform_fee = Math.round(subtotal * 10) / 100, tax = Math.round(subtotal * 15) / 100;
        return { subtotal, platform_fee, tax, total_amount: Math.round((subtotal + platform_fee + tax) * 100) / 100 };
    }
    function bookingSummary(b) {
        return `<div class="eyebrow">Booking summary</div><h2>${esc(b.booking_ref)}</h2><dl class="stack" style="gap:9px"><div><strong>Chef:</strong> ${esc(b.chef_name)}</div><div><strong>Service:</strong> ${esc(b.service)}</div><div><strong>Schedule:</strong> ${esc(b.booking_date)} · ${esc(b.booking_time)}</div><div><strong>Duration:</strong> ${b.duration_hours} hours · ${b.guests} guests</div><div><strong>Status:</strong> ${pill(b.status)}</div></dl>${moneyList(b)}${receipt(b)}`;
    }
    function renderBookingPrice() {
        const box = $('#booking-summary');
        if (!box || !chef) {
            return;
        }
        const hours = Math.max(1, Math.min(12, Number($('#duration_hours')?.value) || 1));
        box.innerHTML = `<div class="eyebrow">Your selected chef</div><div class="profile-summary">${avatar(chef)}<div><h2>${esc(chef.name)}</h2><p class="muted">${esc(chef.specialty)}</p><p>★ ${Number(chef.rating).toFixed(1)} · ${chef.experience_years} years</p></div></div><div class="money-list"><div><span>Hourly rate</span><strong>${tk(chef.price_per_hour)}</strong></div><div><span>Duration</span><span>${hours} hours</span></div></div>${moneyList(calc(chef.price_per_hour, hours))}<p class="small muted">This is a live estimate. The final amount is calculated by the server. Food and travel arrangements should be agreed with the chef.</p>`;
    }
    function formQuery(form) {
        return new URLSearchParams(Object.entries(getForm(form)).filter(([k, v]) => v !== ''));
    }
    async function searchChefs(page = 1) {
        const form = $('#chef-search'), box = $('#chef-results');
        if (!form || !box) {
            return;
        }
        const serial = ++renderRun;
        box.innerHTML = '<div class="loading">Finding chefs...</div>';
        $('#pagination').innerHTML = '';
        $('#result-count').textContent = 'Finding chefs...';
        const q = formQuery(form);
        q.set('page', page);
        try {
            const d = await api('/chefs?' + q);
            if (serial !== renderRun || !box.isConnected) {
                return;
            }
            box.innerHTML = d.data.length ? d.data.map(c => chefCard(c)).join('') : '<div class="empty"><h3>No chefs found</h3>Try a different location, cuisine or price.</div>';
            $('#result-count').textContent = `${d.total} ${d.total === 1 ? 'chef' : 'chefs'} found`;
            const pag = $('#pagination'), last = Number(d.last_page), cur = Number(d.current_page);
            const nums = [...new Set([1, cur - 1, cur, cur + 1, last].filter(n => n >= 1 && n <= last))].sort((a, b) => a - b);
            const item = (n, label = String(n)) => `<button type="button" data-page="${n}" ${n === cur ? 'class="active" aria-current="page"' : ''}>${label}</button>`;
            pag.innerHTML = last > 1 ? `${cur > 1 ? item(cur - 1, '‹') : ''}${nums.map((n, i) => (i && n > nums[i - 1] + 1 ? '<span>…</span>' : '') + item(n)).join('')}${cur < last ? item(cur + 1, '›') : ''}` : '';
        }
        catch (e) {
            if (serial === renderRun && box.isConnected) {
                $('#result-count').textContent = 'Unable to load chefs';
                box.innerHTML = `<div class="empty">${esc(e.message)} <button class="btn btn-outline" data-action="retry-search">Retry</button></div>`;
            }
        }
    }
    const field = (name, label, value = '', type = 'text', extra = '') => `<label class="field"><span>${esc(label)}</span><input name="${esc(name)}" type="${type}" value="${esc(value)}" ${extra}></label>`;
    const stat = (label, value) => `<div class="stat-tile"><div class="stat-label">${esc(label)}</div><div class="stat-number">${esc(value)}</div></div>`;
    function table(cols, rows) {
        return `<div class="table-tools"><label class="field"><span>Filter displayed records</span><input type="search" data-table-filter placeholder="Search this table..." autocomplete="off"></label><small class="muted" data-table-count>${rows.length} record${rows.length === 1 ? '' : 's'} loaded</small></div><div class="table-wrap" tabindex="0" aria-label="Scrollable data table"><table class="data-table"><thead><tr>${cols.map(c => `<th scope="col">${esc(c)}</th>`).join('')}</tr></thead><tbody>${rows.length ? rows.map(r => `<tr>${r.map(c => `<td>${c}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${cols.length}" class="center muted">No records yet.</td></tr>`}</tbody></table></div>`;
    }
    function buttonsForBooking(b, role) {
        let actions = [];
        if (role === 'customer') {
            if (b.status === 'pending' && !b.paid) {
                actions.push(`<a class="btn" data-route href="/payment/${b.id}">Pay</a>`);
            }
            if (['pending', 'confirmed'].includes(b.status)) {
                const start = Date.parse(b.booking_date + 'T' + b.booking_time + ':00+06:00');
                if (start - Date.now() >= 48 * 3600000) {
                    actions.push(`<button class="btn btn-outline" data-booking="${b.id}" data-status="cancelled">Cancel</button>`);
                }
                else {
                    actions.push('<small class="muted">48h cancellation cutoff</small>');
                }
            }
            if (b.status === 'completed' && !b.reviewed) {
                actions.push(`<button class="btn" data-review="${b.id}">Review</button>`);
            }
        }
        else {
            if (b.status === 'pending') {
                if (b.paid) {
                    actions.push(`<button class="btn" data-booking="${b.id}" data-status="confirmed">Accept</button>`);
                }
                actions.push(`<button class="btn btn-outline" data-booking="${b.id}" data-status="cancelled">Reject</button>`);
            }
            if (b.status === 'confirmed') {
                actions.push(`${Date.parse(b.booking_date + 'T' + b.booking_time + ':00+06:00') + b.duration_hours * 3600000 <= Date.now() ? `<button class="btn" data-booking="${b.id}" data-status="completed">Complete</button>` : ''}<button class="btn btn-outline" data-booking="${b.id}" data-status="cancelled">Cancel</button>`);
            }
        }
        actions.push(`<button class="btn btn-outline" data-details="${b.id}">Details</button>`);
        return actions.join(' ');
    }

    // Dashboard tables and reports
    function bookingTable(bookings, role) {
        return table(['Reference', 'Chef / Customer', 'Schedule', 'Total', 'Payment', 'Status', 'Actions'], bookings.map(b => [esc(b.booking_ref), esc(role === 'chef' ? b.customer_name : b.chef_name), `${esc(b.booking_date)}<br><small>${esc(b.booking_time)}</small>`, tk(b.total_amount), pill(b.paid ? 'success' : b.payment?.status || 'pending'), pill(b.status), buttonsForBooking(b, role)]));
    }
    function chart(rows) {
        if (!isDemo() && window.Chart) {
            return `<canvas data-chart='${esc(JSON.stringify(rows))}' height="170" aria-label="Monthly simulated revenue"></canvas>`;
        }
        const max = Math.max(1, ...rows.map(x => Number(x.amount)));
        return rows.map(x => `<div class="chart-row"><span>${esc(x.month)}</span><div class="chart-bar"><span style="width:${100 * Number(x.amount) / max}%"></span></div><strong>${tk(x.amount)}</strong></div>`).join('');
    }
    function activity(rows) {
        return rows.length ? rows.map(r => `<div class="review-card"><strong>${esc(title(r.action))}</strong><p>${esc(r.description || '')}</p><small class="muted">${esc(r.user || '')} · ${esc((r.created_at || '').slice(0, 10))}</small></div>`).join('') : '<p class="muted">No activity yet.</p>';
    }
    function dashPanel(title, html) {
        return `<section class="panel"><h2>${esc(title)}</h2>${html}</section>`;
    }
    async function dashboard(tab = dashTab) {
        const content = $('#dashboard-content');
        if (!content) {
            return;
        }
        if (!me) {
            route(cfg().page === 'admin-dashboard' ? '/admin/login' : '/login?next=/dashboard');
            return;
        }
        if (cfg().page === 'admin-dashboard' && me.user_type !== 'admin') {
            route('/dashboard');
            return;
        }
        if (cfg().page === 'dashboard' && me.user_type === 'admin') {
            route('/admin/dashboard');
            return;
        }
        const role = me.user_type;
        const tabs = role === 'admin' ? ['overview', 'users', 'chefs', 'bookings', 'payments', 'reviews', 'contacts', 'activity', 'revenue'] : role === 'chef' ? ['overview', 'bookings', 'chef profile', 'availability', 'earnings', 'reviews', 'account'] : ['overview', 'bookings', 'reviews', 'account'];
        if (!tabs.includes(tab)) {
            tab = 'overview';
        }
        dashTab = tab;
        $('#dashboard-role').textContent = role + ' account';
        $('#dashboard-title').textContent = tab === 'overview' ? (role === 'admin' ? 'Admin Dashboard' : `Welcome, ${me.first_name}`) : title(tab);
        $('#dashboard-subtitle').textContent = role === 'admin' ? 'Monitor the marketplace and manage users and bookings.' : role === 'chef' ? 'Manage your profile, availability and customer requests.' : 'Manage your bookings and account.';
        $('#dashboard-nav').innerHTML = '<h2>ChefLagbe</h2>' + tabs.map(t => `<button type="button" data-tab="${t}" ${t === tab ? 'class="active" aria-current="page"' : ''}>${title(t)}</button>`).join('') + '<button type="button" data-action="logout">Sign Out</button>';
        const run = ++renderRun;
        content.innerHTML = '<div class="loading">Loading ' + esc(tab) + '...</div>';
        let html = '';
        try {
            if (role === 'admin') {
                if (tab === 'overview') {
                    const d = await api('/admin/stats'), cs = (await api('/admin/chefs')).data.filter(c => !c.is_verified);
                    html = `<div class="dashboard-stats">${stat('Total users', d.users)}${stat('Total chefs', d.chefs)}${stat('Total bookings', d.bookings)}${stat('Simulated revenue', tk(d.revenue))}</div><div class="grid2">${dashPanel('Revenue · last six months', chart(d.monthly))}${dashPanel('Recent activity', activity(d.activity.slice(0, 4)))}</div>${dashPanel('Recent bookings', bookingTable(d.recent, 'admin'))}${dashPanel('Chef applications', adminChefTable(cs))}<div class="inline-actions"><button class="btn" data-tab="users">Manage Users</button><button class="btn btn-secondary" data-tab="chefs">Verify Chefs</button></div>`;
                }
                if (tab === 'users') {
                    const d = await api('/admin/users');
                    html = dashPanel('Users', table(['Name', 'Email', 'Role', 'Status', 'Action'], d.data.map(u => [esc(u.first_name + ' ' + u.last_name), esc(u.email), esc(title(u.user_type)), pill(u.is_active ? 'active' : 'inactive'), u.user_type === 'admin' ? 'Protected' : `<button class="btn btn-outline" data-user="${u.id}" data-active="${u.is_active ? 'false' : 'true'}">${u.is_active ? 'Deactivate' : 'Activate'}</button>`])));
                }
                if (tab === 'chefs') {
                    const d = await api('/admin/chefs');
                    html = dashPanel('Chef applications and verification', adminChefTable(d.data));
                }
                if (tab === 'bookings') {
                    html = dashPanel('All bookings', bookingTable((await api('/admin/bookings')).data, 'admin'));
                }
                if (tab === 'payments') {
                    const d = await api('/admin/payments');
                    html = dashPanel('Simulated payments', table(['Reference', 'Booking', 'Method', 'Amount', 'Status'], d.data.map(p => [esc(p.transaction_id), esc(p.booking?.booking_ref || p.booking_id), esc(title(p.method)), tk(p.amount), pill(p.status)])));
                }
                if (tab === 'reviews') {
                    const d = await api('/admin/reviews');
                    html = dashPanel('Review moderation', table(['Customer', 'Chef ID', 'Rating', 'Comment', 'Actions'], d.data.map(r => [esc(r.customer_name), esc(r.chef_id), `★ ${r.rating}`, esc(r.comment), `<button class="btn btn-outline" data-delete-review="${r.id}">Remove</button>`])));
                }
                if (tab === 'contacts') {
                    const d = await api('/admin/contacts');
                    html = dashPanel('Contact inquiries', table(['Sender', 'Email', 'Subject', 'Message', 'Action'], d.data.map(c => [esc(c.first_name + ' ' + c.last_name), esc(c.email), esc(c.subject), esc(c.message), c.is_read ? pill('read') : `<button class="btn btn-outline" data-contact="${c.id}">Mark read</button>`])));
                }
                if (tab === 'activity') {
                    html = dashPanel('Recent booking activity', activity((await api('/admin/recent-activity')).data));
                }
                if (tab === 'revenue') {
                    const d = await api('/admin/reports/revenue');
                    html = dashPanel('Simulated revenue by month', chart(d.data));
                }
            }
            else {
                if (tab === 'overview' || tab === 'bookings') {
                    const d = await api('/bookings');
                    const b = d.data;
                    if (tab === 'overview') {
                        let summary = role === 'chef' ? await api('/chef/earnings') : null;
                        html = `<div class="dashboard-stats">${stat('Total bookings', b.length)}${stat('Pending', b.filter(x => x.status === 'pending').length)}${stat('Completed', b.filter(x => x.status === 'completed').length)}${stat(role === 'chef' ? 'Demo earnings' : 'Demo payments', tk(summary ? summary.earnings : b.filter(x => x.paid).reduce((n, x) => n + Number(x.total_amount), 0)))}</div>`;
                    }
                    html += dashPanel(tab === 'overview' ? 'Recent bookings' : 'All bookings', bookingTable(tab === 'overview' ? b.slice(0, 8) : b, role));
                }
                if (tab === 'reviews') {
                    const d = await api('/reviews/my');
                    html = dashPanel('Reviews', d.data.length ? d.data.map(r => `<div class="review-card"><strong>★ ${r.rating}/5 · ${esc(r.customer_name)}</strong><p>${esc(r.comment)}</p><small>${esc(r.date)}</small></div>`).join('') : '<p class="muted">No reviews yet.</p>');
                }
                if (tab === 'account') {
                    const d = await api('/users/profile');
                    const u = d.user;
                    html = dashPanel('Email verification', u.email_verified_at ? '<p class="notice">Email verified.</p>' : '<p class="muted">Verify your email using the link sent during registration.</p><button type="button" class="btn btn-outline spaced" data-action="resend-verification">Resend verification email</button>') + dashPanel('Account details', `<form class="stack" data-action="account"><div class="grid2">${field('first_name', 'First name', u.first_name, 'text', 'required')}${field('last_name', 'Last name', u.last_name, 'text', 'required')}${field('phone', 'Phone', u.phone, 'tel', 'required')}${field('location', 'Location', u.location, 'text', 'required')}</div><div class="field"><span>Email</span><input disabled value="${esc(u.email)}"></div><button class="btn" type="submit">Save Profile</button><div class="form-message" role="alert"></div></form>`) + dashPanel('Change password', `<form data-action="change-password" class="stack">${field('current_password', 'Current password', '', 'password', 'required autocomplete="current-password"')}${field('password', 'New password', '', 'password', 'required minlength="8" autocomplete="new-password"')}${field('password_confirmation', 'Confirm new password', '', 'password', 'required minlength="8" autocomplete="new-password"')}<button class="btn" type="submit">Change Password</button><div class="form-message" role="alert"></div></form>`) + dashPanel('Avatar', `<form data-action="avatar" class="stack"><label class="field"><span>Profile image (max 5 MB)</span><input name="avatar" type="file" accept="image/png,image/jpeg,image/webp" required></label><button class="btn" type="submit">Upload Avatar</button><div class="form-message" role="alert"></div></form>`);
                }
                if (tab === 'chef profile') {
                    const c = (await api('/chef/profile')).chef;
                    html = dashPanel('Public chef profile', `<div class="profile-summary">${avatar(c)}<div><strong>${esc(c.name)}</strong><p>${pill(c.is_verified ? 'verified' : 'pending')} ${c.has_documents ? 'Document uploaded' : 'No verification document uploaded'}</p></div></div><form data-action="chef-profile" class="stack spaced">${field('specialty', 'Cuisine specialties', c.specialty, 'text', 'required maxlength="180"')}<label class="field"><span>Biography</span><textarea name="bio" required maxlength="2000">${esc(c.bio)}</textarea></label><div class="grid2">${field('price_per_hour', 'Hourly price (BDT)', c.price_per_hour, 'number', 'required min="100" max="100000" step="0.01"')}${field('experience_years', 'Experience years', c.experience_years, 'number', 'required min="0" max="70"')}</div><button type="submit" class="btn">Save Chef Profile</button><div class="form-message" role="alert"></div></form>`) + dashPanel('Verification and photo', `<form class="stack" data-action="chef-files"><div class="notice">Verification files are private. The admin must review them before your listing goes live. Only use sample files in development.</div><label class="field"><span>Profile image (max 5 MB)</span><input name="profile_image" type="file" accept="image/jpeg,image/png,image/webp"></label><label class="field"><span>Verification document (PDF/JPG/PNG, max 5 MB)</span><input name="verification_docs" type="file" accept="application/pdf,image/jpeg,image/png"></label><button class="btn" type="submit">Submit Files</button><div class="form-message" role="alert"></div></form>`);
                }
                if (tab === 'availability') {
                    const c = (await api('/chef/profile')).chef;
                    html = dashPanel('Weekly availability', `<form class="stack" data-action="availability"><p class="muted">Times use Bangladesh local time. Uncheck all days to pause new bookings. Existing confirmed bookings remain unchanged.</p>${['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'].map(day => `<div class="grid3" style="align-items:center"><label><input type="checkbox" name="${day}_enabled" ${c.availability?.[day] ? 'checked' : ''}> ${title(day)}</label>${field(day + '_start', 'From', c.availability?.[day]?.start || '09:00', 'time')}${field(day + '_end', 'Until', c.availability?.[day]?.end || '21:00', 'time')}</div>`).join('')}<button type="submit" class="btn">Save Availability</button><div class="form-message" role="alert"></div></form>`);
                }
                if (tab === 'earnings') {
                    const d = await api('/chef/earnings');
                    html = `<div class="dashboard-stats">${stat('Demo earnings', tk(d.earnings))}${stat('Completed', d.completed)}${stat('Rating', d.rating)}${stat('Reviews', d.reviews)}</div><div class="notice">Earnings represent completed bookings with successful simulated payments. No funds are transferred.</div>`;
                }
            }
            if (run === renderRun && content.isConnected) {
                content.innerHTML = html || '<div class="empty">No records.</div>';
                passwordToggles(content);
                if (window.Chart) {
                    $$('canvas[data-chart]', content).forEach(c => {
                        const rows = JSON.parse(c.dataset.chart);
                        new Chart(c, { type: 'bar', data: { labels: rows.map(r => r.month), datasets: [{ label: 'Simulated revenue (BDT)', data: rows.map(r => r.amount), backgroundColor: '#FF6B35', borderRadius: 5 }] }, options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });
                    });
                }
            }
        }
        catch (e) {
            if (run === renderRun && content.isConnected) {
                content.innerHTML = `<div class="empty">${esc(e.message)}<div><button class="btn btn-outline spaced" data-tab="${esc(tab)}">Retry</button></div></div>`;
            }
        }
    }
    function adminChefTable(cs) {
        return table(['Chef', 'Specialty', 'Price', 'Documents', 'Status', 'Actions'], cs.map(c => [esc(c.name), esc(c.specialty), tk(c.price_per_hour), c.has_documents ? (isDemo() ? 'Sample document' : `<a href="/api/admin/chefs/${c.id}/documents">Download</a>`) : 'Not uploaded', pill(c.is_verified ? 'verified' : 'pending'), `<button class="btn ${c.is_verified ? 'btn-outline' : ''}" data-verify="${c.id}" data-verified="${c.is_verified ? 'false' : 'true'}">${c.is_verified ? 'Revoke' : 'Verify'}</button>`]));
    }

    // Load the current page
    async function init() {
        const serial = ++renderRun;
        clearTimeout(searchTimer);
        availabilityRun++;
        chef = null;
        dashTab = 'overview';
        passwordToggles();
        const p = cfg().page;
        $$('.faq-question').forEach(el => el.setAttribute('aria-expanded', String(el.closest('.faq-item').classList.contains('active'))));
        await getMe(serial);
        if (serial !== renderRun) {
            return;
        }
        if (p === 'home') {
            try {
                const d = await api('/chefs?per_page=3');
                if ($('#featured-chefs')) {
                    $('#featured-chefs').innerHTML = d.data.map(c => chefCard(c, true)).join('') || '<p class="empty">No verified chefs yet.</p>';
                }
            }
            catch (e) {
                if ($('#featured-chefs')) {
                    $('#featured-chefs').innerHTML = `<p class="empty">${esc(e.message)}</p>`;
                }
            }
        }
        if (p === 'about') {
            try {
                const d = await api('/public/stats');
                $$('#public-stats h3').forEach((h, i) => h.textContent = Number([d.customers, d.chefs, d.bookings, d.locations][i]).toLocaleString());
            }
            catch {
            }
        }
        if (p === 'chefs') {
            const form = $('#chef-search');
            for (const key of ['q', 'cuisine', 'location', 'rating', 'max_price']) {
                if (query().has(key)) {
                    form.elements[key].value = query().get(key);
                }
            }
            await searchChefs();
        }
        if (p === 'login' || p === 'signup') {
            const next = safeNext(query().get('next'));
            if (next) {
                $$('a[href="/login"],a[href="/signup"]', $('#main')).forEach(a => a.href = a.getAttribute('href') + '?next=' + encodeURIComponent(next));
            }
            if (isDemo() && p === 'signup') {
                const form = $('[data-action=signup]');
                const note = document.createElement('p');
                note.className = 'notice';
                note.textContent = 'Demo signup: use fictional details and the sample password DemoPass123!';
                form.prepend(note);
            }
        }
        if (p === 'signup') {
            if (query().get('role') === 'chef') {
                const radio = $('input[name=user_type][value=chef]');
                radio.checked = true;
                $$('.user-option').forEach(x => x.classList.toggle('selected', !!$('input:checked', x)));
            }
        }
        if (p === 'reset-password') {
            const form = $('[data-action=reset-password]');
            form.elements.email.value = cfg().params.email || query().get('email') || '';
            form.elements.token.value = cfg().params.token || '';
        }
        if (p === 'booking' || p === 'profile') {
            try {
                const fetched = await api('/chefs/' + cfg().params.id);
                if (serial !== renderRun) {
                    return;
                }
                chef = fetched;
                if (!$('#main')) {
                    return;
                }
                if (p === 'booking') {
                    const d = await api('/services');
                    const sel = $('#service_id');
                    if (!sel || serial !== renderRun) {
                        return;
                    }
                    sel.innerHTML = '<option value="">Choose a service</option>' + d.data.map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
                    if (query().get('service')) {
                        sel.value = query().get('service');
                    }
                    const tomorrow = new Date(Date.now() + 86400000);
                    $('#booking_date').min = dhakaDate();
                    $('#booking_date').value = dhakaDate(tomorrow);
                    $('#booking_time').value = '11:00';
                    $('#check-availability').disabled = false;
                    const form = $('#booking-form'), note = $('#booking-auth-note');
                    if (!me) {
                        note.hidden = false;
                        note.className = 'notice';
                        note.innerHTML = `<strong>Sign in before submitting a booking.</strong> <a data-route href="/login?next=${encodeURIComponent(current())}">Sign in</a> or <a data-route href="/signup?next=${encodeURIComponent(current())}">create a customer account</a>. You can check the rate and availability first.`;
                    }
                    else if (me.user_type !== 'customer') {
                        note.hidden = false;
                        note.className = 'notice';
                        note.textContent = 'Only a customer account may submit a booking.';
                    }
                    else {
                        $('button[type=submit]', form).disabled = false;
                    }
                    renderBookingPrice();
                }
                else {
                    const service = query().get('service');
                    $('#chef-profile').innerHTML = `<div class="profile-summary">${avatar(chef)}<div><div class="eyebrow">Verified chef</div><h2>${esc(chef.name)}</h2><p>${esc(chef.specialty)}</p><p class="muted">${esc(chef.location)} · ${chef.experience_years} years</p></div></div><p class="spaced">${esc(chef.bio)}</p><p class="chef-rating">★ ${Number(chef.rating).toFixed(1)} (${chef.total_reviews} reviews)</p><h3>Weekly availability</h3><div class="grid2">${Object.entries(chef.availability || {}).map(([day, t]) => `<div class="notice">${title(day)}: ${esc(t.start)} – ${esc(t.end)}</div>`).join('') || '<p>Schedule not available yet.</p>'}</div>`;
                    $('#profile-booking').innerHTML = `<div class="eyebrow">Hourly rate</div><h2>${tk(chef.price_per_hour)} <small>/hour</small></h2><p>Reserve a service and see an itemized quote before booking.</p><a class="btn spaced" data-route href="/booking/${chef.id}${service ? '?service=' + encodeURIComponent(service) : ''}">Book This Chef</a>`;
                    const r = await api('/chefs/' + chef.id + '/reviews');
                    if (serial !== renderRun || !$('#chef-reviews')) {
                        return;
                    }
                    $('#chef-reviews').innerHTML = r.data.length ? r.data.map(x => `<div class="review-card"><strong>★ ${x.rating}/5 · ${esc(x.customer_name)}</strong><p>${esc(x.comment)}</p><small class="muted">${esc(x.date)}</small></div>`).join('') : '<p class="muted">No customer reviews yet.</p>';
                }
            }
            catch (e) {
                const target = $('#booking-summary') || $('#chef-profile');
                if (target) {
                    target.innerHTML = `<div class="empty">${esc(e.message)} <a data-route href="/chefs">Browse chefs</a></div>`;
                }
            }
        }
        if (p === 'payment') {
            if (!me) {
                route('/login?next=' + encodeURIComponent(current()));
                return;
            }
            if (me.user_type !== 'customer') {
                route(me.user_type === 'admin' ? '/admin/dashboard' : '/dashboard');
                return;
            }
            try {
                const b = await api('/bookings/' + cfg().params.id);
                if (serial !== renderRun) {
                    return;
                }
                $('#payment-summary').innerHTML = bookingSummary(b);
                const form = $('#payment-form'), btn = $('button[type=submit]', form);
                if (b.paid || b.status !== 'pending') {
                    btn.disabled = true;
                    form.dataset.locked = '1';
                    $('#payment-receipt').innerHTML = `<p>${b.paid ? 'Payment already recorded.' : 'This booking cannot be paid.'}</p><a data-route href="/dashboard" class="btn btn-secondary">My Bookings</a>`;
                }
                else {
                    btn.disabled = false;
                }
            }
            catch (e) {
                if ($('#payment-summary')) {
                    $('#payment-summary').innerHTML = `<div class="empty">${esc(e.message)} <a href="/dashboard" data-route>My bookings</a></div>`;
                }
            }
        }
        if (['dashboard', 'admin-dashboard'].includes(p)) {
            await dashboard();
        }
    }
    function safeNext(v) {
        if (typeof v !== 'string' || v.includes('\\') || /[\r\n]/.test(v)) {
            return null;
        }
        return /^\/(?:dashboard|booking\/\d+|payment\/\d+|chefs(?:\/\d+)?)(?:\?[^#]*)?$/.test(v) ? v : null;
    }
    let mutationBusy = false;
    const mutate = async (path, method, data) => {
        if (mutationBusy) {
            return;
        }
        mutationBusy = true;
        try {
            await api(path, method, data);
            toast('Changes saved.');
            if ($('#dashboard-content')) {
                await dashboard(dashTab);
            }
        }
        catch (e) {
            toast(e.message);
        }
        finally {
            mutationBusy = false;
        }
    };

    // Handle form submissions
    document.addEventListener('submit', async (e) => {
        const f = e.target;
        if (!(f instanceof HTMLFormElement)) {
            return;
        }
        e.preventDefault();
        if (f.id === 'home-search') {
            route('/chefs?' + formQuery(f));
            return;
        }
        if (f.id === 'chef-search') {
            await searchChefs();
            return;
        }
        const a = f.dataset.action;
        if (!a) {
            return;
        }
        await busy(f, async () => {
            const d = getForm(f);
            if (a === 'login' || a === 'signup' || a === 'admin-login') {
                const endpoint = a === 'admin-login' ? '/admin/login' : '/auth/' + a;
                if (a === 'signup' && d.password !== d.password_confirmation) {
                    throw Error('Passwords do not match.');
                }
                const r = await api(endpoint, 'POST', d);
                const u = r.user;
                toast('Welcome, ' + u.first_name);
                route(u.user_type === 'admin' ? '/admin/dashboard' : (u.user_type === 'customer' ? safeNext(query().get('next')) : null) || '/dashboard');
            }
            else if (a === 'forgot-password') {
                const r = await api('/auth/forgot-password', 'POST', d);
                message(f, r.message, true);
            }
            else if (a === 'reset-password') {
                if (d.password !== d.password_confirmation) {
                    throw Error('Passwords do not match.');
                }
                const r = await api('/auth/reset-password', 'POST', d);
                message(f, r.message, true);
            }
            else if (a === 'contact') {
                const r = await api('/contact', 'POST', d);
                f.reset();
                message(f, r.message, true);
                toast(r.message);
            }
            else if (a === 'booking') {
                if (!chef) {
                    throw Error('Chef details are unavailable.');
                }
                if (!me) {
                    route('/login?next=' + encodeURIComponent(current()));
                    return;
                }
                if (me.user_type !== 'customer') {
                    throw Error('Only customer accounts may create bookings.');
                }
                const b = await api('/bookings', 'POST', { ...d, chef_id: chef.id });
                route('/payment/' + b.id);
            }
            else if (a === 'payment') {
                const r = await api('/payments', 'POST', { ...d, booking_id: cfg().params.id });
                if (r.payment.status === 'success') {
                    modal(`<h2>Payment simulation successful</h2><p>No funds were charged. Await your chef's confirmation.</p><p><strong>${esc(r.payment.transaction_id)}</strong></p><p>${tk(r.payment.amount)}</p><a href="/dashboard" data-route class="btn">My Bookings</a>`);
                    f.dataset.locked = '1';
                    $('#payment-summary').innerHTML = bookingSummary(r.booking);
                    $('#payment-receipt').innerHTML = `<div class="notice">Demo receipt: ${esc(r.payment.transaction_id)} · ${tk(r.payment.amount)}</div><a data-route href="/dashboard" class="btn btn-secondary spaced">View Booking</a>`;
                    message(f, r.message, true);
                }
                else {
                    modal('<h2>Payment simulation failed</h2><p>No money was charged. You may close this message and retry.</p>');
                    message(f, r.message);
                }
            }
            else if (a === 'review') {
                await api('/reviews', 'POST', { ...d, booking_id: Number(d.booking_id) });
                closeModal();
                toast('Review submitted.');
                await dashboard('bookings');
            }
            else if (a === 'account') {
                await api('/users/profile', 'PUT', d);
                await getMe();
                message(f, 'Profile updated.', true);
            }
            else if (a === 'change-password') {
                if (d.password !== d.password_confirmation) {
                    throw Error('Passwords do not match.');
                }
                const response = await api('/users/change-password', 'PUT', d);
                if (response.csrf_token && $('meta[name=csrf-token]')) {
                    $('meta[name=csrf-token]').content = response.csrf_token;
                }
                f.reset();
                message(f, 'Password changed. Previous API tokens were revoked.', true);
            }
            else if (a === 'avatar' || a === 'chef-files') {
                const fd = new FormData(f);
                for (const [k, v] of fd.entries()) {
                    if (v instanceof File && !v.size) {
                        fd.delete(k);
                    }
                }
                if (isDemo()) {
                    throw Error('File uploads require the Laravel server.');
                }
                const r = await api(a === 'avatar' ? '/users/upload-avatar' : '/chefs/apply', 'POST', fd);
                message(f, r.message, true);
            }
            else if (a === 'chef-profile') {
                const r = await api('/chef/profile', 'PUT', d);
                message(f, r.message, true);
            }
            else if (a === 'availability') {
                const availability = {};
                for (const day of ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']) {
                    if (d[day + '_enabled']) {
                        if (d[day + '_end'] <= d[day + '_start']) {
                            throw Error('End time must be after start time.');
                        }
                        availability[day] = { start: d[day + '_start'], end: d[day + '_end'] };
                    }
                }
                await api('/chef/availability', 'PUT', { availability });
                message(f, Object.keys(availability).length ? 'Weekly availability saved.' : 'Availability paused. Existing confirmed bookings remain unchanged.', true);
            }
        });
    });

    // Handle buttons and navigation
    document.addEventListener('click', async (e) => {
        const el = e.target.closest('button,a');
        if (!el) {
            return;
        }
        const a = el.dataset.action;
        if (el.matches('.mobile-menu')) {
            const p = $('#navigation');
            p.classList.toggle('open');
            el.setAttribute('aria-expanded', p.classList.contains('open'));
            return;
        }
        if (el.matches('.faq-question')) {
            const item = el.closest('.faq-item');
            item.classList.toggle('active');
            el.setAttribute('aria-expanded', item.classList.contains('active'));
            return;
        }
        if (el.id === 'modal-close') {
            closeModal();
            return;
        }
        if (el.id === 'check-availability') {
            if (!chef || el.disabled) {
                return;
            }
            const form = $('#booking-form');
            if (!$('#booking_date').reportValidity() || !$('#booking_time').reportValidity() || !$('#duration_hours').reportValidity()) {
                return;
            }
            const data = getForm(form), run = ++availabilityRun;
            el.disabled = true;
            $('#availability-message').textContent = 'Checking availability...';
            try {
                const r = await api(`/chefs/${chef.id}/availability?` + new URLSearchParams({ booking_date: data.booking_date, booking_time: data.booking_time, duration_hours: data.duration_hours }));
                if (run !== availabilityRun || !form.isConnected) {
                    return;
                }
                $('#availability-message').textContent = r.available ? 'Chef is available for this slot.' : 'Chef is unavailable. Choose another date or time.';
                $('#availability-message').className = 'form-message ' + (r.available ? 'success' : 'error');
            }
            catch (err) {
                if (run === availabilityRun && form.isConnected) {
                    $('#availability-message').textContent = err.message;
                    $('#availability-message').className = 'form-message error';
                }
            }
            finally {
                if (el.isConnected) {
                    el.disabled = false;
                }
            }
            return;
        }
        if (el.dataset.page) {
            await searchChefs(Number(el.dataset.page));
            return;
        }
        if (a === 'retry-search') {
            await searchChefs();
            return;
        }
        if (a === 'resend-verification') {
            el.disabled = true;
            try {
                const r = await api('/auth/resend-verification', 'POST');
                toast(r.message);
            }
            catch (err) {
                toast(err.message);
            }
            finally {
                el.disabled = false;
            }
            return;
        }
        if (a === 'logout') {
            try {
                await api('/auth/logout', 'POST');
                me = null;
                route('/');
            }
            catch (err) {
                toast(err.message);
            }
            return;
        }
        if (el.dataset.tab) {
            await dashboard(el.dataset.tab);
            return;
        }
        if (el.dataset.details) {
            try {
                const b = await api('/bookings/' + el.dataset.details);
                modal(bookingSummary(b) + `<h3>Event notes</h3><p>${esc(b.special_instructions || 'No notes provided.')}</p><p><strong>Cuisine:</strong> ${esc(b.cuisine || 'Not specified')}</p><p><strong>Dietary notes:</strong> ${esc(b.dietary_notes || 'Not specified')}</p>`);
            }
            catch (err) {
                toast(err.message);
            }
            return;
        }
        if (el.dataset.booking) {
            const status = el.dataset.status;
            const warning = status === 'confirmed' ? 'Confirm this booking? Any overlapping pending requests will be cancelled and their demo payments refunded.' : status === 'cancelled' ? 'Cancel this booking? Any successful demo payment will be refunded.' : 'Mark this booking ' + status + '?';
            if (!confirm(warning)) {
                return;
            }
            await mutate('/bookings/' + el.dataset.booking, 'PUT', { status });
            return;
        }
        if (el.dataset.review) {
            modal(`<h2>Review your chef</h2><form class="stack spaced" data-action="review"><input type="hidden" name="booking_id" value="${esc(el.dataset.review)}"><label class="field"><span>Rating</span><select name="rating" required><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Average</option><option value="2">2 - Fair</option><option value="1">1 - Poor</option></select></label><label class="field"><span>Comment</span><textarea name="comment" maxlength="1500" placeholder="Share your experience"></textarea></label><button class="btn" type="submit">Submit Review</button><div class="form-message" role="alert"></div></form>`);
            return;
        }
        if (el.dataset.user) {
            if (confirm('Change this account status?')) {
                await mutate('/admin/users/' + el.dataset.user + '/status', 'PUT', { is_active: el.dataset.active === 'true' });
            }
            return;
        }
        if (el.dataset.verify) {
            if (confirm('Change chef verification status?')) {
                await mutate('/admin/chefs/' + el.dataset.verify + '/verify', 'PUT', { is_verified: el.dataset.verified === 'true' });
            }
            return;
        }
        if (el.dataset.deleteReview) {
            if (confirm('Permanently remove this review?')) {
                await mutate('/admin/reviews/' + el.dataset.deleteReview, 'DELETE');
            }
            return;
        }
        if (el.dataset.contact) {
            await mutate('/admin/contacts/' + el.dataset.contact + '/read', 'PUT', {});
            return;
        }
    });
    document.addEventListener('input', e => {
        if (e.target.matches('[data-table-filter]')) {
            const tools = e.target.closest('.table-tools'), table = tools.nextElementSibling?.querySelector('table');
            if (table) {
                const q = e.target.value.trim().toLowerCase();
                let count = 0;
                for (const row of table.querySelectorAll('tbody tr')) {
                    const visible = !q || row.textContent.toLowerCase().includes(q);
                    row.hidden = !visible;
                    if (visible) {
                        count++;
                    }
                }
                tools.querySelector('[data-table-count]').textContent = count + ' matching record' + (count === 1 ? '' : 's');
            }
            return;
        }
        if (e.target.id === 'duration_hours') {
            renderBookingPrice();
        }
        if (['booking_date', 'booking_time', 'duration_hours'].includes(e.target.id)) {
            clearAvailability();
        }
        if (e.target.name === 'q' && e.target.closest('#chef-search')) {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => searchChefs(), 350);
        }
    });
    document.addEventListener('change', e => {
        if (['booking_date', 'booking_time', 'duration_hours'].includes(e.target.id)) {
            clearAvailability();
        }
        if (e.target.name === 'user_type') {
            $$('.user-option').forEach(x => x.classList.toggle('selected', !!$('input:checked', x)));
        }
        if (e.target.name === 'method' && $('#method-description')) {
            $('#method-description').textContent = title(e.target.value) + ' simulation only. No real payment credentials are required.';
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && $('#navigation')?.classList.contains('open')) {
            $('#navigation').classList.remove('open');
            $('.mobile-menu').setAttribute('aria-expanded', 'false');
            $('.mobile-menu').focus();
        }
    });
    document.addEventListener('click', e => {
        const nav = $('#navigation');
        if (nav?.classList.contains('open') && !e.target.closest('.navbar')) {
            nav.classList.remove('open');
            $('.mobile-menu').setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('reset', e => {
        if (e.target.id === 'chef-search') {
            setTimeout(() => searchChefs(), 0);
        }
    });
    window.ChefLagbeInit = init;
    if (!isDemo()) {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
