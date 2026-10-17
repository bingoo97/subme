const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public_html/assets/js/admin.js'), 'utf8');
const start = source.indexOf('    // Keep customer lookup and manual entry');
const end = source.indexOf('    const profileFilter', start);
function panel(open) { return { open, events: {}, addEventListener(name, handler) { this.events[name] = handler; } }; }
const lookup = panel(false), fields = panel(true), listeners = {};
const root = {
    querySelector: selector => selector.includes('lookup-panel') ? lookup : fields,
    addEventListener: (name, handler) => { listeners[name] = handler; }
};
vm.runInNewContext(source.slice(start, end), { root });
let checks = 0;
function check(condition, label) { assert.ok(condition, label); checks++; }
lookup.open = true; lookup.events.toggle();
check(!fields.open, 'Opening lookup closes manual fields');
fields.open = true; fields.events.toggle();
check(!lookup.open, 'Opening manual fields closes lookup');
fields.open = false; fields.events.toggle();
check(!lookup.open, 'Closing a panel does not open unrelated controls');
lookup.open = true;
listeners.change({ target: { matches: () => true, value: '25' } });
check(fields.open && !lookup.open, 'Selecting a customer opens details for review');
fields.open = false;
listeners.change({ target: { matches: () => true, value: '0' } });
check(!fields.open, 'Empty selection does not interrupt lookup');
const outer = panel(false), inner = panel(false);
inner.parentElement = { closest: () => outer };
listeners.invalid({ target: { closest: () => inner } });
check(inner.open && outer.open, 'Invalid nested fields are exposed for native focus');
console.log(`PASS: ${checks} billing disclosure/customer/validation assertions`);
