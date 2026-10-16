import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vitest';

const srcDir = join(__dirname, '../src');

function files(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        return statSync(path).isDirectory() ? files(path) : path.endsWith('.tsx') ? [path] : [];
    });
}

describe('camps de data i hora', () => {
    it('no fan servir els controls natius, que mostren MM/DD/AAAA o AM/PM segons l’idioma del navegador', () => {
        const offenders = files(srcDir).filter((file) => /type=["'](date|time|datetime-local)["']/.test(readFileSync(file, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')));

        expect(offenders.map((file) => relative(srcDir, file))).toEqual([]);
    });
});
