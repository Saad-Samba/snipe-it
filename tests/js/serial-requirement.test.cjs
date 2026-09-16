const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const vm = require('node:vm');
const path = require('node:path');

const template = readFileSync(path.join(__dirname, '../../resources/views/hardware/edit.blade.php'), 'utf8');
const updateFunction = template.match(/    function updateSerialRequirement\(required\) \{[\s\S]*?\n    \}/)[0];
const changeHandler = template.match(/\$\('#model_select_id'\)\.on\('change', (function \(\) \{[\s\S]*?\n        \})\);/)[1];

function createInput() {
    const classes = new Set();
    return {
        value: 'EXISTING-SERIAL',
        classes,
        attributes: {},
        parentElement: { classList: { toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name) } },
        setAttribute(name, value) { this.attributes[name] = value; },
    };
}

test('serial indicators toggle for every row without changing entered values', () => {
    const inputs = [createInput()];
    const help = { textContent: '' };
    const context = vm.createContext({
        serialRequired: false,
        serialRequiredHelp: 'The selected model requires a serial number for each asset.',
        document: {
            querySelectorAll: () => inputs,
            getElementById: () => help,
        },
    });
    vm.runInContext(updateFunction, context);
    context.updateSerialRequirement(true);
    assert.equal(inputs[0].required, true);
    assert.equal(inputs[0].classes.has('required'), true);
    assert.match(help.textContent, /requires a serial/);

    inputs.push(createInput());
    context.updateSerialRequirement(context.serialRequired);
    assert.equal(inputs[1].required, true);
    assert.equal(inputs[1].attributes['aria-required'], 'true');
    assert.equal(inputs[1].attributes['aria-describedby'], 'serial-requirement-help');

    context.updateSerialRequirement(false);
    for (const input of inputs) {
        assert.equal(input.required, false);
        assert.equal(input.classes.has('required'), false);
        assert.equal(input.value, 'EXISTING-SERIAL');
    }
    assert.equal(help.textContent, '');
});

test('model changes read Select2 metadata, initial options, and cleared selections', () => {
    for (const [selection, initialRequirement, expected] of [
        [{ require_serial: true }, undefined, true],
        [{ require_serial: false }, true, false],
        [{ require_serial: 1 }, undefined, true],
        [{ require_serial: '1' }, undefined, true],
        [{ require_serial: '0' }, undefined, false],
        [{ id: 1 }, true, true],
        [{ id: 2 }, false, false],
        [undefined, undefined, false],
        [{ id: '' }, undefined, false],
    ]) {
        let actual;
        const context = vm.createContext({
            $: () => ({
                select2: () => selection ? [selection] : [],
                find: () => ({ data: () => initialRequirement }),
            }),
            updateSerialRequirement: required => { actual = required; },
        });
        vm.runInContext('(' + changeHandler + ')()', context);
        assert.equal(actual, expected);
    }
});
