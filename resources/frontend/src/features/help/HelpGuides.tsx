import { ArrowUp } from 'lucide-react';
import { Link } from 'react-router';
import { PageHeader } from '@/components/PageHeader';
import { Card } from '@/components/ui/card';
import { ca, t } from '@/i18n/ca';

/** Guia ràpida: els passos de cada jornada. */
export function QuickGuidePage() {
    const { quick } = ca.help;
    const steps = Object.values(quick.steps);

    return (
        <>
            <PageHeader title={quick.title} description={quick.subtitle} />
            <ol className="grid max-w-3xl gap-3">
                {steps.map((step, index) => (
                    <li key={step.title}>
                        <Card className="flex-row items-start gap-4 px-4 py-4">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary font-bold text-primary-foreground" aria-hidden>
                                {index + 1}
                            </span>
                            <div className="min-w-0">
                                <p className="sr-only">{t('help.quick.stepLabel', { number: index + 1 })}</p>
                                <h2 className="font-semibold">{step.title}</h2>
                                <p className="mt-1 text-sm text-muted-foreground">{step.text}</p>
                            </div>
                        </Card>
                    </li>
                ))}
            </ol>

            <section className="mt-8 max-w-3xl" aria-labelledby="consells">
                <h2 id="consells" className="text-lg font-semibold">
                    {quick.tipsTitle}
                </h2>
                <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
                    {quick.tips.map((tip) => (
                        <li key={tip}>{tip}</li>
                    ))}
                </ul>
            </section>

            <p className="mt-8 max-w-3xl text-sm">
                {quick.seeFull}{' '}
                <Link to="/ajuda/guia-completa" className="font-medium text-primary underline underline-offset-2">
                    {quick.seeFullLink}
                </Link>
                .
            </p>
        </>
    );
}

/** Guia completa: com funciona l'aplicació, per seccions. */
export function FullGuidePage() {
    const { full } = ca.help;
    const sections = Object.entries(full.sections);

    return (
        <>
            <PageHeader title={full.title} description={full.subtitle} />
            <div className="grid gap-8 lg:grid-cols-[14rem_minmax(0,1fr)]">
                <nav aria-label={full.toc} className="lg:sticky lg:top-6 lg:self-start" id="contingut-guia">
                    <h2 className="mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">{full.toc}</h2>
                    <ul className="grid gap-1 text-sm">
                        {sections.map(([id, section]) => (
                            <li key={id}>
                                <a href={`#guia-${id}`} className="block rounded-md px-2 py-1 hover:bg-secondary">
                                    {section.title}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="max-w-3xl space-y-8">
                    {sections.map(([id, section], index) => (
                        <section key={id} id={`guia-${id}`} aria-labelledby={`guia-titol-${id}`} className="scroll-mt-6">
                            <h2 id={`guia-titol-${id}`} className="text-xl font-semibold">
                                {index + 1}. {section.title}
                            </h2>
                            <p className="mt-2 text-sm">{section.intro}</p>
                            <ul className="mt-3 list-disc space-y-2 pl-5 text-sm">
                                {section.items.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                            <a href="#contingut-guia" className="mt-3 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                                <ArrowUp className="size-3" aria-hidden />
                                {full.backToTop}
                            </a>
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}
