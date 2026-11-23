const API = 'php/api.php';

async function api(action, options = {}) {
  const query = new URLSearchParams({ action, ...(options.query || {}) });
  const { query: _query, body, ...fetchOptions } = options;
  const response = await fetch(`${API}?${query}`, {
    credentials: 'same-origin',
    ...fetchOptions,
    headers: {
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(fetchOptions.headers || {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  const contentType = response.headers.get('content-type') || '';
  const data = contentType.includes('application/json')
    ? await response.json()
    : { error: 'The server returned an unexpected response. Check that PHP is running.' };

  if (!response.ok) throw new Error(data.error || `Request failed (${response.status}).`);
  return data;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  }[char]));
}

function showError(message) {
  const error = document.querySelector('.error-msg');
  if (error) {
    error.textContent = message;
    error.classList.add('show');
  } else {
    toast(message);
  }
}

function pulsePath(w, h) {
  const midY = h / 2;
  return `M0 ${midY} L${w * .22} ${midY} L${w * .30} ${midY - h * .35} ` +
    `L${w * .36} ${midY + h * .42} L${w * .42} ${midY - h * .9} ` +
    `L${w * .48} ${midY + h * .5} L${w * .54} ${midY} L${w * .7} ${midY} ` +
    `L${w * .76} ${midY - h * .3} L${w * .82} ${midY + h * .3} ` +
    `L${w * .88} ${midY} L${w} ${midY}`;
}

function renderWaveforms() {
  document.querySelectorAll('.js-waveform').forEach(el => {
    const w = el.dataset.w || 400;
    const h = el.dataset.h || 56;
    el.innerHTML = `<svg viewBox="0 0 ${w} ${h}" class="pulse-rule" preserveAspectRatio="none" aria-hidden="true">
      <path d="${pulsePath(w, h)}" fill="none" stroke-width="2"/></svg>`;
  });
}

function toast(message) {
  let el = document.querySelector('.toast');
  if (!el) {
    el = document.createElement('div');
    el.className = 'toast';
    document.body.appendChild(el);
  }
  el.textContent = message;
  el.classList.add('show');
  clearTimeout(el._timer);
  el._timer = setTimeout(() => el.classList.remove('show'), 2600);
}

function daysRemaining(date) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(date);
  target.setHours(0, 0, 0, 0);
  return Math.round((target - today) / 86400000);
}

function localDateString(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function compatibleRecipientGroups(donorGroup, component = 'Blood') {
  if (component === 'Plasma') {
    const plasmaCompatibility = {
      'O-': ['O-', 'O+'], 'O+': ['O-', 'O+'],
      'A-': ['O-', 'O+', 'A-', 'A+'], 'A+': ['O-', 'O+', 'A-', 'A+'],
      'B-': ['O-', 'O+', 'B-', 'B+'], 'B+': ['O-', 'O+', 'B-', 'B+'],
      'AB-': ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
      'AB+': ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
    };
    return plasmaCompatibility[donorGroup] || [];
  }
  const redCellCompatibility = {
    'O-': ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
    'O+': ['O+', 'A+', 'B+', 'AB+'],
    'A-': ['A-', 'A+', 'AB-', 'AB+'],
    'A+': ['A+', 'AB+'],
    'B-': ['B-', 'B+', 'AB-', 'AB+'],
    'B+': ['B+', 'AB+'],
    'AB-': ['AB-', 'AB+'],
    'AB+': ['AB+'],
  };
  return redCellCompatibility[donorGroup] || [];
}

async function initAuthPage() {
  const roleButtons = document.querySelectorAll('.role-toggle button');
  const roleInput = document.getElementById('roleInput');
  const donorFields = document.getElementById('donorOnlyFields');
  const requesterFields = document.getElementById('requesterOnlyFields');
  const nameLabel = document.getElementById('nameLabel');

  roleButtons.forEach(button => button.addEventListener('click', () => {
    roleButtons.forEach(item => item.classList.remove('active'));
    button.classList.add('active');
    if (roleInput) roleInput.value = button.dataset.role;
    const isDonor = button.dataset.role === 'donor';
    if (donorFields) donorFields.style.display = isDonor ? 'block' : 'none';
    if (requesterFields) requesterFields.style.display = button.dataset.role === 'hospital' ? 'block' : 'none';
    const bloodGroup = document.getElementById('groupField');
    if (bloodGroup) {
      bloodGroup.required = isDonor;
      bloodGroup.disabled = !isDonor;
    }
    if (nameLabel) nameLabel.textContent = button.dataset.role === 'hospital' ? 'Contact person name' : 'Full name';
  }));

  const form = document.getElementById('authForm');
  if (!form) return;
  const isRegister = location.pathname.endsWith('register.html');
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const value = id => document.getElementById(id)?.value.trim() || '';
    const role = roleInput?.value || 'donor';

    if (isRegister && !value('nameField')) {
      showError('Please enter your name or organization name.');
      return;
    }

    try {
      const result = await api(isRegister ? 'register' : 'login', {
        method: 'POST',
        body: isRegister ? {
          name: role === 'hospital' ? (value('orgField') || value('nameField')) : value('nameField'),
          email: value('emailField'),
          password: document.getElementById('passField')?.value || '',
          phone: value('phoneField'),
          role,
          blood_group: value('groupField'),
          last_donation_date: value('lastDonationField'),
          location: value('locationField') || value('orgAddressField'),
        } : {
          email: value('emailField'),
          password: document.getElementById('passField')?.value || '',
        },
      });
      const user = result.user || { role: role === 'hospital' ? 'recipient' : role };
      location.href = user.role === 'donor' ? 'donor-dashboard.html'
        : user.role === 'admin' ? 'admin-panel.html' : 'hospital-portal.html';
    } catch (error) {
      showError(error.message);
    }
  });
}

async function initDashboardChrome(roleLabel, expectedRole) {
  try {
    const { user } = await api('me');
    if (expectedRole && user.role !== expectedRole) {
      const destination = user.role === 'donor' ? 'donor-dashboard.html'
        : user.role === 'admin' ? 'admin-panel.html' : 'hospital-portal.html';
      location.replace(destination);
      return false;
    }
    const name = document.querySelector('.who .name');
    const role = document.querySelector('.who .sub');
    if (name) name.textContent = user.name || 'Account';
    if (role) role.textContent = roleLabel;
  } catch (error) {
    location.replace('login.html');
    return false;
  }

  document.getElementById('logoutLink')?.addEventListener('click', async event => {
    event.preventDefault();
    const link = event.currentTarget;
    link.setAttribute('aria-disabled', 'true');
    try {
      await api('logout', { method: 'POST' });
      location.replace('index.html');
    } catch (error) {
      link.removeAttribute('aria-disabled');
      toast(error.message);
    }
  });
  return true;
}

async function initDonorDashboard() {
  try {
    const [{ user }, { requests }, { rewards }, { donors }] = await Promise.all([
      api('me'), api('requests'), api('rewards'), api('leaderboard'),
    ]);
    const componentSelect = document.getElementById('donationComponent');
    if (componentSelect && user.plasma_eligible) {
      componentSelect.insertAdjacentHTML('beforeend', '<option value="Plasma">Plasma</option>');
    }
    const nextDate = user.next_eligible_date
      ? new Date(`${user.next_eligible_date}T00:00:00`) : new Date();
    const remaining = user.next_eligible_date ? daysRemaining(nextDate) : 0;
    const ready = remaining <= 0;

    document.getElementById('vitalCard')?.classList.add(ready ? 'is-ready' : 'is-wait');
    document.getElementById('statusValue').textContent = ready ? 'Eligible now' : `${remaining} day${remaining === 1 ? '' : 's'} to go`;
    document.getElementById('statusValue').className = `status-value ${ready ? 'ready' : 'wait'}`;
    document.getElementById('statusCaption').textContent = ready
      ? `Ready to donate ${component}. Last donation ${user.last_donation_date || 'not recorded'}.`
      : `Next eligible ${user.next_eligible_date}.`;
    document.getElementById('pointsStat').textContent = user.blood_points;
    document.getElementById('groupStat').textContent = user.blood_group;
    document.getElementById('lastStat').textContent = user.last_donation_date || '—';
    document.getElementById('plasmaStat').textContent = user.plasma_eligible ? 'Yes' : 'No';

    const matches = requests.filter(request => request.status === 'Open' &&
      compatibleRecipientGroups(user.blood_group, request.component).includes(request.blood_group));
    document.getElementById('matchList').innerHTML = matches.length ? matches.map(request => `
      <tr><td>${escapeHtml(request.hospital)}</td><td>${request.blood_group} · ${request.component}</td>
      <td><span class="badge ${request.urgency === 'Critical' ? 'critical' : 'normal'}">${request.urgency}</span></td>
      <td>${request.posted.slice(0, 10)}</td></tr>`).join('') :
      '<tr><td colspan="4" class="empty-state">No open requests match your blood group right now.</td></tr>';

    const logButton = document.getElementById('logDonationBtn');
    logButton.disabled = !ready;
    logButton.addEventListener('click', async () => {
      try {
        await api('log_donation', {
          method: 'POST',
          body: {
            component: componentSelect?.value || 'Blood',
            donation_date: localDateString(),
          },
        });
        toast('Donation logged — awaiting admin verification.');
        setTimeout(() => location.reload(), 900);
      } catch (error) {
        toast(error.message);
      }
    });

    document.getElementById('rewardGrid').innerHTML = rewards.map(reward => `
      <div class="reward-card"><div class="partner">${escapeHtml(reward.partner_name)}</div>
      <h3>${escapeHtml(reward.discount_details)}</h3><div class="pts">${reward.points_required} pts</div>
      <button class="btn btn-sm ${user.blood_points >= reward.points_required ? 'btn-teal' : 'btn-ghost'}"
        data-reward="${reward.reward_id}" ${user.blood_points >= reward.points_required ? '' : 'disabled'}>
        ${user.blood_points >= reward.points_required ? 'Redeem' : 'Not enough points'}</button></div>`).join('');
    document.querySelectorAll('[data-reward]').forEach(button => button.addEventListener('click', async () => {
      try {
        const result = await api('redeem', { method: 'POST', body: { reward_id: button.dataset.reward } });
        toast(`Redeemed. Coupon: ${result.coupon_code}`);
        setTimeout(() => location.reload(), 900);
      } catch (error) {
        toast(error.message);
      }
    }));

    document.getElementById('leaderList').innerHTML = donors.map((donor, index) =>
      `<li><span class="rank">${index + 1}</span> ${escapeHtml(donor.name)}
      <span class="pts">${donor.points} pts</span></li>`).join('');
  } catch (error) {
    toast(error.message);
  }
}

async function initHospitalPortal() {
  const renderPool = async group => {
    try {
      const { donors } = await api('donors', { query: { blood_group: group || 'all' } });
      document.getElementById('donorPool').innerHTML = donors.length ? donors.map(donor => `
        <tr><td>${escapeHtml(donor.name)}</td><td>${escapeHtml(donor.blood_group)}</td>
        <td>${donor.plasma_eligible ? 'Blood + Plasma' : 'Blood only'}</td>
        <td>${escapeHtml(donor.location || 'Not provided')}</td><td><span class="badge normal">Ready</span></td></tr>`).join('') :
        '<tr><td colspan="5" class="empty-state">No donors are currently eligible for this filter.</td></tr>';
    } catch (error) { toast(error.message); }
  };
  const renderRequests = async () => {
    try {
      const { requests } = await api('requests');
      document.getElementById('requestList').innerHTML = requests.map(request => `
        <tr><td>#${request.id}</td><td>${request.blood_group} · ${request.component}</td>
        <td><span class="badge ${request.urgency === 'Critical' ? 'critical' : 'normal'}">${request.urgency}</span></td>
        <td><span class="badge ${request.status === 'Open' ? 'open' : 'fulfilled'}">${request.status}</span></td>
        <td>${request.posted.slice(0, 10)}</td></tr>`).join('');
    } catch (error) { toast(error.message); }
  };
  await renderPool('all');
  await renderRequests();
  document.getElementById('groupFilter')?.addEventListener('change', event => renderPool(event.target.value));
  document.getElementById('postRequestForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = new FormData(event.target);
    try {
      await api('create_request', {
        method: 'POST',
        body: {
          blood_group: form.get('bloodGroup'),
          component: form.get('component'),
          urgency: form.get('urgency'),
        },
      });
      toast('Request posted.');
      setTimeout(() => location.reload(), 900);
    } catch (error) { toast(error.message); }
  });
}

async function initAdminPanel() {
  try {
    const [{ donors, open_requests, pending, points, pending_donations }, { requests }, { rewards }] =
      await Promise.all([api('admin_overview'), api('requests'), api('rewards')]);
    document.getElementById('adminDonors').textContent = donors;
    document.getElementById('adminOpenReq').textContent = open_requests;
    document.getElementById('adminPending').textContent = pending;
    document.getElementById('adminPointsIssued').textContent = points;
    const verifyList = document.getElementById('verifyList');
    verifyList.innerHTML = pending_donations.length ? pending_donations.map(item => `
      <tr><td>${escapeHtml(item.donor)}</td><td>${item.component}</td><td>${item.date}</td>
      <td><span class="badge pending">Pending</span></td>
      <td><button class="btn btn-sm btn-teal" data-verify="${item.id}">Verify + award points</button></td></tr>`).join('') :
      '<tr><td colspan="5" class="empty-state">Nothing waiting on verification.</td></tr>';
    verifyList.querySelectorAll('[data-verify]').forEach(button => button.addEventListener('click', async () => {
      try {
        await api('verify_donation', { method: 'POST', body: { donation_id: button.dataset.verify } });
        toast('Verified. Blood Points awarded.');
        setTimeout(() => location.reload(), 900);
      } catch (error) { toast(error.message); }
    }));
    document.getElementById('adminRequestList').innerHTML = requests.map(request => `
      <tr><td>#${request.id}</td><td>${escapeHtml(request.hospital)}</td>
      <td>${request.blood_group} · ${request.component}</td><td>${request.urgency}</td><td>${request.status}</td></tr>`).join('');
    document.getElementById('adminRewardList').innerHTML = rewards.map(reward =>
      `<tr><td>${escapeHtml(reward.partner_name)}</td><td>${escapeHtml(reward.discount_details)}</td>
      <td>${reward.points_required} pts</td></tr>`).join('');
  } catch (error) {
    toast(error.message);
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  renderWaveforms();
  if (document.getElementById('authForm')) return initAuthPage();
  const page = document.body.dataset.page;
  if (page === 'donor') {
    if (await initDashboardChrome('Donor', 'donor')) await initDonorDashboard();
  } else if (page === 'hospital') {
    if (await initDashboardChrome('Hospital / Recipient', 'recipient')) await initHospitalPortal();
  } else if (page === 'admin') {
    if (await initDashboardChrome('Administrator', 'admin')) await initAdminPanel();
  }
});
