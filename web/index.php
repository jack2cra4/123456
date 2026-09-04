<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SDK License Manager</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <header>
        <div>
            <h1>SDK License Manager</h1>
            <div class="subtitle">Generate, manage, and revoke SDK keys</div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
            <button class="btn btn-primary" style="width:auto;" onclick="loadKeys()">Refresh</button>
        </div>
    </header>

    <div class="stats">
        <div class="stat-card">
            <div class="number" id="totalKeys">0</div>
            <div class="label">Total Keys</div>
        </div>
        <div class="stat-card">
            <div class="number" id="activeKeys" style="color:var(--success)">0</div>
            <div class="label">Active</div>
        </div>
        <div class="stat-card">
            <div class="number" id="revokedKeys" style="color:var(--danger)">0</div>
            <div class="label">Revoked</div>
        </div>
        <div class="stat-card">
            <div class="number" id="totalDevices" style="color:var(--warning)">0</div>
            <div class="label">Devices</div>
        </div>
    </div>

    <div class="main-content">
        <div class="panel">
            <h2>Generate New Key</h2>
            <form id="createForm" onsubmit="return createKey(event)">
                <div class="form-group">
                    <label>Label</label>
                    <input type="text" id="label" placeholder="e.g. Client App v2" required>
                </div>
                <div class="form-group">
                    <label>Duration</label>
                    <select id="duration">
                        <option value="7">7 Days</option>
                        <option value="14">14 Days</option>
                        <option value="30" selected>30 Days</option>
                        <option value="90">90 Days</option>
                        <option value="180">180 Days</option>
                        <option value="365">365 Days</option>
                        <option value="lifetime">Lifetime</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Max Devices</label>
                    <input type="number" id="maxDevices" value="5" min="1" max="100">
                </div>
                <button type="submit" class="btn btn-primary">Generate SDK Key</button>
            </form>
        </div>

        <div class="panel">
            <h2>Active Keys</h2>
            <div class="keys-list" id="keysList">
                <div class="empty-state">
                    <div class="icon">&#128273;</div>
                    <div>No keys generated yet</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const API_BASE = 'api.php';

async function apiCall(action, method = 'GET', body = null) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(`${API_BASE}?action=${action}`, opts);
    return res.json();
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

async function createKey(e) {
    e.preventDefault();
    const data = {
        label: document.getElementById('label').value,
        duration: document.getElementById('duration').value,
        max_devices: parseInt(document.getElementById('maxDevices').value)
    };
    const res = await apiCall('create', 'POST', data);
    if (res.status) {
        showToast('Key generated: ' + res.key.sdk_key);
        document.getElementById('label').value = '';
        loadKeys();
    } else {
        showToast(res.reason || 'Failed to create key', 'error');
    }
}

async function revokeKey(sdkKey) {
    if (!confirm('Revoke this key? Devices using it will lose access.')) return;
    const res = await apiCall('revoke', 'POST', { sdk_key: sdkKey });
    if (res.status) {
        showToast('Key revoked');
        loadKeys();
    } else {
        showToast(res.reason || 'Failed to revoke', 'error');
    }
}

async function deleteKey(sdkKey) {
    if (!confirm('Permanently delete this key? This cannot be undone.')) return;
    const res = await apiCall('delete', 'POST', { sdk_key: sdkKey });
    if (res.status) {
        showToast('Key deleted');
        loadKeys();
    } else {
        showToast(res.reason || 'Failed to delete', 'error');
    }
}

function copyKey(key) {
    navigator.clipboard.writeText(key).then(() => showToast('Copied to clipboard'));
}

function isExpired(expiresAt) {
    if (!expiresAt || expiresAt === 'lifetime') return false;
    return new Date(expiresAt) < new Date();
}

function formatDate(dateStr) {
    if (!dateStr || dateStr === 'lifetime') return 'Lifetime';
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric'
    });
}

async function loadKeys() {
    const res = await apiCall('list');
    if (!res.status) return;

    const keys = res.keys || [];
    const revoked = res.revoked || [];

    let totalDevices = 0;
    keys.forEach(k => totalDevices += (k.devices || []).length);

    document.getElementById('totalKeys').textContent = keys.length + revoked.length;
    document.getElementById('activeKeys').textContent = keys.length;
    document.getElementById('revokedKeys').textContent = revoked.length;
    document.getElementById('totalDevices').textContent = totalDevices;

    const container = document.getElementById('keysList');
    container.innerHTML = '';

    const allEntries = [
        ...keys.map(k => ({ ...k, isRevoked: false })),
        ...revoked.map(k => ({ ...k, isRevoked: true }))
    ].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

    if (allEntries.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="icon">&#128273;</div><div>No keys generated yet</div></div>';
        return;
    }

    allEntries.forEach(entry => {
        const expired = isExpired(entry.expires_at);
        let badgeClass = 'badge-active';
        let badgeText = 'Active';
        if (entry.isRevoked) { badgeClass = 'badge-revoked'; badgeText = 'Revoked'; }
        else if (expired) { badgeClass = 'badge-expired'; badgeText = 'Expired'; }

        const div = document.createElement('div');
        div.className = 'key-entry' + (entry.isRevoked ? ' revoked' : '');
        div.innerHTML = `
            <div class="key-header">
                <div class="key-label">${escapeHtml(entry.label || 'Unnamed')}</div>
                <span class="key-badge ${badgeClass}">${badgeText}</span>
            </div>
            <div class="key-details">
                <span class="sdk-key">${escapeHtml(entry.sdk_key)}</span>
                <span>Expires: ${formatDate(entry.expires_at)}</span>
                <span>Devices: ${(entry.devices || []).length} / ${entry.max_devices || 5}</span>
                <span>Created: ${formatDate(entry.created_at)}</span>
            </div>
            <div class="key-actions">
                <button class="btn btn-primary btn-sm" style="width:auto" onclick="copyKey('${escapeHtml(entry.sdk_key)}')">Copy Key</button>
                ${!entry.isRevoked && !expired ? `<button class="btn btn-danger btn-sm" onclick="revokeKey('${escapeHtml(entry.sdk_key)}')">Revoke</button>` : ''}
                <button class="btn btn-danger btn-sm" onclick="deleteKey('${escapeHtml(entry.sdk_key)}')">Delete</button>
            </div>
        `;
        container.appendChild(div);
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

loadKeys();
</script>
</body>
</html>
