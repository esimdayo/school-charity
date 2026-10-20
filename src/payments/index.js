// PAYMENT_PROVIDERS is a comma-separated list, e.g. "payme,click".
// Defaults to "test" so a fresh install works without any payment account.
const all = {
  test: require('./test'),
  payme: require('./payme'),
  click: require('./click'),
};

const enabled = (process.env.PAYMENT_PROVIDERS || 'test')
  .split(',')
  .map((s) => s.trim())
  .filter((s) => all[s])
  .map((s) => all[s]);

if (enabled.length === 0) enabled.push(all.test);

module.exports = { enabled, get: (name) => enabled.find((p) => p.name === name) };
