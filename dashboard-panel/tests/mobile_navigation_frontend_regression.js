'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../public_html/assets/js/admin.js'), 'utf8');
const code = source.slice(source.indexOf('    function initMobileNavigation()'), source.indexOf('    function initSidebarAndDropdowns()'));
let checks = 0;
function run({mode = 'back', referrer = 'http://localhost:8088/admin/?page=orders', length = 2, ...modifiers} = {}) {
    let handler, backs = 0, prevented = false;
    const link = {getAttribute: () => mode, addEventListener: (_event, fn) => { handler = fn; }};
    const context = {URL, q: () => link, doc: {referrer}, window: {location: {href: 'http://localhost:8088/admin/?page=payments&payment_id=1', origin: 'http://localhost:8088'}, history: {length, back: () => { backs++; }}}};
    vm.createContext(context); vm.runInContext(code, context); context.initMobileNavigation();
    if (handler) handler({button: 0, preventDefault: () => { prevented = true; }, ...modifiers});
    return {backs, prevented};
}
function check(options, expected) { assert.deepEqual(run(options), {backs: expected ? 1 : 0, prevented: expected}); checks++; }
check({}, true);
check({referrer: 'http://localhost:8088/admin/index.php?page=users'}, true);
for (const referrer of ['', 'invalid', 'https://foreign.example/admin/?page=orders', 'http://localhost:8088/', 'http://localhost:8088/admin/provider-browser-setup.html', 'http://localhost:8088/admin/?page=payments&payment_id=1']) check({referrer}, false);
check({length: 1}, false);
check({mode: 'link'}, false);
for (const modifier of ['ctrlKey', 'metaKey', 'shiftKey', 'altKey', 'defaultPrevented']) check({[modifier]: true}, false);
check({button: 1}, false);
console.log(`PASS: ${checks} mobile back-navigation checks`);
