const crypto = require('crypto');

const LICENSE = 'Vm8Lk7Uj2JmsjCPVPVjrLa7zgfx3uz9E';

// In-memory store (resets on cold start; replace with database for production)
// Structure: { [user_key]: { game, expires, maxDevices, serials: Set<string> } }
const keyStore = new BoundKeyStore();

function BoundKeyStore() {
  this.keys = {};
  this.get = function(k) { return this.keys[k] || null; };
  this.set = function(k, v) { this.keys[k] = v; };
}

function parseBody(body) {
  const params = new URLSearchParams(body);
  return {
    game: params.get('game') || '',
    user_key: params.get('user_key') || '',
    serial: params.get('serial') || ''
  };
}

function computeToken(game, user_key, serial) {
  const raw = `${game}-${user_key}-${serial}-${LICENSE}`;
  return crypto.createHash('md5').update(raw).digest('hex');
}

function formatDate(iso) {
  if (!iso || iso === 'lifetime') return '2099-01-12 00:00:00';
  const d = new Date(iso);
  const pad = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

exports.handler = async (event) => {
  const headers = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Content-Type': 'application/json'
  };

  if (event.httpMethod === 'OPTIONS') {
    return { statusCode: 200, headers, body: '' };
  }

  if (event.httpMethod !== 'POST') {
    return {
      statusCode: 405,
      headers,
      body: JSON.stringify({ status: false, error: 'Method not allowed' })
    };
  }

  try {
    const { game, user_key, serial } = parseBody(event.body || '');

    if (!game || !user_key || !serial) {
      return {
        statusCode: 400,
        headers,
        body: JSON.stringify({ status: false, error: 'Missing required fields: game, user_key, serial' })
      };
    }

    // Look up key in store
    const record = keyStore.get(user_key);

    if (!record) {
      return {
        statusCode: 200,
        headers,
        body: JSON.stringify({ status: false, reason: 'Invalid Key!' })
      };
    }

    // Check if key is expired
    if (record.expires && record.expires !== 'lifetime') {
      if (new Date(record.expires) < new Date()) {
        return {
          statusCode: 200,
          headers,
          body: JSON.stringify({ status: false, reason: 'Key Expired!' })
        };
      }
    }

    // Check device limit
    const serials = record.serials || new Set();
    if (!serials.has(serial) && serials.size >= record.maxDevices) {
      return {
        statusCode: 200,
        headers,
        body: JSON.stringify({ status: false, reason: 'Max Devices Limit Reached!' })
      };
    }

    // Register serial (bind new device or re-authorize existing)
    serials.add(serial);
    record.serials = serials;
    keyStore.set(user_key, record);

    // Generate token
    const token = computeToken(game, user_key, serial);

    return {
      statusCode: 200,
      headers,
      body: JSON.stringify({
        status: true,
        data: {
          EXP: formatDate(record.expires),
          token: token,
          rng: Math.floor(Date.now() / 1000)
        }
      })
    };
  } catch (err) {
    return {
      statusCode: 500,
      headers,
      body: JSON.stringify({ status: false, error: 'Internal server error' })
    };
  }
};

// Helper to register a key from admin panel (call this from your admin API or deploy hook)
exports.registerKey = function(user_key, game, expires, maxDevices) {
  keyStore.set(user_key, {
    game: game,
    expires: expires,
    maxDevices: maxDevices || 1,
    serials: new Set()
  });
};
