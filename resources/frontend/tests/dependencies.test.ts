import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

describe('dependències', () => {
    it('no inclouen el paquet «cn» que afegeix el CLI de shadcn (cn és local, a @/lib/utils)', () => {
        const pkg = JSON.parse(readFileSync(join(__dirname, '../../../package.json'), 'utf8')) as { dependencies?: Record<string, string> };

        expect(Object.keys(pkg.dependencies ?? {})).not.toContain('cn');
        expect(Object.keys(pkg.dependencies ?? {})).not.toContain('next-themes');
    });
});
