const crypto = require('crypto');

const LICENSE = 'Vm8Lk7Uj2JmsjCPVPVjrLa7zgfx3uz9E';

function parseBody(body) {
  const params = new URLSearchParams(body);
  return {
    game: params.get('game') || '',
    user_key: params.get('user_key') || '',
    serial: params.get('serial') || ''
  };
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

    const raw = `${game}-${user_key}-${serial}-${LICENSE}`;
    const token = crypto.createHash('md5').update(raw).digest('hex');

    return {
      statusCode: 200,
      headers,
      body: JSON.stringify({
        status: true,
        data: {
          EXP: '2099-01-12 00:00:00',
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
