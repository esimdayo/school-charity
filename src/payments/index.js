// PAYMENT_PROVIDERS is a comma-separated list, e.g. "card" or "card,payme".
// Defaults to "card": buyers transfer money to RECIPIENT_CARD and a teacher
// confirms it. "test" is a fake payment page for trying the site.
const all = {
  card: require('./card'),
  test: require('./test'),
  payme: require('./payme'),
  click: require('./click'),
};

const enabled = (process.env.PAYMENT_PROVIDERS || 'card')
  .split(',')
  .map((s) => s.trim())
  .filter((s) => all[s])
  .map((s) => all[s]);

if (enabled.length === 0) enabled.push(all.card);

module.exports = { enabled, get: (name) => enabled.find((p) => p.name === name) };
