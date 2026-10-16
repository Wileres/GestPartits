import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vitest';

const srcDir = join(__dirname, '../src');

/** Textos JSX permesos fora del diccionari (noms propis o sigles). */
const allowed = new Set(['SPM']);

function tsxFiles(dir: string): string[] {
    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) {
            // Els components de shadcn/ui no tenen textos visibles propis.
            return entry === 'ui' ? [] : tsxFiles(path);
        }

        return path.endsWith('.tsx') ? [path] : [];
    });
}

describe('components', () => {
    it('no contenen textos visibles escrits directament (tots passen per t())', () => {
        const offenders: string[] = [];

        for (const file of tsxFiles(srcDir)) {
            const source = readFileSync(file, 'utf8');
            // Text JSX: el «>» va enganxat al nom de l'etiqueta, a una cometa o a una clau (a TypeScript, «a > b» porta espais),
            // i el text acaba en un tancament «</» o en una expressió «{».
            for (const match of source.matchAll(/(?<=[\p{L}\d"'}])>(?![(,;)])([^<>{}\n=]*\p{L}[^<>{}\n]*)(?=<\/|\{)/gu)) {
                const text = match[1]?.trim() ?? '';
                if (text !== '' && !allowed.has(text)) {
                    offenders.push(`${relative(srcDir, file)}: «${text}»`);
                }
            }
        }

        expect(offenders).toEqual([]);
    });
});
